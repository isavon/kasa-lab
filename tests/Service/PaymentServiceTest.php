<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Payment;
use App\Enum\Currency;
use App\Enum\PaymentStatus;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PaymentServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private EntityRepository $repository;

    private PaymentService $paymentService;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
        $this->repository = $this->entityManager->getRepository(Payment::class);
        $this->paymentService = static::getContainer()->get(PaymentService::class);
    }

    public static function amounts(): array
    {
        return array_map(
            fn (Currency $currency) => [1250, $currency],
            Currency::cases()
        );
    }

    #[DataProvider('amounts')]
    public function testCreatePayment(int $amount, Currency $currency): void
    {
        $payment = $this->paymentService->create($amount, $currency);
        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame($amount, $payment->getAmount());
        $this->assertSame($currency, $payment->getCurrency());
        $this->assertSame(PaymentStatus::PENDING, $payment->getStatus());

        $this->entityManager->clear();

        $paymentDb = $this->repository->find($payment->getId());
        $this->assertNotNull($paymentDb);
        $this->assertNotSame($paymentDb, $payment);
        $this->assertSame((string) $payment->getId(), (string) $paymentDb->getId());
        $this->assertSame($amount, $paymentDb->getAmount());
        $this->assertSame($currency, $paymentDb->getCurrency());
        $this->assertSame(PaymentStatus::PENDING, $paymentDb->getStatus());
        $this->assertSame($payment->getCreatedAt()->format(\DateTimeInterface::ATOM), $paymentDb->getCreatedAt()->format(\DateTimeInterface::ATOM));
        $this->assertSame($payment->getUpdatedAt()->format(\DateTimeInterface::ATOM), $paymentDb->getUpdatedAt()->format(\DateTimeInterface::ATOM));
        $this->assertSame('UTC', $payment->getCreatedAt()->getTimezone()->getName());
        $this->assertSame('UTC', $paymentDb->getCreatedAt()->getTimezone()->getName());
        $this->assertSame('UTC', $payment->getUpdatedAt()->getTimezone()->getName());
        $this->assertSame('UTC', $paymentDb->getUpdatedAt()->getTimezone()->getName());

        $this->assertCount(1, $this->repository->findAll());
    }

    public function testDifferentCreatePayments(): void
    {
        $paymentFirst = $this->paymentService->create(1250, Currency::EUR);
        $paymentSecond = $this->paymentService->create(12, Currency::USD);
        $this->assertNotSame((string) $paymentFirst->getId(), (string) $paymentSecond->getId());

        $this->entityManager->clear();
        $this->assertCount(2, $this->repository->findAll());
    }

    public static function invalidArgumentsProvider(): array
    {
        return [
            'zero amount' => [0, Currency::UAH, 'The payment amount must be greater than zero.'],
            'negative amount' => [-1250, Currency::EUR, 'The payment amount must be greater than zero.'],
            'uah above max' => [Currency::UAH->maxAmount() + 1, Currency::UAH, 'The amount exceeds the maximum allowed limit for currency.'],
            'usd above max' => [Currency::USD->maxAmount() + 1, Currency::USD, 'The amount exceeds the maximum allowed limit for currency.'],
            'eur above max' => [Currency::EUR->maxAmount() + 1, Currency::EUR, 'The amount exceeds the maximum allowed limit for currency.'],
        ];
    }

    #[DataProvider('invalidArgumentsProvider')]
    public function testCreatePaymentsWithInvalidArguments(int $amount, Currency $currency, string $expectedMessage): void
    {
        try {
            $this->paymentService->create($amount, $currency);
            $this->fail('Expected InvalidArgumentException.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame($expectedMessage, $e->getMessage());
        }

        $this->entityManager->clear();
        $this->assertCount(0, $this->repository->findAll());
    }
}
