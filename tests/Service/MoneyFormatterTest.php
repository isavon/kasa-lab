<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\Currency;
use App\Service\MoneyFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyFormatterTest extends TestCase
{
    public static function amounts(): array
    {
        return [
            [0, Currency::UAH, '0.00 UAH'],
            [0, Currency::USD, '0.00 USD'],
            [0, Currency::EUR, '0.00 EUR'],
            [5, Currency::UAH, '0.05 UAH'],
            [99, Currency::UAH, '0.99 UAH'],
            [100, Currency::UAH, '1.00 UAH'],
            [1250, Currency::UAH, '12.50 UAH'],
            [-1250, Currency::UAH, '-12.50 UAH'],
            [-5, Currency::UAH, '-0.05 UAH'],
            [-50, Currency::UAH, '-0.50 UAH'],
            [-150, Currency::UAH, '-1.50 UAH'],
            [PHP_INT_MAX, Currency::UAH, '92233720368547758.07 UAH'],
            [PHP_INT_MIN, Currency::UAH, '-92233720368547758.08 UAH'],
        ];
    }

    #[DataProvider('amounts')]
    public function testFormat(int $amount, Currency $currency, string $expected): void
    {
        $formatter = new MoneyFormatter();

        $this->assertSame($expected, $formatter->format($amount, $currency));
    }

    public function testFormatRejectsNonIntegerAmount(): void
    {
        $this->expectException(\TypeError::class);

        $formatter = new MoneyFormatter();
        $formatter->format('-50.0051', Currency::UAH);
    }
}
