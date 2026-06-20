<?php

namespace App\Console\Commands;

use App\Services\GeocodingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillEventData extends Command
{
    protected $signature = 'events:backfill';

    protected $description = 'Backfill location_name and placeholder images for existing seeded events';

    private const PLACEHOLDER_IMAGES = [
        'events/placeholder-1.svg',
        'events/placeholder-2.svg',
        'events/placeholder-3.svg',
        'events/placeholder-4.svg',
        'events/placeholder-5.svg',
        'events/placeholder-6.svg',
    ];

    public function handle(GeocodingService $geo): int
    {
        $this->backfillLocations($geo);
        $this->backfillImages();

        return self::SUCCESS;
    }

    private function backfillLocations(GeocodingService $geo): void
    {
        $total = DB::table('events')->whereNull('location_name')->count();

        if ($total === 0) {
            $this->info('location_name: already populated, skipping.');

            return;
        }

        $this->info("Backfilling location_name for {$total} events...");
        $bar = $this->output->createProgressBar($total);

        // Write (id → city) into a temp table, then do a single UPDATE JOIN.
        DB::statement('CREATE TEMPORARY TABLE _geo_update (event_id CHAR(36), location_name VARCHAR(255))');

        $batch = [];
        $batchSize = 2000;
        $now = now()->toDateTimeString();

        DB::table('events')
            ->whereNull('location_name')
            ->select('id', 'latitude', 'longitude')
            ->orderBy('id')
            ->chunk($batchSize, function ($rows) use ($geo, &$batch, $batchSize, $bar) {
                foreach ($rows as $row) {
                    $batch[] = [
                        'event_id' => $row->id,
                        'location_name' => $geo->nearestCity((float) $row->latitude, (float) $row->longitude),
                    ];
                }

                if (count($batch) >= $batchSize) {
                    DB::table('_geo_update')->insert($batch);
                    $batch = [];
                }

                $bar->advance(count($rows));
            });

        if ($batch) {
            DB::table('_geo_update')->insert($batch);
        }

        DB::statement('UPDATE events e JOIN _geo_update g ON e.id = g.event_id SET e.location_name = g.location_name');
        DB::statement('DROP TEMPORARY TABLE _geo_update');

        $bar->finish();
        $this->newLine();
        $this->info('location_name backfill done.');
    }

    private function backfillImages(): void
    {
        $withoutImages = DB::table('events')
            ->leftJoin('event_images', 'events.id', '=', 'event_images.event_id')
            ->whereNull('event_images.event_id')
            ->count('events.id');

        if ($withoutImages === 0) {
            $this->info('Images: already populated, skipping.');

            return;
        }

        $this->info("Adding placeholder images for {$withoutImages} events...");
        $bar = $this->output->createProgressBar($withoutImages);
        $now = now()->toDateTimeString();

        DB::table('events')
            ->leftJoin('event_images', 'events.id', '=', 'event_images.event_id')
            ->whereNull('event_images.event_id')
            ->select('events.id')
            ->orderBy('events.id')
            ->chunk(2000, function ($rows) use ($bar, $now) {
                $images = [];
                foreach ($rows as $row) {
                    $images[] = ['event_id' => $row->id, 'path' => self::PLACEHOLDER_IMAGES[mt_rand(0, 5)], 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now];
                    $images[] = ['event_id' => $row->id, 'path' => self::PLACEHOLDER_IMAGES[mt_rand(0, 5)], 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now];
                }
                DB::table('event_images')->insert($images);
                $bar->advance(count($rows));
            });

        $bar->finish();
        $this->newLine();
        $this->info('Image backfill done.');
    }
}
