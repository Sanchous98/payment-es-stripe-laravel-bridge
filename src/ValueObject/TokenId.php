<?php

namespace PaymentSystem\Laravel\Stripe\ValueObject;

use EventSauce\EventSourcing\AggregateRootId;
use Stringable;

readonly class TokenId implements AggregateRootId, Stringable
{
    public function __construct(private string $tokenId)
    {
        assert(str_starts_with($this->tokenId, 'tok_'));
    }

    public function toString(): string
    {
        return $this->tokenId;
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