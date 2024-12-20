<?php

namespace PaymentSystem\Laravel\Stripe\Listeners;

use EventSauce\EventSourcing\Message;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use PaymentSystem\Events\RefundCreated;
use PaymentSystem\Laravel\Messages\AccountDecorator;
use PaymentSystem\Laravel\Models\Account;
use PaymentSystem\Laravel\Stripe\Jobs\RefundCreateJob;
use PaymentSystem\Laravel\Stripe\Models\Credentials;
use PaymentSystem\Repositories\RefundRepositoryInterface;

readonly class RefundCreateListener implements ShouldQueue
{
    public function __construct(
        private RefundRepositoryInterface $repository,
        private Dispatcher $dispatcher,
    ) {
    }

    public function __invoke(RefundCreated $event, Message $message): void
    {
        $account = Account::with('credentials')
            ->find($message->header(AccountDecorator::ACCOUNT_IDS_HEADER)[0]);

        if (!isset($account)) {
            $this->repository->retrieve($message->aggregateRootId())->decline('No accounts set for this request.');
            return;
        }

        if ($account->credentials instanceof Credentials) {
            $this->dispatcher->dispatch((new RefundCreateJob($event, $message, $account))->onQueue('stripe'));
        }
    }
}