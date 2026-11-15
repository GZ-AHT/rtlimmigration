<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** REQ §12.3 payment methods. */
enum PaymentMethodKind: string
{
    use HasOptions;

    case BankTransfer = 'bank_transfer';
    case Card = 'card';
    case Cash = 'cash';
    case MobileBanking = 'mobile_banking';
    case Gateway = 'gateway';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Bank transfer',
            self::Card => 'Debit / credit card',
            self::Cash => 'Cash',
            self::MobileBanking => 'Mobile financial service',
            self::Gateway => 'Online payment gateway',
            self::Manual => 'Manual entry',
        };
    }
}
