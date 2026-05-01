<?php

namespace App\Enums;

enum ListingTypeEnum: string
{
    case AuctionOpen = 'auction_open';
    case AuctionBlind = 'auction_blind';
    case FixedPrice = 'fixed_price';
    case PartnerStock = 'partner_stock';

    public function label(): string
    {
        return match($this) {
            self::AuctionOpen => 'Enchère ouverte',
            self::AuctionBlind => 'Enchère blind',
            self::FixedPrice => 'Prix fixe',
            self::PartnerStock => 'Stock partenaire',
        };
    }

    public function isAuction(): bool
    {
        return in_array($this, [self::AuctionOpen, self::AuctionBlind]);
    }
}
