<?php

namespace App\Http\Controllers;

use App\Mail\AttendeeConfirmation;
use App\Models\Attendee;
use App\Models\Event;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AttendeeController extends Controller
{
    public function store(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'name'  => ['required', 'string', 'min:2', 'max:100', 'regex:/^[\pL\s\-\'\.]+$/u'],
            'email' => ['required', 'email:rfc,dns', 'max:255'],
        ]);

        try {
            $attendee = $event->attendees()->firstOrCreate(
                ['email' => $validated['email']],
                ['name'  => $validated['name']],
            );
        } catch (UniqueConstraintViolationException) {
            // Two concurrent requests slipped past the firstOrCreate SELECT at the same time.
            // The DB unique constraint blocked the second INSERT — treat it as already registered.
            return response()->json([
                'message'            => "You're already registered for this event.",
                'already_registered' => true,
            ]);
        }

        if ($attendee->wasRecentlyCreated) {
            Mail::to($attendee->email)->queue(new AttendeeConfirmation($attendee, $event));
        }

        return response()->json([
            'message' => $attendee->wasRecentlyCreated
                ? "You're registered! Check your email for confirmation."
                : "You're already registered for this event.",
            'already_registered' => ! $attendee->wasRecentlyCreated,
        ]);
    }
}
