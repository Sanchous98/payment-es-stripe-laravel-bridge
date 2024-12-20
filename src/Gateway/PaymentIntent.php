<?php

namespace PaymentSystem\Laravel\Stripe\Gateway;

use EventSauce\EventSourcing\AggregateRootId;
use Money\Currency;
use Money\Money;
use PaymentSystem\Gateway\Resources\PaymentIntentInterface;
use PaymentSystem\Laravel\Stripe\ValueObject\PaymentIntentId;
use PaymentSystem\Laravel\Uuid;
use Stripe\BalanceTransaction;
use Stripe\Charge;

readonly class PaymentIntent implements PaymentIntentInterface
{
    public array $transactions;

    public function __construct(
        public Uuid $accountId,
        public \Stripe\PaymentIntent $paymentIntent,
        BalanceTransaction ...$transactions,
    ) {
        $this->transactions = $transactions;
    }

    public function getId(): AggregateRootId
    {
        return new PaymentIntentId($this->paymentIntent->id);
    }

    public function getGatewayId(): AggregateRootId
    {
        return $this->accountId;
    }

    public function getRawData(): array
    {
        return $this->paymentIntent->toArray();
    }

    public function isValid(): bool
    {
        return isset($this->paymentIntent->id);
    }

    public function getFee(): ?Money
    {
        if (count($this->transactions) === 0) {
            return null;
        }

        $fees = array_map(function(BalanceTransaction $transaction) {
            return new Money($transaction->amount, new Currency($transaction->currency));
        }, $this->transactions);

        return $fees[0]->add(...array_slice($fees, 1));
    }
}