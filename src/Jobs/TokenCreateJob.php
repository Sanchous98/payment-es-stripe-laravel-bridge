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
use PaymentSystem\Contracts\TokenizedSourceInterface;
use PaymentSystem\Events\TokenCreated;
use PaymentSystem\Laravel\Models\Account;
use PaymentSystem\Laravel\Stripe\Exceptions\UnsupportedSourceTypeException;
use PaymentSystem\Laravel\Stripe\Gateway\Token;
use PaymentSystem\Laravel\Uuid;
use PaymentSystem\Repositories\TokenRepositoryInterface;
use PaymentSystem\ValueObjects\CreditCard;
use Stripe\Service\TokenService;
use Stripe\StripeClient;

class TokenCreateJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;
    use InteractsWithQueue;
    use SerializesModels;
    use Batchable;

    private TokenService $service;

    public function __construct(
        private readonly TokenCreated $event,
        private readonly Message $message,
        private readonly Account $account,
    ) {
        $this->service = new TokenService(new StripeClient($this->account->credentials->api_key));
    }

    public function uniqueId(): string
    {
        return $this->message->aggregateRootId() . $this->account->id;
    }

    public function __invoke(DecryptInterface $decrypt, TokenRepositoryInterface $repository): void
    {
        $apiKey = $this->account->credentials->api_key;

        $repository
            ->retrieve($this->message->aggregateRootId())
            ->getGatewayTokens()
            ->add(fn() => new Token(
                Uuid::fromString($this->account->id),
                $this->create($this->event->source, $decrypt, $apiKey)
            ));
    }

    private function create(TokenizedSourceInterface $source, DecryptInterface $decrypt, string $apiKey): \Stripe\Token
    {
        $data = match ($source::class) {
            CreditCard::class => [
                'number' => $source->number->getNumber($decrypt),
                'exp_month' => $source->expiration->format('n'),
                'exp_year' => $source->expiration->format('Y'),
                'cvc' => $source->cvc->getCvc($decrypt),
            ],
            default => throw new UnsupportedSourceTypeException(),
        };

        return $this->service->create([$source::TYPE => $data], ['api_key' => $apiKey]);
    }
}