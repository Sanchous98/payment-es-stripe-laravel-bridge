<?php

namespace PaymentSystem\Laravel\Stripe\Listeners;

use EventSauce\EventSourcing\Message;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Bus\QueueingDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use PaymentSystem\Events\TokenCreated;
use PaymentSystem\Laravel\Messages\AccountDecorator;
use PaymentSystem\Laravel\Models\Account;
use PaymentSystem\Laravel\Repository\TokenRepository;
use PaymentSystem\Laravel\Stripe\Jobs\TokenCreateJob;
use PaymentSystem\Laravel\Stripe\Models\Credentials;

readonly class TokenCreateListener implements ShouldQueue
{
    public function __construct(
        private TokenRepository $repository,
        private QueueingDispatcher $dispatcher
    ) {
    }

    public function __invoke(TokenCreated $event, Message $message): void
    {
        $accounts = Account::with('credentials')
            ->whereIn('id', $message->header(AccountDecorator::ACCOUNT_IDS_HEADER))
            ->get();

        if ($accounts->isEmpty()) {
            $this->repository->retrieve($message->aggregateRootId())->decline('No accounts set for this request.');
            return;
        }

        $accounts = $accounts->filter(fn (Account $account) => $account->credentials instanceof Credentials);

        if ($accounts->isEmpty()) {
            return;
        }

        $this->dispatcher
            ->batch($accounts->map(fn(Account $account) => new TokenCreateJob($event, $message, $account)))
            ->name("Create token ({$message->aggregateRootId()})")
            ->withOption('id', $message->aggregateRootId())
            ->onQueue('stripe')
            ->finally(function (Batch $batch) {
                if ($batch->pendingJobs === $batch->totalJobs) {
                    $repository = \app(TokenRepository::class);
                    $repository->retrieve($batch->options['id'])->decline('none of acquirers responded successfully');
                }
            })
            ->dispatch();
    }
}