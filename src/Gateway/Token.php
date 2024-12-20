<?php

namespace PaymentSystem\Laravel\Stripe\Gateway;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\Gateway\Resources\TokenInterface;
use PaymentSystem\Laravel\Stripe\ValueObject\TokenId;
use PaymentSystem\Laravel\Uuid;

readonly class Token implements TokenInterface
{
    public function __construct(
        public Uuid $accountId,
        public \Stripe\Token $token,
    ) {
    }

    public function isValid(): bool
    {
        return isset($this->token->id);
    }

    public function getId(): AggregateRootId
    {
        return new TokenId($this->token->id);
    }

    public function getGatewayId(): AggregateRootId
    {
        return $this->accountId;
    }

    public function getRawData(): array
    {
        return $this->token->toArray();
    }
}