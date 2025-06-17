<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\EventInviteDeletionEmail;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendEventInviteDeletionEmail implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $eventID, public int $userID)
    {
        //
    }

    // If the event is moddified multiple times before the job is processed, we want to ensure that user are only notified once
    public function uniqueId(): string
    {
        return "event-invite-deletion-{$this->eventID}-{$this->userID}";
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $event = Event::find($this->eventID);
        $user = User::find($this->userID);

        if ($event && $user) {
            Mail::to($user)->send(
                new EventInviteDeletionEmail($event, $user)
            );
        } else {
            Log::error("SendEventInviteEmail -- Event or User not found for ID: {$this->eventID} or {$this->userID}");
        }
    }
}
