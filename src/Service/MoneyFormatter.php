<?php

declare(strict_types=1);

namespace App\Service;

class MoneyFormatter
{
    public function format(int $amount, string $currency): string
    {
        $isNegative = $amount < 0;

        $units = intdiv($amount, 100);
        $cents = $amount % 100;

        return ($isNegative ? '-' : '').abs($units).'.'.sprintf('%02d', abs($cents)).' '.$currency;
    }
}
