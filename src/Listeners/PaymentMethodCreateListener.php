<?php

namespace PaymentSystem\Laravel\Stripe\Listeners;

use EventSauce\EventSourcing\Message;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Bus\QueueingDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\CallQueuedListener;
use PaymentSystem\Events\PaymentMethodCreated;
use PaymentSystem\Laravel\Jobs\Middleware\SkipMiddleware;
use PaymentSystem\Laravel\Messages\AccountDecorator;
use PaymentSystem\Laravel\Models\Account;
use PaymentSystem\Laravel\Stripe\Jobs\PaymentMethodCreateJob;
use PaymentSystem\Laravel\Stripe\Models\Credentials;
use PaymentSystem\Repositories\PaymentMethodRepositoryInterface;
use PaymentSystem\Repositories\TokenRepositoryInterface;

readonly class PaymentMethodCreateListener implements ShouldQueue
{
    public function __construct(
        private PaymentMethodRepositoryInterface $repository,
        private TokenRepositoryInterface $tokens,
        private QueueingDispatcher $dispatcher,
    ) {
    }

    public function __invoke(PaymentMethodCreated $event, Message $message): void
    {
        $accounts = Account::with('credentials')
            ->whereIn('id', $message->header(AccountDecorator::ACCOUNT_IDS_HEADER))
            ->get();

        if ($accounts->isEmpty()) {
            $this->repository->persist($this->repository->retrieve($message->aggregateRootId())->fail());
            return;
        }

        $accounts = $accounts->filter(fn (Account $account) => $account->credentials instanceof Credentials);

        if ($accounts->isEmpty()) {
            return;
        }

        $batch = $this->dispatcher
            ->batch($accounts->map(fn(Account $account) => new PaymentMethodCreateJob($event, $message, $account)))
            ->name("Create payment method ({$message->aggregateRootId()})")
            ->withOption('id', $message->aggregateRootId())
            ->onQueue('stripe')
            ->finally(function (Batch $batch) {
                if ($batch->pendingJobs === $batch->totalJobs) {
                    $repository = \app(PaymentMethodRepositoryInterface::class);
                    $repository->persist($repository->retrieve($batch->options['id'])->fail());
                }
            });

        if ($event->tokenId !== null) {
            $this->tokens->persist(
                $this->tokens->retrieve($event->tokenId)->use($batch->dispatch(...))
            );
        } else {
            $batch->dispatch();
        }
    }

    public function middleware(): array
    {
        return [new SkipMiddleware(function (CallQueuedListener $listener) {
            $accounts = Account::with('credentials')
                ->whereIn('id', $listener->data[1]->header(AccountDecorator::ACCOUNT_IDS_HEADER))
                ->get();

            return $accounts->filter(fn (Account $account) => $account->credentials instanceof Credentials)->isEmpty();
        })];
    }
}