<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

class SystemPushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public string $url = '/'
    ) {
    }

    public function via($notifiable): array
    {
        return ['webpush'];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->message)
            ->icon('/img/icons/icon-192x192.png')
            ->badge('/img/icons/icon-192x192.png')
            ->data([
                'url' => $this->url,
                'title' => $this->title,
                'message' => $this->message,
            ]);
    }
}