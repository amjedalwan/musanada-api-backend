<?php
// php artisan make:notification ApplicationStatusChanged

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ApplicationStatusChanged extends Notification
{
    use Queueable;

    protected $application;
    protected $title;
    protected $message;

    public function __construct($application, $title, $message)
    {
        $this->application = $application;
        $this->title = $title;
        $this->message = $message;
    }

    public function via($notifiable)
    {
        // سنخزن الإشعار في قاعدة البيانات ليجلبها تطبيق الفلاتر
        return ['database'];
    }

   
    public function toArray($notifiable)
    {
        return [
            'application_id'    => $this->application->id,
            'title'             => $this->title,
            'message'           => $this->message,
            'opportunity_title' => $this->application->opportunity->title,
            'status'            => $this->application->status,
            // إضافات جديدة لتحسين UI
            'type'              => $this->getNotificationType(), // 'success', 'info', 'warning'
            'sender_name'       => $this->application->opportunity->user->organization->org_name ?? 'منصة مساندة',
            'action_url'        => "/applications/{$this->application->id}", // رابط سريع للانتقال
        ];
    }

    private function getNotificationType()
    {
        return match ($this->application->status) {
            'accepted' => 'success',
            'rejected' => 'error',
            'pending'  => 'info',
            default    => 'info',
        };
    }
}
