<?php

namespace PaymentSystem\Laravel\Stripe\Listeners;

use EventSauce\EventSourcing\Message;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use PaymentSystem\Events\PaymentIntentCaptured;
use PaymentSystem\Laravel\Messages\AccountDecorator;
use PaymentSystem\Laravel\Models\Account;
use PaymentSystem\Laravel\Stripe\Jobs\PaymentIntentCaptureJob;
use PaymentSystem\Laravel\Stripe\Models\Credentials;
use PaymentSystem\Repositories\PaymentIntentRepositoryInterface;

readonly class PaymentCaptureListener implements ShouldQueue
{
    public function __construct(
        private PaymentIntentRepositoryInterface $repository,
        private Dispatcher $dispatcher,
    ) {
    }

    public function __invoke(PaymentIntentCaptured $event, Message $message): void
    {
        $account = Account::with('credentials')
            ->find($message->header(AccountDecorator::ACCOUNT_IDS_HEADER)[0]);

        if (!isset($account)) {
            $this->repository->retrieve($message->aggregateRootId())->decline('No accounts set for this request.');
            return;
        }

        if ($account->credentials instanceof Credentials) {
            $this->dispatcher->dispatch((new PaymentIntentCaptureJob($event, $message, $account))->onQueue('stripe'));
        }
    }
}