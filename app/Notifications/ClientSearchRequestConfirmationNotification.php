<?php

namespace App\Notifications;

use App\Models\CustomerSearch;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientSearchRequestConfirmationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly CustomerSearch $search,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $fullName = trim((string) $this->search->client_full_name);
        $vehicleLabel = trim(implode(' ', array_filter([
            $this->search->make,
            $this->search->model,
        ])));
        $dashboardUrl = rtrim((string) config('app.url', 'http://localhost'), '/')
            . '/dashboard_client_sourcing.html?search=' . $this->search->id;

        return (new MailMessage)
            ->from((string) config('mail.from.address'), (string) config('mail.from.name'))
            ->subject('Votre demande MBPRESTIGE est bien enregistree')
            ->greeting($fullName !== '' ? 'Bonjour ' . $fullName . ',' : 'Bonjour,')
            ->line('Nous avons bien recu votre demande de sourcing automobile.')
            ->line('Votre demande est centralisee par MBPRESTIGE et transmise en interne a notre reseau de garages selectionnes.')
            ->line('Recapitulatif de votre besoin :')
            ->line('Vehicule : ' . ($vehicleLabel !== '' ? $vehicleLabel : 'Modele libre'))
            ->line('Budget maximum : ' . number_format((float) $this->search->budget_max, 0, ',', ' ') . ' EUR')
            ->line('Annee minimum : ' . (string) $this->search->year_min)
            ->line('Carburant : ' . ($this->search->fuel ?: 'Tous'))
            ->line('Boite : ' . ($this->search->transmission ?: 'Toutes'))
            ->when(
                $this->search->mileage_max !== null,
                fn (MailMessage $message) => $message->line(
                    'Kilometrage maximum : ' . number_format((int) $this->search->mileage_max, 0, ',', ' ') . ' km'
                )
            )
            ->when(
                trim((string) $this->search->client_comment) !== '',
                fn (MailMessage $message) => $message->line('Commentaire : ' . trim((string) $this->search->client_comment))
            )
            ->action('Suivre ma demande', $dashboardUrl)
            ->line('Vous recevrez ensuite les retours utiles directement depuis votre espace client.');
    }
}

