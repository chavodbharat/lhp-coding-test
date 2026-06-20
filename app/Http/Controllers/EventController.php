<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    private const TYPES = ['concert', 'conference', 'meetup', 'workshop', 'festival', 'sports', 'networking', 'exhibition'];

    public function index(Request $request): Response
    {
        return Inertia::render('Events/Index', [
            'filters' => [
                'status' => $request->status,
                'from' => $request->input('from', '2023-01-01'),
            ],
            'statuses' => ['draft', 'published', 'cancelled', 'sold_out'],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        [$events, $stats] = $this->loadListing($request);

        return response()->json([
            'data' => $events->through(fn ($e) => $this->transformEvent($e))->items(),
            'current_page' => $events->currentPage(),
            'last_page' => $events->lastPage(),
            'total' => $events->total(),
            'stats' => $stats,
        ]);
    }

    public function show(Request $request, Event $event): Response
    {
        $event->load(['user', 'images']);

        $backUrls = [
            'visual1' => '/events-visual-1',
            'visual2' => '/events-visual-2',
        ];
        $backTo = $backUrls[$request->query('from')] ?? '/events';

        return Inertia::render('Events/Show', [
            'event' => $this->transformEvent($event),
            'attendeeCount' => $event->attendees()->count(),
            'backTo' => $backTo,
        ]);
    }

    public function visualOne(Request $request): Response
    {
        $featured = Event::with('images')
            ->where('status', 'published')
            ->orderByDesc('created_time')
            ->limit(5)
            ->get()
            ->map(fn ($e) => $this->transformEvent($e))
            ->values();

        return Inertia::render('Events/VisualOne', [
            'filters'   => array_merge(
                ['location' => 'Miami, US'],
                $request->only(['date_from', 'date_to', 'location', 'type', 'search'])
            ),
            'locations' => $this->locationOptions(),
            'types'     => self::TYPES,
            'featured'  => $featured,
        ]);
    }

    public function visualTwo(Request $request): Response
    {
        return Inertia::render('Events/VisualTwo', [
            'filters'   => array_merge(
                ['location' => 'Miami, US'],
                $request->only(['date_from', 'date_to', 'location', 'type', 'search'])
            ),
            'locations' => $this->locationOptions(),
            'types'     => self::TYPES,
        ]);
    }

    /**
     * @return array{0: LengthAwarePaginator, 1: array{ms: int, bytes: int}}
     */
    private function loadListing(Request $request): array
    {
        $start = microtime(true);
        $perPage = max(1, min((int) $request->input('per_page', 50), 100));

        $search = trim((string) $request->input('search', ''));

        $query = Event::with(['user', 'images'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->location, fn ($q, $l) => $q->where('location_name', $l))
            ->when($request->date_from, fn ($q, $d) => $q->where('created_time', '>=', strtotime($d)))
            ->when($request->date_to, fn ($q, $d) => $q->where('created_time', '<=', strtotime($d.' 23:59:59')));

        $sortAsc = $request->input('sort') === 'asc';

        if ($search) {
            // Two-step approach: FULLTEXT scan stops as soon as it hits the LIMIT,
            // so MySQL never ranks all 100K+ matches for common words like "food".
            // The outer query then filters/paginates a small known set via primary key.
            $matchingIds = DB::table('events')
                ->select('id')
                ->whereRaw('MATCH(event_name) AGAINST(? IN BOOLEAN MODE)', [$search])
                ->limit(1000)
                ->pluck('id');

            $query->whereIn('id', $matchingIds);
        }

        $sortAsc
            ? $query->orderBy('created_time')
            : $query->orderByDesc('created_time');

        $events = $query->paginate($perPage)->withQueryString();

        $stats = [
            'ms' => (int) round((microtime(true) - $start) * 1000),
            'bytes' => strlen((string) json_encode($events->items())),
        ];

        return [$events, $stats];
    }

    /** @return array<string, mixed> */
    private function transformEvent(Event $event): array
    {
        return [
            'id' => $event->id,
            'type' => $event->type,
            'status' => $event->status,
            'created_time' => $event->created_time,
            'location_name' => $event->location_name,
            'latitude' => $event->latitude,
            'longitude' => $event->longitude,
            'payload' => $event->payload,
            'images' => $event->images->map(fn ($img) => [
                'id' => $img->id,
                'url' => asset('storage/'.$img->path),
                'sort_order' => $img->sort_order,
            ])->values()->all(),
            'user' => $event->user ? ['id' => $event->user->id, 'name' => $event->user->name] : null,
        ];
    }

    /** @return string[] */
    private function locationOptions(): array
    {
        return Cache::remember('location_options', 3600, fn () => Event::select('location_name')
            ->distinct()
            ->whereNotNull('location_name')
            ->orderBy('location_name')
            ->pluck('location_name')
            ->all()
        );
    }
}
