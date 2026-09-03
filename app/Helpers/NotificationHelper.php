<?php

namespace App\Helpers;

use App\Models\SystemNotification;
use App\Models\User;
use App\Notifications\SystemPushNotification;

class NotificationHelper
{
    public static function send(
        int $userId,
        string $title,
        string $message,
        string $url = '/'
    ): void {
        $user = User::find($userId);

        if (!$user) {
            return;
        }

        SystemNotification::create([
            'user_id' => $user->id,
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'is_read' => false,
        ]);

        $user->notify(
            new SystemPushNotification(
                title: $title,
                message: $message,
                url: $url
            )
        );
    }

    public static function sendToAdmins(
        string $title,
        string $message,
        string $url = '/'
    ): void {
        User::query()
            ->where('role', 'admin')
            ->get(['id'])
            ->each(function ($admin) use ($title, $message, $url) {
                self::send(
                    userId: $admin->id,
                    title: $title,
                    message: $message,
                    url: $url
                );
            });
    }
}