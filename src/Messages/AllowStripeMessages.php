<?php

namespace PaymentSystem\Laravel\Stripe\Messages;

use EventSauce\EventSourcing\AntiCorruptionLayer\MessageFilter;
use EventSauce\EventSourcing\Message;
use PaymentSystem\Laravel\Messages\AccountContextDispatcher;
use PaymentSystem\Laravel\Models\Account;
use PaymentSystem\Laravel\Stripe\Models\Credentials;

class AllowStripeMessages implements MessageFilter
{
    public function allows(Message $message): bool
    {
        $accountId = $message->header(AccountContextDispatcher::ACCOUNT_ID_HEADER);

        if ($accountId === null) {
            return false;
        }

        $account = Account::with('credentials')->find($accountId);

        return $account !== null && $account->credentials instanceof Credentials;
    }
}