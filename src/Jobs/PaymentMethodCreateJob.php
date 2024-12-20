<?php

namespace PaymentSystem\Laravel\Stripe\Jobs;

use EventSauce\EventSourcing\Message;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PaymentSystem\Contracts\DecryptInterface;
use PaymentSystem\Events\PaymentMethodCreated;
use PaymentSystem\Gateway\Resources\TokenInterface;
use PaymentSystem\Laravel\Models\Account;
use PaymentSystem\Laravel\Stripe\Exceptions\UnsupportedSourceTypeException;
use PaymentSystem\Laravel\Stripe\Gateway\PaymentMethod;
use PaymentSystem\Laravel\Stripe\Gateway\Token;
use PaymentSystem\Laravel\Uuid;
use PaymentSystem\Repositories\PaymentMethodRepositoryInterface;
use PaymentSystem\Repositories\TokenRepositoryInterface;
use PaymentSystem\TokenAggregateRoot;
use PaymentSystem\ValueObjects\BillingAddress;
use PaymentSystem\ValueObjects\CreditCard;
use Stripe\Customer;
use Stripe\Service\CustomerService;
use Stripe\Service\PaymentMethodService;
use Stripe\StripeClient;

class PaymentMethodCreateJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;
    use InteractsWithQueue;
    use SerializesModels;
    use Batchable;

    private PaymentMethodService $service;
    private CustomerService $customers;

    public function __construct(
        private readonly PaymentMethodCreated $event,
        private readonly Message $message,
        private readonly Account $account,
    ) {
        $this->service = new PaymentMethodService(new StripeClient($this->account->credentials->api_key));
        $this->customers = new CustomerService(new StripeClient($this->account->credentials->api_key));
    }

    public function uniqueId(): string
    {
        return $this->message->aggregateRootId() . $this->account->id;
    }

    public function __invoke(
        TokenRepositoryInterface $tokenRepository,
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        DecryptInterface $decrypt,
    ): void {
        if ($this->event->tokenId !== null) {
            $token = $tokenRepository->retrieve($this->event->tokenId);
            $stripePaymentMethod = $this->attachFromToken($this->event->billingAddress, $token);
        } else {
            $source = $this->event->source;
            $data = [
                'type' => $source::TYPE,
                $source::TYPE => match ($source::class) {
                    CreditCard::class => [
                        'number' => $source->number->getNumber($decrypt),
                        'exp_month' => $source->expiration->format('n'),
                        'exp_year' => $source->expiration->format('Y'),
                        'cvc' => $source->cvc->getCvc($decrypt),
                    ],
                    default => throw new UnsupportedSourceTypeException(),
                }
            ];

            $stripePaymentMethod = $this->attach($this->event->billingAddress, $data);
        }

        $paymentMethodRepository
            ->retrieve($this->message->aggregateRootId())
            ->getGatewayPaymentMethods()
            ->add(fn() => new PaymentMethod(
                Uuid::fromString($this->account->id),
                $stripePaymentMethod,
            ));
    }

    private function attach(BillingAddress $address, array $params): \Stripe\PaymentMethod
    {
        $params += ['billing_details' => self::address($address),];
        $paymentMethod = $this->service->create($params);

        return $paymentMethod->attach(['customer' => $this->customer($address)]);
    }

    private function attachFromToken(BillingAddress $address, TokenAggregateRoot $token): \Stripe\PaymentMethod
    {
        /** @var Token $stripeToken */
        $stripeToken = $token->getGatewayTokens()
            ->find(fn(TokenInterface $token) => $token instanceof Token && $token->accountId->equals(Uuid::fromString($this->account->id)));

        assert($stripeToken !== null);

        return $this->attach($address, [
            'type' => $stripeToken->token->type,
            $stripeToken->token->type => [
                'token' => $stripeToken->token->id,
            ]
        ]);
    }

    private static function address(BillingAddress $billingAddress): array
    {
        return [
            'name' => "$billingAddress->firstName $billingAddress->lastName",
            'address' => [
                'city' => $billingAddress->city,
                'country' => (string)$billingAddress->country,
                'line1' => $billingAddress->addressLine,
                'line2' => $billingAddress->addressLineExtra,
                'postal_code' => $billingAddress->postalCode,
                'state' => (string)$billingAddress->state,
            ],
            'phone' => (string)$billingAddress->phone,
            'email' => (string)$billingAddress->email,
        ];
    }

    private function customer(BillingAddress $billingAddress): Customer
    {
        return $this->customers->create(self::address($billingAddress));
    }
}