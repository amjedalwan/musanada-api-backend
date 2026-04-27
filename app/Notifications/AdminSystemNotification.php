<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AdminSystemNotification extends Notification
{
    use Queueable;

    protected $title;
    protected $message;
    protected $type;

    public function __construct($title, $message, $type = 'info')
    {
        $this->title = $title;
        $this->message = $message;
        $this->type = $type; // success, error, warning
    }

    public function via($notifiable)
    {
        return ['database']; // التخزين في جدول notifications لقراءته في React/Flutter
    }

    public function toArray($notifiable)
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'sender' => 'إدارة مساندة',
            'created_at' => now()->toDateTimeString(),
        ];
    }
}