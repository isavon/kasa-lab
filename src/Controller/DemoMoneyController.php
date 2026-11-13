<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\Currency;
use App\Service\MoneyFormatter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class DemoMoneyController extends AbstractController
{
    #[Route('/demo/money/{amount<\d+>}', methods: ['GET'])]
    public function getMoney(int $amount, MoneyFormatter $formatter, Request $request): JsonResponse
    {
        $rawCurrency = $request->query->get('currency');
        if (null === $rawCurrency) {
            return $this->json(['message' => 'Bad request'], 400);
        }

        $currency = Currency::tryFrom($rawCurrency);
        if (null === $currency) {
            return $this->json(['message' => 'Bad request'], 400);
        }

        return $this->json([
            'amount' => $amount,
            'currency' => $currency,
            'formatted' => $formatter->format($amount, $currency),
        ]);
    }
}
