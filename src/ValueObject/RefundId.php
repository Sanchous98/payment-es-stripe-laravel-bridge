<?php

namespace PaymentSystem\Laravel\Stripe\ValueObject;

use EventSauce\EventSourcing\AggregateRootId;
use Stringable;

readonly class RefundId implements AggregateRootId, Stringable
{
    public function __construct(private string $refundId)
    {
        assert(str_starts_with($this->refundId, 're_'));
    }

    public function toString(): string
    {
        return $this->refundId;
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