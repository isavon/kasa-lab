<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Payment;
use App\Enum\Currency;
use App\Enum\PaymentStatus;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

class PaymentControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
    }

    private function postJson(array|string $body): Response
    {
        if (is_array($body)) {
            $body = json_encode($body, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        }

        $this->client->request(
            method: 'POST',
            uri: '/payments',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $body,
        );

        return $this->client->getResponse();
    }

    public static function amounts(): array
    {
        $data = [];

        foreach (Currency::cases() as $currency) {
            $data[$currency->value.' min'] = [1, $currency->value];
            $data[$currency->value.' typical'] = [1250, $currency->value];
            $data[$currency->value.' max'] = [$currency->maxAmount(), $currency->value];
        }

        return $data;
    }

    #[DataProvider('amounts')]
    public function testCreatesPayment(int $amount, string $currency): void
    {
        $payload = [
            'amount' => $amount,
            'currency' => $currency,
        ];
        $response = $this->postJson($payload);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $content = $response->getContent();
        $this->assertJson($content);

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('id', $data);
        $this->assertTrue(Uuid::isValid($data['id']));
        $this->assertSame($amount, $data['amount']);
        $this->assertSame($currency, $data['currency']);
        $this->assertSame(PaymentStatus::PENDING->value, $data['status']);
        $this->assertResponseHeaderSame('Location', "/payments/{$data['id']}");
        $this->assertArrayHasKey('createdAt', $data);
        $this->assertNotFalse(\DateTimeImmutable::createFromFormat(\DateTimeImmutable::ATOM, $data['createdAt']));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $paymentRepository = $entityManager->getRepository(Payment::class);

        $payments = $paymentRepository->findAll();
        $this->assertCount(1, $payments);

        $payment = $paymentRepository->find($data['id']);
        $this->assertSame($data['id'], (string) $payment->getId());
        $this->assertSame($amount, $payment->getAmount());
        $this->assertSame($data['amount'], $payment->getAmount());
        $this->assertSame($currency, $payment->getCurrency()->value);
        $this->assertSame($data['currency'], $payment->getCurrency()->value);
        $this->assertSame(PaymentStatus::PENDING, $payment->getStatus());
        $this->assertSame($data['status'], $payment->getStatus()->value);
        $this->assertSame($data['createdAt'], $payment->getCreatedAt()->format(\DateTimeInterface::ATOM));
    }

    public static function invalidAmounts(): array
    {
        return [
            'missing both fields currency' => [
                [], 'currency',
            ],
            'missing amount' => [
                ['currency' => 'UAH'], 'amount',
            ],
            'missing currency' => [
                ['amount' => 1250], 'currency',
            ],
            'zero amount' => [
                ['amount' => 0, 'currency' => 'UAH'], 'amount',
            ],
            'negative amount' => [
                ['amount' => -1250, 'currency' => 'UAH'], 'amount',
            ],
            'amount as string' => [
                ['amount' => '1250', 'currency' => 'UAH'], 'amount',
            ],
            'amount as fraction' => [
                ['amount' => 12.5, 'currency' => 'UAH'], 'amount',
            ],
            'amount as json fraction' => [
                ['amount' => 12.0, 'currency' => 'UAH'], 'amount',
            ],
            'amount as null' => [
                ['amount' => null, 'currency' => 'UAH'], 'amount',
            ],
            'amount as boolean' => [
                ['amount' => true, 'currency' => 'UAH'], 'amount',
            ],
            'amount as array' => [
                ['amount' => [], 'currency' => 'UAH'], 'amount',
            ],
            'big amount' => [
                ['amount' => 99999999999999999999, 'currency' => 'UAH'], 'amount',
            ],
            'amount max limit of uah' => [
                ['amount' => Currency::UAH->maxAmount() + 1, 'currency' => 'UAH'], 'amount',
            ],
            'amount max limit of usd' => [
                ['amount' => Currency::USD->maxAmount() + 1, 'currency' => 'USD'], 'amount',
            ],
            'amount max limit of eur' => [
                ['amount' => Currency::EUR->maxAmount() + 1, 'currency' => 'EUR'], 'amount',
            ],
            'currency invalid' => [
                ['amount' => 1250, 'currency' => 'INVALID'], 'currency',
            ],
            'currency lowercase' => [
                ['amount' => 1250, 'currency' => 'uah'], 'currency',
            ],
            'currency is empty' => [
                ['amount' => 1250, 'currency' => ''], 'currency',
            ],
            'currency as null' => [
                ['amount' => 1250, 'currency' => null], 'currency',
            ],
            'currency as number' => [
                ['amount' => 1250, 'currency' => 123], 'currency',
            ],
            'currency as array' => [
                ['amount' => 1250, 'currency' => []], 'currency',
            ],
            'empty json' => ['{}', 'currency'],
            'json is not object' => ['[]', 'currency'],
            'json as string' => ['"abc"', 'currency'],
            'json as number' => ['123', 'currency'],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function testValidationErrors(array|string $payload, string $expectedFieldName): void
    {
        $response = $this->postJson($payload);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $content = $response->getContent();
        $this->assertJson($content);

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('detail', $data);
        $this->assertStringContainsString($expectedFieldName, $data['detail']);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $paymentRepository = $entityManager->getRepository(Payment::class);

        $payments = $paymentRepository->findAll();
        $this->assertCount(0, $payments);
    }

    public static function badRequestProvider(): array
    {
        return [
            'broken json' => ['{"amount": 1250,'],
            'empty body' => [''],
        ];
    }

    #[DataProvider('badRequestProvider')]
    public function testBadRequest(string $payload): void
    {
        $response = $this->postJson($payload);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $content = $response->getContent();
        $this->assertJson($content);

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('detail', $data);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $paymentRepository = $entityManager->getRepository(Payment::class);

        $payments = $paymentRepository->findAll();
        $this->assertCount(0, $payments);
    }

    public function testExtraField(): void
    {
        $response = $this->postJson(['amount' => 1250, 'currency' => Currency::UAH->value, 'foo' => 1]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $content = $response->getContent();
        $this->assertJson($content);

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('id', $data);
        $this->assertTrue(Uuid::isValid($data['id']));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $paymentRepository = $entityManager->getRepository(Payment::class);

        $payments = $paymentRepository->findAll();
        $this->assertCount(1, $payments);
    }

    public function testInvariants(): void
    {
        $this->postJson(['amount' => 0, 'currency' => 'UAH']);
        $this->postJson(['amount' => -1250, 'currency' => 'UAH']);
        $this->postJson(['amount' => '1250', 'currency' => 'UAH']);
        $this->postJson(['amount' => 12.5, 'currency' => 'UAH']);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $paymentRepository = $entityManager->getRepository(Payment::class);

        $payments = $paymentRepository->findAll();
        $this->assertCount(0, $payments);
    }
}
