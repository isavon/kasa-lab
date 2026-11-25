<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Payment;
use App\Enum\Currency;
use App\Enum\PaymentStatus;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

class PaymentControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    private PaymentService $paymentService;

    private EntityRepository $paymentRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->paymentService = static::getContainer()->get(PaymentService::class);
        $this->paymentRepository = $this->entityManager->getRepository(Payment::class);
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

    private function assertPaymentsCount(int $expectedCount): void
    {
        $this->entityManager->clear();
        $this->assertCount($expectedCount, $this->paymentRepository->findAll());
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
    public function testCreatesPayment(int $amount, Currency $currency): void
    {
        $payload = [
            'amount' => $amount,
            'currency' => $currency->value,
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
        $this->assertSame($currency->value, $data['currency']);
        $this->assertSame(PaymentStatus::PENDING->value, $data['status']);
        $this->assertResponseHeaderSame('Location', "/payments/{$data['id']}");
        $this->assertArrayHasKey('createdAt', $data);
        $this->assertNotFalse(\DateTimeImmutable::createFromFormat(\DateTimeImmutable::ATOM, $data['createdAt']));

        $this->assertPaymentsCount(1);

        $payment = $this->paymentRepository->find($data['id']);
        $this->assertSame($data['id'], (string) $payment->getId());
        $this->assertSame($amount, $payment->getAmount());
        $this->assertSame($data['amount'], $payment->getAmount());
        $this->assertSame($currency, $payment->getCurrency());
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

        $this->assertPaymentsCount(0);
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

        $this->assertPaymentsCount(0);
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

        $this->assertPaymentsCount(1);
    }

    public function testInvariants(): void
    {
        $this->postJson(['amount' => 0, 'currency' => 'UAH']);
        $this->postJson(['amount' => -1250, 'currency' => 'UAH']);
        $this->postJson(['amount' => '1250', 'currency' => 'UAH']);
        $this->postJson(['amount' => 12.5, 'currency' => 'UAH']);

        $this->assertPaymentsCount(0);
    }

    private function getPayment(string $id): Response
    {
        $this->client->request(
            method: 'GET',
            uri: "/payments/{$id}",
        );

        return $this->client->getResponse();
    }

    #[DataProvider('amounts')]
    public function testGetPaymentSuccessfully(int $amount, Currency $currency): void
    {
        $payment = $this->paymentService->create($amount, $currency);
        $this->assertInstanceOf(Payment::class, $payment);

        $this->entityManager->clear();

        $getPayment = $this->getPayment((string) $payment->getId());

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $content = $getPayment->getContent();

        $this->assertJson($content);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame((string) $payment->getId(), $data['id']);
        $this->assertSame($amount, $data['amount']);
        $this->assertSame($currency->value, $data['currency']);
        $this->assertSame(PaymentStatus::PENDING->value, $data['status']);
        $this->assertArrayHasKey('createdAt', $data);
        $this->assertNotFalse(\DateTimeImmutable::createFromFormat(\DateTimeImmutable::ATOM, $data['createdAt']));
        $this->assertSame($data['createdAt'], $payment->getCreatedAt()->format(\DateTimeInterface::ATOM));
        $this->assertEqualsCanonicalizing(['id', 'amount', 'currency', 'status', 'createdAt'], array_keys($data));
    }

    public function testContract(): void
    {
        $amount = 1250;
        $currency = Currency::UAH;

        $response = $this->postJson([
            'amount' => $amount,
            'currency' => $currency->value,
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertResponseHasHeader('Location');

        $contentPost = $response->getContent();
        $this->assertJson($contentPost);

        $dataPost = json_decode($contentPost, true, 512, JSON_THROW_ON_ERROR);

        $this->client->request('GET', $response->headers->get('Location'));
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $contentGet = $this->client->getResponse()->getContent();

        $this->assertJson($contentGet);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $dataGet = json_decode($contentGet, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($dataPost, $dataGet);
    }

    public static function invalidUuidsProvider(): array
    {
        return [
            'not exists uuid' => [(string) Uuid::v7()],
            'not a uuid' => ['abc'],
            'digits only' => ['123'],
            'uuid with extra char' => [(string) Uuid::v7().'d'],
            'uuid without hyphens' => [str_replace('-', '', (string) Uuid::v7())],
            'uuid long' => ['sadfasdfasdfasdfasdfasdfasdfasdfsfasdfasdfasdfasdfasdfasdfasdfsdfafsdfasd'],
        ];
    }

    #[DataProvider('invalidUuidsProvider')]
    public function testGetPaymentValidationErrors(string $id): void
    {
        $response = $this->getPayment($id);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $content = $response->getContent();
        $this->assertJson($content);

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('detail', $data);
    }

    public function testPostPaymentByIdNotAllowed(): void
    {
        $id = (string) Uuid::v7();
        $this->client->request('POST', "/payments/{$id}");
        $this->assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
    }

    public function testGetPaymentWithUpperId(): void
    {
        $payment = $this->paymentService->create(1250, Currency::USD);
        $this->assertInstanceOf(Payment::class, $payment);

        $this->entityManager->clear();

        $response = $this->getPayment(strtoupper((string) $payment->getId()));
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $content = $response->getContent();
        $this->assertJson($content);

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame((string) $payment->getId(), $data['id']);
    }
}
