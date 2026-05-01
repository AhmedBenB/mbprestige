<?php

namespace App\Enums;

enum BidStatusEnum: string
{
    case Pending = 'pending';
    case Leading = 'leading';
    case Outbid = 'outbid';
    case WonPendingValidation = 'won_pending_validation';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match($this) {
            self::Pending => 'En attente',
            self::Leading => 'Meilleure offre',
            self::Outbid => 'Surenchéri',
            self::WonPendingValidation => 'Gagnant - validation en cours',
            self::Accepted => 'Acceptée',
            self::Rejected => 'Refusée',
            self::Cancelled => 'Annulée',
            self::Expired => 'Expirée',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Leading, self::WonPendingValidation]);
    }
}
