<?php

namespace App\Mail;

use App\Models\Attendee;
use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventReminder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Attendee $attendee,
        public readonly Event $event,
        public readonly string $timeframe, // '3 days' | '24 hours'
    ) {}

    public function envelope(): Envelope
    {
        $name = $this->event->payload['name'] ?? 'your event';

        return new Envelope(subject: "Reminder: {$this->timeframe} until {$name}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.event-reminder');
    }
}
