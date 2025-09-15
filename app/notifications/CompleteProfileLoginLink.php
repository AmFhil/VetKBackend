<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompleteProfileLoginLink extends Notification
{
    use Queueable;

    protected $loginUrl;

    /**
     * Create a new notification instance.
     */
    public function __construct($loginUrl)
    {
        //
        $this->loginUrl = $loginUrl;
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
            ->subject('Complete Your Profile')
            ->greeting("Hello " . $notifiable->email)
            ->line('Please complete your profile by clicking the link below')
            ->action('Verify Link', $this->loginUrl)
            ->line('If you did not create an account, no further action is required.')
            ->line('Thank you for using our application!')
            ->line('VetKonect Team');
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
