<?php

namespace App\Enums;

enum PublicationStatusEnum: string
{
    case Draft = 'draft';
    case Imported = 'imported';
    case Enriched = 'enriched';
    case MediaProcessing = 'media_processing';
    case ReadyForReview = 'ready_for_review';
    case Approved = 'approved';
    case Published = 'published';
    case Reserved = 'reserved';
    case Paused = 'paused';
    case SoldPendingPayment = 'sold_pending_payment';
    case SoldPendingBalance = 'sold_pending_balance';
    case Paid = 'paid';
    case InDelivery = 'in_delivery';
    case Delivered = 'delivered';
    case Archived = 'archived';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match($this) {
            self::Draft => 'Brouillon',
            self::Imported => 'Importé',
            self::Enriched => 'Enrichi',
            self::MediaProcessing => 'Traitement médias',
            self::ReadyForReview => 'Prêt à valider',
            self::Approved => 'Approuvé',
            self::Published => 'Publié',
            self::Reserved => 'Réservé',
            self::Paused => 'En pause',
            self::SoldPendingPayment => 'Vendu (paiement attendu)',
            self::SoldPendingBalance => 'Vendu (solde attendu)',
            self::Paid => 'Payé',
            self::InDelivery => 'En livraison',
            self::Delivered => 'Livré',
            self::Archived => 'Archivé',
            self::Rejected => 'Rejeté',
        };
    }

    public function isPubliclyVisible(): bool
    {
        return in_array($this, [self::Published]);
    }

    public function color(): string
    {
        return match($this) {
            self::Published => 'green',
            self::Reserved => 'indigo',
            self::Approved => 'blue',
            self::ReadyForReview => 'yellow',
            self::Paused => 'orange',
            self::Archived, self::Rejected => 'red',
            default => 'gray',
        };
    }
}
