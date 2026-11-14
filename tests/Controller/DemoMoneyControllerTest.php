<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DemoMoneyControllerTest extends WebTestCase
{
    public static function amounts(): array
    {
        return [
            'UAH' => [1250, 'UAH', '12.50 UAH'],
            'USD' => [1250, 'USD', '12.50 USD'],
            'EUR' => [1250, 'EUR', '12.50 EUR'],
        ];
    }

    #[DataProvider('amounts')]
    public function testValidValues(int $amount, string $currency, string $formatted): void
    {
        $url = sprintf('/demo/money/%d?currency=%s', $amount, $currency);

        $client = static::createClient();
        $client->request('GET', $url);

        $this->assertResponseStatusCodeSame(200);

        $content = $client->getResponse()->getContent();

        $this->assertJson($content);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($amount, $data['amount']);
        $this->assertSame($currency, $data['currency']);
        $this->assertSame($formatted, $data['formatted']);
    }

    public static function urls(): array
    {
        return [
            'missing currency' => ['/demo/money/1250', 'Currency is required'],
            'unknown currency code' => ['/demo/money/1250?currency=INVALID', 'The provided currency is not supported'],
            'lowercase currency' => ['/demo/money/1250?currency=usd', 'The provided currency is not supported'],
            'array currency' => ['/demo/money/1250?currency[]=UAH', 'Bad request'],
            'empty currency' => ['/demo/money/1250?currency=', 'The provided currency is not supported'],
        ];
    }

    #[DataProvider('urls')]
    public function testBadRequest(string $url, string $expectedMessage): void
    {
        $client = static::createClient();
        $client->request('GET', $url);
        $this->assertResponseStatusCodeSame(400);

        $content = $client->getResponse()->getContent();
        $this->assertJson($content);

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('message', $data);
        $this->assertSame($expectedMessage, $data['message']);
    }

    public function testNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/demo/money/abc');
        $this->assertResponseStatusCodeSame(404);
    }
}
