<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrangThaiDonHangNotification extends Notification
{
    use Queueable;

    protected $message;
    protected $orderId;

    public function __construct($message, $orderId)
    {
        $this->message = $message;
        $this->orderId = $orderId;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'message' => $this->message,
            'order_id' => $this->orderId,
        ];
    }
}
