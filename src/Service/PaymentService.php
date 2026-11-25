<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Payment;
use App\Enum\Currency;
use App\Exception\PaymentNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

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

    public function get(Uuid $id): Payment
    {
        $payment = $this->entityManager->getRepository(Payment::class)->find($id);

        if (!$payment) {
            throw PaymentNotFoundException::forId($id);
        }

        return $payment;
    }
}
