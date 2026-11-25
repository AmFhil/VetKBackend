<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserNewsLetterNotification extends Notification
{
    use Queueable, ShouldQueue;
    protected $email;

    /**
     * Create a new notification instance.
     */
    public function __construct($email)
    {
        //
        $this->email = $email;
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
            ->subject('Thank you for subscribing to our Newsletter')
            ->greeting('Hello!')
            ->line('Thank you for subscribing to our newsletter with the email: ' . $this->email)
            ->line('we appreciate your interest and will keep you updated with our latest news and offers.')
            ->salutation('Best regards, Vetkonect Team');
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
