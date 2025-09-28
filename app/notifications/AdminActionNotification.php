<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

class AdminActionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $title;
    protected $detail;
    protected $imageUrl;

    /**
     * Create a new notification instance.
     */
    public function __construct($title, $detail, $imageUrl = null)
    {
        $this->title = $title;
        $this->detail = $detail;
        $this->imageUrl = $imageUrl;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting('Hello ' . ($notifiable->first_name ?? $notifiable->email))
            ->line($this->detail);
        if ($this->imageUrl) {
            $mail->line('<img src="' . $this->imageUrl . '" alt="Image" style="max-width:200px;">');
        }
        $mail->line('Thank you for using VetKonect!');
        return $mail;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'title' => $this->title,
            'detail' => $this->detail,
            'imageUrl' => $this->imageUrl,
        ];
    }
}
