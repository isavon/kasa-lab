<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\MoneyFormatter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class DemoMoneyController extends AbstractController
{
    #[Route('/demo/money/{amount<\d+>}', methods: ['GET'])]
    public function getMoney(int $amount, MoneyFormatter $formatter): JsonResponse
    {
        $currency = 'UAH';

        return $this->json([
            'amount' => $amount,
            'currency' => $currency,
            'formatted' => $formatter->format($amount, $currency),
        ]);
    }
}
