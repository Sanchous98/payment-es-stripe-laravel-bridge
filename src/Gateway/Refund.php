<?php

namespace PaymentSystem\Laravel\Stripe\Gateway;

use EventSauce\EventSourcing\AggregateRootId;
use Money\Currency;
use Money\Money;
use PaymentSystem\Gateway\Resources\RefundInterface;
use PaymentSystem\Laravel\Stripe\ValueObject\RefundId;
use PaymentSystem\Laravel\Uuid;

readonly class Refund implements RefundInterface
{
    public function __construct(
        public Uuid $accountId,
        public \Stripe\Refund $refund,
        public AggregateRootId $paymentIntentId,
    ) {
        assert(!is_string($this->refund->balance_transaction));
    }

    public function getId(): AggregateRootId
    {
        return new RefundId($this->refund->id);
    }

    public function getGatewayId(): AggregateRootId
    {
       return $this->accountId;
    }

    public function getRawData(): array
    {
        return $this->refund->toArray();
    }

    public function isValid(): bool
    {
        return isset($this->refund->id);
    }

    public function getFee(): ?Money
    {
        if ($this->refund->balance_transaction === null) {
            return null;
        }

        return new Money($this->refund->balance_transaction->fee, new Currency($this->refund->balance_transaction->currency));
    }

    public function getMoney(): Money
    {
        return new Money($this->refund->amount, new Currency($this->refund->currency));
    }

    public function getPaymentIntentId(): AggregateRootId
    {
        return $this->paymentIntentId;
    }
}