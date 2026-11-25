<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\PaymentDto;
use App\Entity\Payment;
use App\Exception\PaymentNotFoundException;
use App\Service\PaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

class PaymentController extends AbstractController
{
    private function serializePayment(Payment $payment): array
    {
        return [
            'id' => $payment->getId(),
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrency()->value,
            'status' => $payment->getStatus()->value,
            'createdAt' => $payment->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    #[Route('/payments', methods: ['POST'], format: 'json')]
    public function create(
        #[MapRequestPayload(acceptFormat: 'json')] PaymentDto $paymentDto,
        PaymentService $service,
    ): JsonResponse {
        $payment = $service->create($paymentDto->amount, $paymentDto->currency);

        return $this->json($this->serializePayment($payment), Response::HTTP_CREATED, [
            'Location' => '/payments/'.$payment->getId(),
        ]);
    }

    #[Route('/payments/{id}', methods: ['GET'], format: 'json')]
    public function show(Uuid $id, PaymentService $service): JsonResponse
    {
        try {
            $payment = $service->get($id);
        } catch (PaymentNotFoundException $e) {
            throw new NotFoundHttpException(message: $e->getMessage(), previous: $e);
        }

        return $this->json($this->serializePayment($payment), Response::HTTP_OK);
    }
}
