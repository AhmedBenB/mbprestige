<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class ClientVerifyEmailNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->from((string) config('mail.from.address'), (string) config('mail.from.name'))
            ->subject('Verifiez votre adresse email - MBPRESTIGE')
            ->greeting('Bienvenue sur MBPRESTIGE')
            ->line('Veuillez confirmer votre adresse email pour acceder a votre espace client.')
            ->action('Verifier mon email', $this->verificationUrl($notifiable))
            ->line('Si vous n\'avez pas cree de compte, ignorez simplement cet email.')
            ->line('Ce lien de verification expire automatiquement apres 60 minutes.');
    }

    protected function verificationUrl(object $notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }
}

