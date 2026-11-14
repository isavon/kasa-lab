<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Payment;
use App\Enum\Currency;
use App\Enum\PaymentStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class PaymentTest extends TestCase
{
    public function testAmountAndCurrency(): void
    {
        $amount = 1250;
        $currency = Currency::UAH;

        $payment = new Payment(amount: $amount, currency: $currency);

        $this->assertSame($amount, $payment->getAmount());
        $this->assertSame($currency, $payment->getCurrency());
    }

    public function testPaymentHasPendingStatus(): void
    {
        $payment = new Payment(1250, Currency::UAH);
        $this->assertSame(PaymentStatus::PENDING, $payment->getStatus());
    }

    public function testValidUuid(): void
    {
        $payment = new Payment(1250, Currency::UAH);
        $this->assertInstanceOf(Uuid::class, $payment->getId());
    }

    public function testTimestampsAreSetAndEqualOnCreation(): void
    {
        $beforeCreation = new \DateTimeImmutable();
        $payment = new Payment(1250, Currency::UAH);
        $afterCreation = new \DateTimeImmutable();

        $this->assertEquals($payment->getCreatedAt(), $payment->getUpdatedAt());

        $this->assertGreaterThanOrEqual($beforeCreation, $payment->getCreatedAt());
        $this->assertGreaterThanOrEqual($beforeCreation, $payment->getUpdatedAt());

        $this->assertLessThanOrEqual($afterCreation, $payment->getCreatedAt());
        $this->assertLessThanOrEqual($afterCreation, $payment->getUpdatedAt());
    }

    public function testTimestampsAreInUtc(): void
    {
        $payment = new Payment(1250, Currency::UAH);

        $createdAtTz = $payment->getCreatedAt()->getTimezone()->getName();
        $updatedAtTz = $payment->getUpdatedAt()->getTimezone()->getName();

        $this->assertSame('UTC', $createdAtTz);
        $this->assertSame('UTC', $updatedAtTz);
    }

    public static function amounts(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1250],
        ];
    }

    #[DataProvider('amounts')]
    public function testCreationWithZeroOrNegativeAmount(int $amount): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Payment($amount, Currency::UAH);
    }

    public function testMinimumValidAmount(): void
    {
        $payment = new Payment(1, Currency::UAH);
        $this->assertSame(1, $payment->getAmount());
    }
}
