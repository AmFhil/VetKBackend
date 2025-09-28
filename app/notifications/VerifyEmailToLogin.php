<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailToLogin extends Notification implements ShouldQueue
{
    use Queueable;

    protected $verifyUrl;

    /**
     * Create a new notification instance.
     */
    public function __construct($verifyUrl)
    {
        //
        $this->verifyUrl = $verifyUrl;
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
            ->subject('Please Verify Your Email')
            ->greeting("Hello " . $notifiable->email)
            ->line('Please verify your email and complete your profile by clicking the link below')
            ->action('Verify Link', $this->verifyUrl)
            ->line('If you did not make this request, no further action is required.')
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
