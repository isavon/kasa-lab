<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\Uid\Uuid;

class PaymentNotFoundException extends \RuntimeException
{
    public static function forId(Uuid $id): self
    {
        return new self(sprintf('Payment "%s" not found.', (string) $id));
    }
}
