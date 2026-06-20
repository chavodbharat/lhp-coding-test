<?php

namespace App\Console\Commands;

use App\Mail\EventReminder;
use App\Models\Attendee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendEventReminders extends Command
{
    protected $signature = 'reminders:send
                            {--window=3day : Which window to send — "3day" or "24h"}
                            {--dry-run    : Print who would be emailed without actually sending}';

    protected $description = 'Send event reminder emails to registered attendees (3-day and 24-hour windows)';

    public function handle(): int
    {
        $window  = $this->option('window');
        $dryRun  = $this->option('dry-run');

        if (! in_array($window, ['3day', '24h'], true)) {
            $this->error("Unknown window \"{$window}\". Use --window=3day or --window=24h.");
            return self::FAILURE;
        }

        [$from, $to, $label] = match ($window) {
            '3day' => [
                now()->addDays(3)->startOfDay()->timestamp,
                now()->addDays(3)->endOfDay()->timestamp,
                '3 days',
            ],
            '24h' => [
                now()->addHours(24)->subMinutes(30)->timestamp,
                now()->addHours(24)->addMinutes(30)->timestamp,
                '24 hours',
            ],
        };

        $sent = 0;

        Attendee::with('event')
            ->whereHas('event', fn ($q) => $q
                ->whereRaw(
                    'CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.schedule.starts_at")) AS UNSIGNED) BETWEEN ? AND ?',
                    [$from, $to]
                )
                ->where('status', 'published')
            )
            ->chunk(200, function ($attendees) use ($label, $dryRun, &$sent) {
                foreach ($attendees as $attendee) {
                    if ($dryRun) {
                        $this->line("  [dry-run] Would email {$attendee->email} — {$attendee->event->payload['name']}");
                    } else {
                        Mail::to($attendee->email)->queue(new EventReminder($attendee, $attendee->event, $label));
                    }
                    $sent++;
                }
            });

        $action = $dryRun ? 'Would send' : 'Queued';
        $this->info("{$action} {$sent} reminder(s) for the {$label} window.");

        return self::SUCCESS;
    }

}
