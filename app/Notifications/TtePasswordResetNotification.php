<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Reset admin/verifikator: tidak diantrekan agar kegagalan SMTP diketahui segera. */
class TtePasswordResetNotification extends Notification {
    public function __construct(public readonly string $token) {}

    public function via(object $notifiable): array {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage {
        // The canonical APP_URL prevents Host-header based reset-link poisoning.
        $path = route(
            'password.reset',
            [
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ],
            false,
        );
        $url = rtrim((string) config('app.url'), '/') . $path;
        $minutes = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage())
            ->subject('Atur Ulang Password - TTE Ticketing')
            ->greeting('Halo, ' . $notifiable->name)
            ->line('Kami menerima permintaan pengaturan ulang password akun TTE Ticketing Anda.')
            ->action('Atur Ulang Password', $url)
            ->line(
                'Tautan berlaku selama ' . $minutes . ' menit dan hanya dapat digunakan satu kali.',
            )
            ->line('Apabila Anda tidak meminta reset password, abaikan email ini.')
            ->line('Jangan bagikan tautan reset atau kode rahasia kepada siapa pun.');
    }
}
