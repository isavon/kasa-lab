<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\MoneyFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyFormatterTest extends TestCase
{
    public static function amounts(): array
    {
        return [
            [0, 'UAH', '0.00 UAH'],
            [5, 'UAH', '0.05 UAH'],
            [99, 'UAH', '0.99 UAH'],
            [100, 'UAH', '1.00 UAH'],
            [1250, 'UAH', '12.50 UAH'],
            [-1250, 'UAH', '-12.50 UAH'],
            [-5, 'UAH', '-0.05 UAH'],
            [-50, 'UAH', '-0.50 UAH'],
            [-150, 'UAH', '-1.50 UAH'],
            [PHP_INT_MAX, 'UAH', '92233720368547758.07 UAH'],
            [PHP_INT_MIN, 'UAH', '-92233720368547758.08 UAH'],
        ];
    }

    #[DataProvider('amounts')]
    public function testFormat(int $amount, string $currency, string $expected): void
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
