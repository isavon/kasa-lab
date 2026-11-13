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
            [0, Currency::UAH, '0.00 '.Currency::UAH->value],
            [0, Currency::USD, '0.00 '.Currency::USD->value],
            [0, Currency::EUR, '0.00 '.Currency::EUR->value],
            [5, Currency::UAH, '0.05 '.Currency::UAH->value],
            [99, Currency::UAH, '0.99 '.Currency::UAH->value],
            [100, Currency::UAH, '1.00 '.Currency::UAH->value],
            [1250, Currency::UAH, '12.50 '.Currency::UAH->value],
            [-1250, Currency::UAH, '-12.50 '.Currency::UAH->value],
            [-5, Currency::UAH, '-0.05 '.Currency::UAH->value],
            [-50, Currency::UAH, '-0.50 '.Currency::UAH->value],
            [-150, Currency::UAH, '-1.50 '.Currency::UAH->value],
            [PHP_INT_MAX, Currency::UAH, '92233720368547758.07 '.Currency::UAH->value],
            [PHP_INT_MIN, Currency::UAH, '-92233720368547758.08 '.Currency::UAH->value],
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
        $formatter->format('-50.0051', 'UAH');
    }
}
