<?php

namespace PaymentSystem\Laravel\Stripe\Messages;

use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDecorator;
use PaymentSystem\Laravel\Messages\AccountContextDispatcher;

class MigrateHeadersDecorator implements MessageDecorator
{
    private Message $message;

    public function messageToCopy(Message $message): void
    {
        $this->message = $message;
    }

    public function decorate(Message $message): Message
    {
        if (!isset($this->message)) {
            return $message;
        }

        return $message->withHeaders([
            AccountContextDispatcher::ACCOUNT_ID_HEADER => $this->message->header(AccountContextDispatcher::ACCOUNT_ID_HEADER),
        ]);
    }
}