<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Currency;
use App\Enum\PaymentStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
class Payment
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(type: Types::BIGINT)]
    private int $amount;

    #[ORM\Column(enumType: Currency::class, length: 3)]
    private Currency $currency;

    #[ORM\Column(enumType: PaymentStatus::class, length: 32)]
    private PaymentStatus $status;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(int $amount, Currency $currency)
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('The payment amount must be greater than zero.');
        }

        if ($amount > $currency->maxAmount()) {
            throw new \InvalidArgumentException('The amount exceeds the maximum allowed limit for currency.');
        }

        $this->id = Uuid::v7();
        $this->amount = $amount;
        $this->currency = $currency;
        $this->status = PaymentStatus::PENDING;

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
