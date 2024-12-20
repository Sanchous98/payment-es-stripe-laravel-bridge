<?php

namespace PaymentSystem\Laravel\Stripe\Jobs;

use EventSauce\EventSourcing\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PaymentSystem\Events\PaymentMethodUpdated;
use PaymentSystem\Gateway\Resources\PaymentMethodInterface;
use PaymentSystem\Laravel\Models\Account;
use PaymentSystem\Laravel\Stripe\Gateway\PaymentMethod;
use PaymentSystem\Laravel\Uuid;
use PaymentSystem\Repositories\PaymentMethodRepositoryInterface;
use PaymentSystem\ValueObjects\BillingAddress;
use Stripe\Service\PaymentMethodService;
use Stripe\StripeClient;

class PaymentMethodUpdateJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;
    use InteractsWithQueue;
    use SerializesModels;

    private PaymentMethodService $service;

    public function __construct(
        private readonly PaymentMethodUpdated $event,
        private readonly Message $message,
        private readonly Account $account,
    ) {
        $this->service = new PaymentMethodService(new StripeClient($this->account->credentials->api_key));
    }

    public function uniqueId(): string
    {
        return $this->message->aggregateRootId() . $this->account->id;
    }

    public function __invoke(PaymentMethodRepositoryInterface $repository): void
    {
        $paymentMethod = $repository->retrieve($this->message->aggregateRootId());
        $stripePm = $paymentMethod->getGatewayPaymentMethods()
            ->find(fn(PaymentMethodInterface $paymentMethod) => $paymentMethod instanceof PaymentMethod && $paymentMethod->accountId->equals(Uuid::fromString($this->account->id)));

        assert($stripePm !== null);

        $paymentMethod
            ->getGatewayPaymentMethods()
            ->update($stripePm->getGatewayId(), $stripePm->getId(), fn() => new PaymentMethod(
                Uuid::fromString($this->account->id),
                $this->service->update($stripePm->paymentMethod->id, ['billing_details' => self::address($this->event->billingAddress)])
            ));
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
}