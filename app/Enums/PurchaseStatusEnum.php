<?php

namespace App\Enums;

enum PurchaseStatusEnum: string
{
    case Reserved = 'reserved';
    case DepositPending = 'deposit_pending';
    case DepositPaid = 'deposit_paid';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
