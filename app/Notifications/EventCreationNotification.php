<?php

namespace App\Notifications;

use Carbon\Carbon;
use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class EventCreationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Event $event)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Event Created Successfully')
            ->greeting("Hello {$notifiable->name}!")
            ->line("The event '{$this->event->name}' was successfully created and is now visible to everybody.")
            ->line('Event Page: ' . route('events.show', $this->event->id))
            ->line("The event is scheduled to start " . Carbon::parse($this->event->start_date)->diffForHumans() . " ({$this->event->start_date}), it's location will be: {$this->event->location}.")
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            $this->event->toArray(),
        ];
    }
}
