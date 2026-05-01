<?php

namespace App\Enums;

enum AuctionStatusEnum: string
{
    case Scheduled = 'scheduled';
    case Live = 'live';
    case EndingSoon = 'ending_soon';
    case EndedWaitingValidation = 'ended_waiting_validation';
    case WinnerSelected = 'winner_selected';
    case NotAwarded = 'not_awarded';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Scheduled => 'Programmée',
            self::Live => 'En cours',
            self::EndingSoon => 'Se termine bientôt',
            self::EndedWaitingValidation => 'Terminée - validation en attente',
            self::WinnerSelected => 'Gagnant désigné',
            self::NotAwarded => 'Non attribuée',
            self::Cancelled => 'Annulée',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Live, self::EndingSoon]);
    }
}
