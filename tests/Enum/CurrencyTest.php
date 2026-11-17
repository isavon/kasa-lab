<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\Currency;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CurrencyTest extends TestCase
{
    public static function maxAmounts(): array
    {
        return [
            'uah' => [9999999999, Currency::UAH],
            'usd' => [999999999, Currency::USD],
            'eur' => [99999999, Currency::EUR],
        ];
    }

    #[DataProvider('maxAmounts')]
    public function testMaxAmount(int $maxAmount, Currency $currency): void
    {
        $this->assertSame($maxAmount, $currency->maxAmount());
    }
}
