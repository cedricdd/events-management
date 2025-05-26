<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class EventRegistrationNotification extends Notification implements ShouldQueue
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
            ->subject('Registered from an event.')
            ->greeting("Hello *{$notifiable->name}*!")
            ->line("We are confirming that you have just registered to the event *" . $this->event->name . "*.")
            ->line("You have been charged **" . $this->event->cost . "** tokens for this event.")
            ->line("This event is scheduled to start " . \Carbon\Carbon::parse($this->event->start_date)->diffForHumans() . " ({$this->event->start_date}), it's location will be: {$this->event->location}.")
            ->line("You have the possibility to unregistered from this event up until the start of the event.")
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
            //
        ];
    }
}
