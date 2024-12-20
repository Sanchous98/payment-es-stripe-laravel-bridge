<?php

namespace PaymentSystem\Laravel\Stripe\Jobs;

use EventSauce\EventSourcing\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PaymentSystem\Events\RefundCanceled;
use PaymentSystem\Gateway\Resources\RefundInterface;
use PaymentSystem\Laravel\Models\Account;
use PaymentSystem\Laravel\Stripe\Gateway\Refund;
use PaymentSystem\Laravel\Uuid;
use PaymentSystem\RefundAggregateRoot;
use PaymentSystem\Repositories\RefundRepositoryInterface;
use Stripe\Service\RefundService;
use Stripe\StripeClient;

class RefundCancelJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;
    use InteractsWithQueue;
    use SerializesModels;

    private RefundService $service;

    public function __construct(
        private readonly RefundCanceled $event,
        private readonly Message $message,
        private readonly Account $account,
    ) {
        $this->service = new RefundService(new StripeClient($this->account->credentials->api_key));
    }

    public function uniqueId(): string
    {
        return $this->message->aggregateRootId() . $this->account->id;
    }

    public function __invoke(RefundRepositoryInterface $repository): void
    {
        $repository
            ->retrieve($this->message->aggregateRootId())
            ->getGatewayRefund()
            ->cancel(fn(RefundInterface $refund) => new Refund(
                Uuid::fromString($this->account->id),
                $this->service->cancel($refund->getId()->toString()),
                $refund->getPaymentIntentId()
            ));
    }
}