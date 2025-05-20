<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use App\Notifications\EventReminderNotification;

class SendEventReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-event-reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends notifications to users about upcoming events';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $events = Event::whereBetween('start_date', [now(), now()->addHours(24)])
            ->with('attendees')
            ->get();

        $this->info("Sending notifications for " . $events->count() . " upcoming events...");

        foreach ($events as $event) {
            $this->info("Notification for event " . $event->name);
            foreach ($event->attendees as $attendee) {
                $this->info("Notifiying: " . $attendee->email);

                $attendee->notify(new EventReminderNotification($event));
            }
        }
    }
}
