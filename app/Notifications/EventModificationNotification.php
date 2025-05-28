<?php

namespace App\Notifications;

use Carbon\Carbon;
use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class EventModificationNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Event $event) {}

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
            ->subject('Event Updated')
            ->greeting("Hello {$notifiable->name}!")
            ->line("You are register to attempt the event '{$this->event->name}', the organizer has modified the event.")
            ->line('Here are the new details:')
            ->line("*Name*: **{$this->event->name}**")
            ->line("*Description*: **{$this->event->description}**")
            ->line("*Start*: " . Carbon::parse($this->event->start_date)->diffForHumans())
            ->line("*End*: " . Carbon::parse($this->event->end_date)->diffForHumans())
            ->line("*Location*: **{$this->event->location}**")
            ->line("*Cost*: **{$this->event->cost}**")
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
