<?php

namespace App\Notifications;

use Carbon\Carbon;
use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class EventInviteNotification extends Notification implements ShouldQueue
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
            ->subject('Event Invitation')
            ->greeting("Hello {$notifiable->name}!")
            ->line("You have been invited to attempt the private event '{$this->event->name}'.")
            ->line('Here are the new details:')
            ->line("*Name*: **{$this->event->name}**")
            ->line("*Description*: **{$this->event->description}**")
            ->line("*Start*: " . Carbon::parse($this->event->start_date)->diffForHumans() . " (on " . Carbon::parse($this->event->start_date)->format('Y-m-d H:i') . ")")
            ->line("*End*: " . Carbon::parse($this->event->end_date)->diffForHumans() . " (on " . Carbon::parse($this->event->end_date)->format('Y-m-d H:i') . ")")
            ->line("*Location*: **{$this->event->location}**")
            ->line("*Cost*: **{$this->event->cost}**")
            ->line("*Type*: **{$this->event->type->name}**")
            ->line("You can join a private event the same way you would join a public event as long as you have been invited by the organizer.")
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
