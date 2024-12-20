<?php

namespace PaymentSystem\Laravel\Stripe\Jobs;

use EventSauce\EventSourcing\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PaymentSystem\Gateway\Resources\PaymentIntentInterface;
use PaymentSystem\Laravel\Models\Account;
use PaymentSystem\Laravel\Stripe\Gateway\PaymentIntent;
use PaymentSystem\Laravel\Uuid;
use PaymentSystem\Repositories\PaymentIntentRepositoryInterface;
use Stripe\Service\PaymentIntentService;
use Stripe\StripeClient;

class PaymentIntentCancelJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;
    use InteractsWithQueue;
    use SerializesModels;

    private PaymentIntentService $service;

    public function __construct(
        private readonly Message $message,
        private readonly Account $account,
    ) {
        $this->service = new PaymentIntentService(new StripeClient($this->account->credentials->api_key));
    }

    public function uniqueId(): string
    {
        return $this->message->aggregateRootId() . $this->account->id;
    }

    public function __invoke(PaymentIntentRepositoryInterface $repository): void
    {
        $repository
            ->retrieve($this->message->aggregateRootId())
            ->getGatewayPaymentIntent()
            ->cancel(fn(PaymentIntentInterface $gateway) => new PaymentIntent(
                Uuid::fromString($this->account->id),
                $this->service->cancel($gateway->getId()->toString())
            ));
    }
}