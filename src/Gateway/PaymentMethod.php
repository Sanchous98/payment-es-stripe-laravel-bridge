<?php

namespace PaymentSystem\Laravel\Stripe\Gateway;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\Contracts\SourceInterface;
use PaymentSystem\Gateway\Resources\PaymentMethodInterface;
use PaymentSystem\Laravel\Stripe\ValueObject\PaymentMethodId;
use PaymentSystem\Laravel\Uuid;
use PaymentSystem\ValueObjects\BillingAddress;
use PaymentSystem\ValueObjects\Country;
use PaymentSystem\ValueObjects\CreditCard;

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

    public function getBillingAddress(): BillingAddress
    {
        [$firstName, $lastName] = explode(' ', $this->paymentMethod->billing_details->name);

        return new BillingAddress(
            $firstName,
            $lastName,
            $this->paymentMethod->billing_details->address->city,
            new Country($this->paymentMethod->billing_details->address->country),
            $this->paymentMethod->billing_details->address->postal_code,
            $this->paymentMethod->billing_details->email,
            $this->paymentMethod->billing_details->phone,
            $this->paymentMethod->billing_details->address->line1,
            $this->paymentMethod->billing_details->address->line2,
        );
    }

    public function getSource(): SourceInterface
    {
        return match ($this->paymentMethod->type) {
            'card' => new CreditCard(
                new CreditCard\Number('******', $this->paymentMethod->card->last4, $this->paymentMethod->card->brand),
                CreditCard\Expiration::fromMonthAndYear($this->paymentMethod->card->exp_month, $this->paymentMethod->card->exp_year),
                new CreditCard\Holder($this->paymentMethod->card->name ?? ''),
                new CreditCard\Cvc(),
            ),
        };
    }
}