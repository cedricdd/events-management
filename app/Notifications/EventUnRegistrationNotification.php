<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class EventUnRegistrationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Event $event, public string $source = 'user')
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
        $mail = (new MailMessage)
            ->subject('Unregistered from an event.')
            ->greeting("Hello *{$notifiable->name}*!")
            ->line("We are confirming that you have just unregistered from the event: *" . $this->event->name . "*.")
            ->line("You have been refunded **" . $this->event->cost . "** tokens.");
        
        if($this->source == 'organizer') {
            $mail->line("You have been removed from the event by *{$this->event->organizer->name}* (ID: {$this->event->organizer->id}), the event's organizer, please contact them for more info.");
        } elseif($this->source == 'admin') {
            $mail->line("You have been removed from the event by a member of our team, please contact us for more info.");
        } 

        return $mail->line('Thank you for using our application!');
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
