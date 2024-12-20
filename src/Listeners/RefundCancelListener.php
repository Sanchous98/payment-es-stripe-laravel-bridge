<?php

namespace PaymentSystem\Laravel\Stripe\Listeners;

use EventSauce\EventSourcing\Message;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\CallQueuedListener;
use PaymentSystem\Events\RefundCanceled;
use PaymentSystem\Laravel\Jobs\Middleware\SkipMiddleware;
use PaymentSystem\Laravel\Messages\AccountDecorator;
use PaymentSystem\Laravel\Models\Account;
use PaymentSystem\Laravel\Stripe\Jobs\RefundCancelJob;
use PaymentSystem\Laravel\Stripe\Models\Credentials;
use PaymentSystem\Repositories\RefundRepositoryInterface;

readonly class RefundCancelListener implements ShouldQueue
{
    public function __construct(private RefundRepositoryInterface $repository, private Dispatcher $dispatcher)
    {
    }

    public function __invoke(RefundCanceled $event, Message $message): void
    {
        $account = Account::with('credentials')
            ->find($message->header(AccountDecorator::ACCOUNT_IDS_HEADER)[0]);

        if (!isset($account)) {
            $this->repository->persist($this->repository->retrieve($message->aggregateRootId())->decline('No accounts set for this request.'));
            return;
        }

        if ($account->credentials instanceof Credentials) {
            $this->dispatcher->dispatch((new RefundCancelJob($event, $message, $account))->onQueue('stripe'));
        }
    }

    public function middleware(): array
    {
        return [new SkipMiddleware(function (CallQueuedListener $listener) {
            $account = Account::with('credentials')
                ->find($listener->data[1]->header(AccountDecorator::ACCOUNT_IDS_HEADER)[0]);

            return !$account->credentials instanceof Credentials;
        })];
    }
}