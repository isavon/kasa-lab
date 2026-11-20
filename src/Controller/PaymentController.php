<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\PaymentDto;
use App\Service\PaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class PaymentController extends AbstractController
{
    #[Route('/payments', methods: ['POST'], format: 'json')]
    public function payments(
        #[MapRequestPayload(acceptFormat: 'json')] PaymentDto $paymentDto,
        PaymentService $service,
    ): JsonResponse {
        $payment = $service->create($paymentDto->amount, $paymentDto->currency);

        return $this->json([
            'id' => $payment->getId(),
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrency()->value,
            'status' => $payment->getStatus()->value,
            'createdAt' => $payment->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], Response::HTTP_CREATED, [
            'Location' => '/payments/'.$payment->getId(),
        ]);
    }
}
