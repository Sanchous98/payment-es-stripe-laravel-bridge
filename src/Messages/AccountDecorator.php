<?php

namespace PaymentSystem\Laravel\Stripe\Messages;

use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDecorator;
use PaymentSystem\Laravel\Models\Account;

readonly class AccountDecorator implements MessageDecorator
{
    public function __construct(private Account $account)
    {
    }

    public function decorate(Message $message): Message
    {
        return $message->withHeader(\PaymentSystem\Laravel\Messages\AccountDecorator::ACCOUNT_IDS_HEADER, $this->account->id);
    }
}