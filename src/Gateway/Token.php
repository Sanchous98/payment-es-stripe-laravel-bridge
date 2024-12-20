<?php

namespace PaymentSystem\Laravel\Stripe\Gateway;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\Contracts\TokenizedSourceInterface;
use PaymentSystem\Gateway\Resources\TokenInterface;
use PaymentSystem\Laravel\Stripe\ValueObject\TokenId;
use PaymentSystem\Laravel\Uuid;
use PaymentSystem\ValueObjects\CreditCard;

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

    public function getSource(): TokenizedSourceInterface
    {
        return new CreditCard(
            new CreditCard\Number('******', $this->token->card->last4, $this->token->card->brand),
            CreditCard\Expiration::fromMonthAndYear($this->token->card->exp_month, $this->token->card->exp_year),
            new CreditCard\Holder($this->token->card->name ?? ''),
            new CreditCard\Cvc(),
        );
    }
}