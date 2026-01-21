<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class HalalGoIdTokenExpired extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): DatabaseMessage
    {
        return new DatabaseMessage([
            'title' => 'Token Halal.go.id Kadaluarsa!',
            'body' => 'Token Bearer halal.go.id sudah tidak valid atau kadaluarsa. Silakan update token baru di halaman Konfigurasi SiHalal.',
            'status' => 'danger',
        ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Token Halal.go.id Kadaluarsa!',
            'body' => 'Token Bearer halal.go.id sudah tidak valid atau kadaluarsa. Silakan update token baru di halaman Konfigurasi SiHalal.',
            'status' => 'danger',
        ];
    }
}
