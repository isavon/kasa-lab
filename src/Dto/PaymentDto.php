<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\Currency;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class PaymentDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Type('int')]
        #[Assert\Positive]
        public readonly mixed $amount,

        public readonly Currency $currency,
    ) {
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context, mixed $payload): void
    {
        if (!is_int($this->amount)) {
            return;
        }

        if ($this->amount > $this->currency->maxAmount()) {
            $context->buildViolation('The amount exceeds the maximum allowed limit for currency.')
                ->atPath('amount')
                ->addViolation();
        }
    }
}
