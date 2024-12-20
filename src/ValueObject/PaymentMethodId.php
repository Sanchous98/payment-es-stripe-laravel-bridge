<?php

namespace PaymentSystem\Laravel\Stripe\ValueObject;

use EventSauce\EventSourcing\AggregateRootId;
use Stringable;

readonly class PaymentMethodId implements AggregateRootId, Stringable
{
    public function __construct(private string $paymentMethodId)
    {
        assert(str_starts_with($this->paymentMethodId, 'pm_'));
    }

    public function toString(): string
    {
        return $this->paymentMethodId;
    }

    public static function fromString(string $aggregateRootId): static
    {
        return new static($aggregateRootId);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}