<?php

namespace PaymentSystem\Laravel\Stripe;

use EventSauce\EventSourcing\Message;
use PaymentSystem\Laravel\Messages\AccountContextDispatcher;
use PaymentSystem\Laravel\Models\Account;
use PaymentSystem\Laravel\Stripe\Models\Credentials;
use PaymentSystem\Stripe\Contract\ConfigProviderInterface;

class AccountConfigProvider implements ConfigProviderInterface
{
    public function getApiKey(Message $message): string
    {
        $accountId = $message->header(AccountContextDispatcher::ACCOUNT_ID_HEADER);

        if($accountId === null) {
            throw new \InvalidArgumentException('configId is required');
        }

        $account = Account::with('credentials')->find($accountId);

        if ($account === null) {
            throw new \InvalidArgumentException('account not found');
        }

        if (!$account->credentials instanceof Credentials) {
            throw new \InvalidArgumentException('account is not stripe');
        }

        return $account->credentials->api_key;
    }
}