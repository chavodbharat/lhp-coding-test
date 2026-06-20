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

class AttendeeConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Attendee $attendee,
        public readonly Event $event,
    ) {}

    public function envelope(): Envelope
    {
        $name = $this->event->payload['name'] ?? 'the event';

        return new Envelope(subject: "You're registered: {$name}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.attendee-confirmation');
    }
}
