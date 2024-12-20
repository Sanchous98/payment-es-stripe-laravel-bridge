<?php

namespace PaymentSystem\Laravel\Stripe\Messages;

use EventSauce\EventSourcing\AntiCorruptionLayer\MessageFilter;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDispatcher;

readonly class StripeMessageDispatcher implements MessageDispatcher
{
    public function __construct(
        private iterable $consumers,
        private MigrateHeadersDecorator $decorator,
        private MessageFilter $filter = new AllowStripeMessages(),
    ) {
    }

    public function dispatch(Message ...$messages): void
    {
        foreach (array_filter($messages, $this->filter->allows(...)) as $message) {
            foreach ($this->consumers as $consumer) {
                $this->decorator->messageToCopy($message);
                $consumer->handle($message);
            }
        }
    }
}
