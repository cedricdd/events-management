<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class EventDeletionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public array $event, public User $userDeleting) {}

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
            ->subject('Event Deleted')
            ->greeting("Hello {$notifiable->name}!")
            ->line("An event you were registered for has been deleted. You will no longer be able to attend this event.")
            ->line("You have been refunded " . $this->event['cost'] . " tokens.")
            ->line("This event was scheduled to start " . \Carbon\Carbon::parse($this->event['start_date'])->diffForHumans() . " ({$this->event['start_date']}), it's location would have been: {$this->event['location']}.")
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [$this->event];
    }
}
