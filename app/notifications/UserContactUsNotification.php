<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserContactUsNotification extends Notification
{
    use Queueable, ShouldQueue;

    protected $email;
    protected $content;

    /**
     * Create a new notification instance.
     */
    public function __construct($email, $content)
    {
        //
        $this->email = $email;
        $this->content = $content;
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
            ->subject('Thank you for contacting Vetkonect')
            ->greeting('Hello!')
            ->line('we have received your message and will get back to you shortly.')
            ->line('Your Message: ' . $this->content)
            ->line('Thank you for reaching out to us!')
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
