<?php

namespace App\Enums;

enum PaymentTypeEnum: string
{
    case Deposit = 'deposit';
    case Balance = 'balance';
    case Full = 'full';
}
