<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Payment;
use App\Enum\Currency;
use Doctrine\ORM\EntityManagerInterface;

class PaymentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function create(int $amount, Currency $currency): Payment
    {
        $payment = new Payment($amount, $currency);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $payment;
    }
}
