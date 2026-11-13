<?php

declare(strict_types=1);

namespace App\Enum;

enum Currency: string
{
    case UAH = 'UAH';
    case USD = 'USD';
    case EUR = 'EUR';
}
