<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Payment;
use App\Enum\Currency;
use App\Enum\PaymentStatus;
use App\Exception\PaymentNotFoundException;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

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

    private function assertPaymentMatches(Payment $expected, Payment $actual): void
    {
        $this->assertInstanceOf(Payment::class, $expected);
        $this->assertNotSame($expected, $actual);
        $this->assertSame((string) $actual->getId(), (string) $expected->getId());
        $this->assertSame($actual->getAmount(), $expected->getAmount());
        $this->assertSame($actual->getCurrency(), $expected->getCurrency());
        $this->assertSame(PaymentStatus::PENDING, $expected->getStatus());
        $this->assertSame($actual->getCreatedAt()->format(\DateTimeInterface::ATOM), $expected->getCreatedAt()->format(\DateTimeInterface::ATOM));
        $this->assertSame($actual->getUpdatedAt()->format(\DateTimeInterface::ATOM), $expected->getUpdatedAt()->format(\DateTimeInterface::ATOM));
        $this->assertSame('UTC', $actual->getCreatedAt()->getTimezone()->getName());
        $this->assertSame('UTC', $expected->getCreatedAt()->getTimezone()->getName());
        $this->assertSame('UTC', $actual->getUpdatedAt()->getTimezone()->getName());
        $this->assertSame('UTC', $expected->getUpdatedAt()->getTimezone()->getName());
    }

    public static function amounts(): array
    {
        $data = [];

        foreach (Currency::cases() as $currency) {
            $data[$currency->value.' min'] = [1, $currency];
            $data[$currency->value.' typical'] = [1250, $currency];
            $data[$currency->value.' max'] = [$currency->maxAmount(), $currency];
        }

        return $data;
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
        $this->assertPaymentMatches($paymentDb, $payment);

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

    #[DataProvider('amounts')]
    public function testGetPayment(int $amount, Currency $currency): void
    {
        $payment = new Payment($amount, $currency);
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        $this->entityManager->clear();

        $paymentDb = $this->paymentService->get($payment->getId());
        $this->assertPaymentMatches($paymentDb, $payment);

        $this->assertCount(1, $this->repository->findAll());
    }

    public function testGetPaymentWhenMultipleExist(): void
    {
        $paymentFirst = new Payment(1250, Currency::UAH);
        $this->entityManager->persist($paymentFirst);
        $this->entityManager->flush();

        $paymentSecond = new Payment(3600, Currency::USD);
        $this->entityManager->persist($paymentSecond);
        $this->entityManager->flush();

        $this->entityManager->clear();

        $paymentDb = $this->paymentService->get($paymentSecond->getId());
        $this->assertPaymentMatches($paymentDb, $paymentSecond);

        $this->assertCount(2, $this->repository->findAll());
    }

    public function testUnknownIdWithEmptyDb(): void
    {
        $id = Uuid::v7();

        $this->expectException(PaymentNotFoundException::class);
        $this->expectExceptionMessageIs(sprintf('Payment "%s" not found.', (string) $id));

        $this->paymentService->get($id);
    }

    public function testUnknownIdWithNotEmptyDb(): void
    {
        $payment = new Payment(1250, Currency::UAH);
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        $id = Uuid::v7();

        $this->expectException(PaymentNotFoundException::class);
        $this->expectExceptionMessageIs(sprintf('Payment "%s" not found.', (string) $id));

        $this->paymentService->get($id);
    }

    public function testReadingChangesNothing(): void
    {
        $payment = new Payment(1250, Currency::UAH);
        $this->entityManager->persist($payment);
        $this->entityManager->flush();
        $this->entityManager->clear();
        $countRecords = count($this->repository->findAll());

        $this->paymentService->get($payment->getId());
        $this->entityManager->clear();

        $paymentByRepository = $this->repository->find($payment->getId());
        $this->assertSame($paymentByRepository->getUpdatedAt()->format(\DateTimeInterface::ATOM), $payment->getUpdatedAt()->format(\DateTimeInterface::ATOM));
        $this->assertCount($countRecords, $this->repository->findAll());
    }
}
