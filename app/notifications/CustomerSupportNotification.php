<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerSupportNotification extends Notification
{
    use Queueable;

    protected $firstName;
    protected $lastName;
    protected $email;
    protected $category;
    protected $complain;

    /**
     * Create a new notification instance.
     */
    public function __construct($firstName, $lastName, $email, $category, $complain)
    {
        //
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->category = $category;
        $this->complain = $complain;
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
            ->subject('New Customer Support Complain Received')
            ->line('You have received a new customer support complain.')
            ->line('Name: ' . $this->firstName . ' ' . $this->lastName)
            ->line('Email: ' . $this->email)
            ->line('Category: ' . $this->category)
            ->line('Complain: ' . $this->complain)
            ->line('User is expecting a response soon. Thank you for using our application!');
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
