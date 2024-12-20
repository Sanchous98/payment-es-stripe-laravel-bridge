<?php

namespace PaymentSystem\Laravel\Stripe\Gateway;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\Gateway\Resources\PaymentMethodInterface;
use PaymentSystem\Laravel\Stripe\ValueObject\PaymentMethodId;
use PaymentSystem\Laravel\Uuid;

readonly class PaymentMethod implements PaymentMethodInterface
{
    public function __construct(
        public Uuid $accountId,
        public \Stripe\PaymentMethod $paymentMethod,
    ) {
    }

    public function isValid(): bool
    {
        return isset($this->paymentMethod->id);
    }

    public function getId(): AggregateRootId
    {
        return new PaymentMethodId($this->paymentMethod->id);
    }

    public function getGatewayId(): AggregateRootId
    {
        return $this->accountId;
    }

    public function getRawData(): array
    {
        return $this->paymentMethod->toArray();
    }
}