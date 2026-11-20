<?php

declare(strict_types=1);

namespace App\Enum;

enum Currency: string
{
    case UAH = 'UAH';
    case USD = 'USD';
    case EUR = 'EUR';

    public function maxAmount(): int
    {
        return match ($this) {
            self::UAH => 9999999999,
            self::USD => 999999999,
            self::EUR => 99999999,
        };
    }
}
