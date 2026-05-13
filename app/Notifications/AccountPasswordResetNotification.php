<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountPasswordResetNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $context,
        private readonly string $resetUrl,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isAdmin = $this->context === 'admin';
        $audience = $isAdmin ? 'garage' : 'client';

        return (new MailMessage)
            ->from((string) config('mail.from.address'), (string) config('mail.from.name'))
            ->subject('Reinitialisez votre mot de passe - MBPRESTIGE')
            ->greeting('Bonjour,')
            ->line("Une demande de reinitialisation de mot de passe a ete faite pour votre espace {$audience}.")
            ->line('Cliquez sur le bouton ci-dessous pour definir un nouveau mot de passe en toute securite.')
            ->action('Reinitialiser mon mot de passe', $this->resetUrl)
            ->line('Si vous n etes pas a l origine de cette demande, vous pouvez ignorer cet email.')
            ->line('Ce lien expire automatiquement apres 60 minutes.');
    }
}

