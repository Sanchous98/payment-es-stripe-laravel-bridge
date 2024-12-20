<?php

namespace PaymentSystem\Laravel\Stripe\Gateway;

use EventSauce\EventSourcing\AggregateRootId;
use Money\Currency;
use Money\Money;
use PaymentSystem\Gateway\Resources\PaymentIntentInterface;
use PaymentSystem\Laravel\Stripe\ValueObject\PaymentIntentId;
use PaymentSystem\Laravel\Uuid;
use PaymentSystem\ValueObjects\ThreeDSResult;
use Stripe\BalanceTransaction;

readonly class PaymentIntent implements PaymentIntentInterface
{
    public array $transactions;

    public function __construct(
        public Uuid $accountId,
        public \Stripe\PaymentIntent $paymentIntent,
        public ?AggregateRootId $paymentMethodId = null,
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

        $fees = array_map(fn(BalanceTransaction $transaction) => new Money($transaction->amount, new Currency($transaction->currency)), $this->transactions);

        return array_reduce($fees, fn(?Money $carry, Money $money) => isset($carry) ? $carry->add($money) : $money);
    }

    public function getMoney(): Money
    {
        return new Money($this->paymentIntent->amount, new Currency($this->paymentIntent->currency));
    }

    public function getMerchantDescriptor(): string
    {
        return $this->paymentIntent->statement_descriptor ?? '';
    }

    public function getDescription(): string
    {
        return $this->paymentIntent->description ?? '';
    }

    public function getPaymentMethodId(): ?AggregateRootId
    {
        return $this->paymentMethodId;
    }

    public function getThreeDS(): ?ThreeDSResult
    {
        return null;
    }

    public function getDeclineReason(): string
    {
        return $this->paymentIntent->cancellation_reason;
    }
}