<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\Currency;
use App\Service\MoneyFormatter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class DemoMoneyController extends AbstractController
{
    #[Route('/demo/money/{amount<\d+>}', methods: ['GET'])]
    public function getMoney(int $amount, MoneyFormatter $formatter, Request $request): JsonResponse
    {
        try {
            $rawCurrency = $request->query->get('currency');
        } catch (BadRequestException) {
            return $this->badRequest('Bad request');
        }

        if (null === $rawCurrency) {
            return $this->badRequest('Currency is required');
        }

        $currency = Currency::tryFrom($rawCurrency);
        if (null === $currency) {
            return $this->badRequest('The provided currency is not supported');
        }

        return $this->json([
            'amount' => $amount,
            'currency' => $currency->value,
            'formatted' => $formatter->format($amount, $currency),
        ]);
    }

    private function badRequest(string $message): JsonResponse
    {
        return $this->json(['message' => $message], 400);
    }
}
