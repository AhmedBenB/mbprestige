<?php

namespace App\Notifications;

use App\Models\Bid;
use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OutbidNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Listing $listing,
        public readonly Bid $bid
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Vous avez été surenchéri – {$this->listing->title}")
            ->greeting("Bonjour {$notifiable->first_name},")
            ->line("Votre offre sur **{$this->listing->title}** a été dépassée.")
            ->line("Meilleure offre actuelle : **" . number_format($this->listing->current_bid, 0, ',', ' ') . " €**")
            ->action('Voir l\'annonce et surenchérir', route('vehicles.show', $this->listing))
            ->line("L'enchère se termine le " . $this->listing->ends_at?->format('d/m/Y à H:i'))
            ->salutation('L\'équipe AutoMoto B2B');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'       => 'outbid',
            'listing_id' => $this->listing->id,
            'title'      => "Surenchéri : {$this->listing->title}",
            'body'       => "Votre offre a été dépassée. Nouvelle meilleure offre : " . number_format($this->listing->current_bid, 0, ',', ' ') . ' €',
            'url'        => route('vehicles.show', $this->listing),
        ];
    }
}
