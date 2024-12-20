<?php

namespace PaymentSystem\Laravel\Stripe\ValueObject;

use EventSauce\EventSourcing\AggregateRootId;
use Stringable;

readonly class PaymentIntentId implements AggregateRootId, Stringable
{
    public function __construct(private string $paymentIntentId)
    {
        assert(str_starts_with($this->paymentIntentId, 'pi_'));
    }

    public function toString(): string
    {
        return $this->paymentIntentId;
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