<?php

namespace PaymentSystem\Laravel\Stripe\Jobs;

use EventSauce\EventSourcing\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PaymentSystem\Events\PaymentIntentCaptured;
use PaymentSystem\Gateway\Resources\PaymentIntentInterface;
use PaymentSystem\Gateway\Resources\ResourceInterface;
use PaymentSystem\Laravel\Models\Account;
use PaymentSystem\Laravel\Stripe\Gateway\PaymentIntent;
use PaymentSystem\Laravel\Stripe\Gateway\PaymentMethod;
use PaymentSystem\Laravel\Stripe\Gateway\Token;
use PaymentSystem\Laravel\Uuid;
use PaymentSystem\Repositories\PaymentIntentRepositoryInterface;
use PaymentSystem\Repositories\TenderRepositoryInterface;
use Stripe\Charge;
use Stripe\Exception\InvalidRequestException;
use Stripe\Service\ChargeService;
use Stripe\Service\PaymentIntentService;
use Stripe\StripeClient;

class PaymentIntentCaptureJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;
    use InteractsWithQueue;
    use SerializesModels;

    private PaymentIntentService $service;

    private ChargeService $charges;

    public function __construct(
        private readonly PaymentIntentCaptured $event,
        private readonly Message $message,
        private readonly Account $account,
    ) {
        $this->service = new PaymentIntentService(new StripeClient($this->account->credentials->api_key));
        $this->charges = new ChargeService(new StripeClient($this->account->credentials->api_key));
    }

    public function uniqueId(): string
    {
        return $this->message->aggregateRootId() . $this->account->id;
    }

    public function __invoke(
        TenderRepositoryInterface $tenderRepository,
        PaymentIntentRepositoryInterface $repository,
    ): void {
        $tenderId = null;

        if (isset($this->event->tenderId)) {
            $tender = collect($tenderRepository->retrieve($this->event->tenderId)->getGatewayTenders())
                ->first(
                    fn(ResourceInterface $tender) => $tender->getGatewayId()->toString() === (string)$this->account->id
                );

            $tenderId = match ($tender::class) {
                Token::class => $tender->token->id,
                PaymentMethod::class => $tender->paymentMethod->id,
                default => throw new \RuntimeException('unknown tender'),
            };
        }

        try {
            $repository
                ->retrieve($this->message->aggregateRootId())
                ->getGatewayPaymentIntent()
                ->capture(function(PaymentIntentInterface $paymentIntent) use($tenderId) {
                    $captured = $this->service->capture($paymentIntent->getId()->toString(), [
                        ...(isset($this->event->amount) ? ['amount_to_capture' => $this->event->amount] : []),
                        ...(isset($tenderId) ? ['payment_method' => $tenderId] : []),
                    ]);

                    $balanceTransactions = collect($this->charges->all([
                        'payment_intent' => $captured->id,
                        'expand' => ['data.balance_transaction'],
                    ]))
                        ->flatMap(fn(Charge $charge) => $charge->balance_transaction)
                        ->filter();

                    return new PaymentIntent(
                        Uuid::fromString($this->account->id),
                        $captured,
                        ...$balanceTransactions,
                    );
                });
        } catch (InvalidRequestException $e) {
            $repository->retrieve($this->message->aggregateRootId())->decline($e->getMessage());
        }
    }
}