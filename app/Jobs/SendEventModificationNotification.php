<?php

namespace App\Jobs;

use App\Models\Event;
use Illuminate\Support\Facades\Log;
use App\Mail\EventModificationEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class SendEventModificationNotification implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $eventID)
    {
        //
    }

    // If the event is moddified multiple times before the job is processed, we want to ensure that user are only notified once
    public function uniqueId(): string
    {
        return "event-modification-notification-{$this->eventID}";
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $event = Event::find($this->eventID);

        if (!$event) {
            Log::error("Event with ID {$this->eventID} not found for modification notification.");

            return;
        }

        // Send notification to all registered users
        foreach ($event->attendees as $user) {
            Mail::to($user)->send(
                new EventModificationEmail($event, $user)
            );
        }
    }
}
