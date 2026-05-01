<?php

namespace App\Enums;

enum SupportTicketStatusEnum: string
{
    case Open = 'open';
    case PendingAdmin = 'pending_admin';
    case PendingCustomer = 'pending_customer';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Ouvert',
            self::PendingAdmin => 'En attente admin',
            self::PendingCustomer => 'En attente client',
            self::Resolved => 'Résolu',
            self::Closed => 'Clos',
        };
    }
}
