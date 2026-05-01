<?php

namespace App\Enums;

enum SupportTicketPriorityEnum: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Faible',
            self::Normal => 'Normale',
            self::High => 'Haute',
            self::Urgent => 'Urgente',
        };
    }
}
