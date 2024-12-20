<?php

namespace PaymentSystem\Laravel\Stripe\Jobs;

use EventSauce\EventSourcing\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PaymentSystem\Events\PaymentIntentAuthorized;
use PaymentSystem\Gateway\Resources\ResourceInterface;
use PaymentSystem\Laravel\Models\Account;
use PaymentSystem\Laravel\Stripe\Gateway\PaymentIntent;
use PaymentSystem\Laravel\Stripe\Gateway\PaymentMethod;
use PaymentSystem\Laravel\Stripe\Gateway\Token;
use PaymentSystem\Laravel\Uuid;
use PaymentSystem\Repositories\PaymentIntentRepositoryInterface;
use PaymentSystem\Repositories\TenderRepositoryInterface;
use Stripe\Exception\InvalidRequestException;
use Stripe\Service\PaymentIntentService;
use Stripe\StripeClient;

class PaymentIntentAuthorizeJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;
    use InteractsWithQueue;
    use SerializesModels;

    private PaymentIntentService $service;

    public function __construct(
        private readonly PaymentIntentAuthorized $event,
        private readonly Message $message,
        private readonly Account $account,
    ) {
        $this->service = new PaymentIntentService(new StripeClient($this->account->credentials->api_key));
    }

    public function uniqueId(): string
    {
        return $this->message->aggregateRootId() . $this->account->id;
    }

    public function __invoke(
        PaymentIntentRepositoryInterface $repository,
        TenderRepositoryInterface $tenderRepository
    ): void {
        $stripeTender = null;

        if (isset($this->event->tenderId)) {
            $tender = $tenderRepository->retrieve($this->event->tenderId);
            $gatewayTender = collect($tender->getGatewayTenders())
                ->first(fn(ResourceInterface $tender) => $tender->getGatewayId()->toString() === (string)$this->account->id);

            $stripeTender = match ($gatewayTender::class) {
                Token::class => $gatewayTender->token,
                PaymentMethod::class => $gatewayTender->paymentMethod,
                default => throw new \RuntimeException('unknown tender'),
            };
        }

        $options = [
            'amount' => $this->event->money->getAmount(),
            'currency' => $this->event->money->getCurrency()->getCode(),
            'description' => $this->event->description,
            ...isset($stripeTender->customer) ? ['customer' => $stripeTender->customer] : [],
            ...match ($stripeTender::class) {
                \Stripe\Token::class => [
                    'payment_method_data' => [
                        'type' => $stripeTender->type,
                        $stripeTender->type => [
                            'token' => $stripeTender->id,
                        ]
                    ]
                ],
                \Stripe\PaymentMethod::class => ['payment_method' => $stripeTender->id],
            },
            ...isset($this->event->threeDSResult) ? [
                'payment_method_options' => [
                    'card' => [
                        'three_d_secure' => [
                            'cryptogram' => $this->event->threeDSResult->authenticationValue,
                            'transaction_id' => $this->event->threeDSResult->dsTransactionId,
                            'version' => $this->event->threeDSResult->version->value,
                            'ares_trans_status' => $this->event->threeDSResult->status->value,
                            'electronic_commerce_indicator' => $this->event->threeDSResult->eci->value,
                        ],
                    ],
                ]
            ] : [],
            ...!empty($this->event->merchantDescriptor) ? ['statement_descriptor' => $this->event->merchantDescriptor] : [],
            'capture_method' => 'manual',
            'confirm' => true,
            'automatic_payment_methods' => [
                'allow_redirects' => 'never',
                'enabled' => true,
            ],
        ];

        try {
            $repository
                ->retrieve($this->message->aggregateRootId())
                ->getGatewayPaymentIntent()
                ->authorize(fn() => new PaymentIntent(
                    Uuid::fromString($this->account->id),
                    $this->service->create($options),
                ));
        } catch (InvalidRequestException $e) {
            $repository
                ->retrieve($this->message->aggregateRootId())
                ->decline($e->getMessage());
        }
    }
}