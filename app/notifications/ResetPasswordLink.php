<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordLink extends Notification implements ShouldQueue
{
    use Queueable;

    protected $resetUrl;
    protected $name;
    protected $email;

    /**
     * Create a new notification instance.
     */
    public function __construct($resetUrl, $name, $email)
    {
        //
        $this->resetUrl = $resetUrl;
        $this->name = $name;
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
            ->subject('Reset Your Password')
            ->greeting("Hello " . $this->name)
            ->line('You are receiving this email because we received a password reset request for your account')
            ->action('Reset Password', $this->resetUrl)
            ->line('If you did not request a password reset, no further action is required.')
            ->line('Thank you for using our application!')
            ->line('vetKonect Team');
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
            'resetUrl' => $this->resetUrl,
            'name' => $this->name,
            'email' => $this->email
        ];
    }
}
