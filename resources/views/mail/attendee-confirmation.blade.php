<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Confirmed</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f4f4f5; margin: 0; padding: 32px 16px; }
        .card { background: #fff; border-radius: 12px; max-width: 560px; margin: 0 auto; padding: 40px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .badge { display: inline-block; background: #f0fdf4; color: #16a34a; border-radius: 9999px; padding: 4px 14px; font-size: 13px; font-weight: 600; margin-bottom: 24px; }
        h1 { font-size: 22px; font-weight: 700; color: #18181b; margin: 0 0 8px; }
        p { color: #52525b; line-height: 1.6; margin: 0 0 16px; }
        .detail { background: #fafafa; border: 1px solid #e4e4e7; border-radius: 8px; padding: 16px; margin: 24px 0; }
        .detail p { margin: 4px 0; font-size: 14px; }
        .detail strong { color: #18181b; }
        .footer { margin-top: 32px; font-size: 13px; color: #a1a1aa; }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">✓ Confirmed</div>
        <h1>You're on the list, {{ $attendee->name }}!</h1>
        <p>Thanks for registering. We'll see you there.</p>

        <div class="detail">
            <p><strong>Event</strong><br>{{ $event->payload['name'] ?? '—' }}</p>
            @if(!empty($event->payload['schedule']['starts_at']))
                <p style="margin-top:12px"><strong>Date</strong><br>
                    {{ \Carbon\Carbon::createFromTimestamp($event->payload['schedule']['starts_at'])->toFormattedDateString() }}
                </p>
            @endif
            @if($event->location_name)
                <p style="margin-top:12px"><strong>Location</strong><br>{{ $event->location_name }}</p>
            @endif
            @if(!empty($event->payload['venue']['name']))
                <p style="margin-top:12px"><strong>Venue</strong><br>{{ $event->payload['venue']['name'] }}</p>
            @endif
        </div>

        <p>We'll send you a reminder 3 days before and again 24 hours before the event.</p>

        <div class="footer">
            <p>You're receiving this because you registered at {{ config('app.url') }}.</p>
        </div>
    </div>
</body>
</html>
