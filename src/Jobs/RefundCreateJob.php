<?php

namespace PaymentSystem\Laravel\Stripe\Jobs;

use EventSauce\EventSourcing\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PaymentSystem\Events\RefundCreated;
use PaymentSystem\Laravel\Models\Account;
use PaymentSystem\Laravel\Stripe\Gateway\Refund;
use PaymentSystem\Laravel\Uuid;
use PaymentSystem\Repositories\PaymentIntentRepositoryInterface;
use PaymentSystem\Repositories\RefundRepositoryInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Stripe\PaymentIntent;
use Stripe\Service\RefundService;
use Stripe\StripeClient;

class RefundCreateJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;
    use InteractsWithQueue;
    use SerializesModels;

    private RefundService $service;

    public function __construct(
        private readonly RefundCreated $event,
        private readonly Message $message,
        private readonly Account $account,
    ) {
        $this->service = new RefundService(new StripeClient($this->account->credentials->api_key));
    }

    public function uniqueId(): string
    {
        return $this->message->aggregateRootId() . $this->account->id;
    }

    public function __invoke(
        RefundRepositoryInterface $refundRepository,
        PaymentIntentRepositoryInterface $paymentIntentRepository,
    ): void {
        $refund = $refundRepository
            ->retrieve($this->message->aggregateRootId());

        $paymentIntent = $paymentIntentRepository
            ->retrieve($this->event->paymentIntentId)
            ->getGatewayPaymentIntent();

        try {
            $refund->getGatewayRefund()->create(fn() => new Refund(
                Uuid::fromString($this->account->id),
                $this->service->create([
                    'amount' => $this->event->money->getAmount(),
                    'payment_intent' => $paymentIntent->getPaymentIntent()->getId(),
                    'expand' => ['balance_transaction'],
                ]),
                $this->event->paymentIntentId,
            ));
        } catch (InvalidRequestException $e) {
            $refund->decline($e->getMessage());
        }
    }
}