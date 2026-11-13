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

        $data = json_decode($content, true);
        $this->assertSame($amount, $data['amount']);
        $this->assertSame($currency, $data['currency']);
        $this->assertSame($formatted, $data['formatted']);
    }

    public static function urls(): array
    {
        return [
            'missing currency' => ['/demo/money/1250'],
            'unknown currency code' => ['/demo/money/1250?currency=INVALID'],
            'lowercase currency' => ['/demo/money/1250?currency=usd'],
            'array currency' => ['/demo/money/1250?currency[]=UAH'],
            'empty currency' => ['/demo/money/1250?currency='],
        ];
    }

    #[DataProvider('urls')]
    public function testBadRequest(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/demo/money/abc');
        $this->assertResponseStatusCodeSame(404);
    }
}
