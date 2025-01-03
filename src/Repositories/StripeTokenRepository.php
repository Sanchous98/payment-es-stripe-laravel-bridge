<?php

namespace PaymentSystem\Laravel\Stripe\Repositories;

use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\ClassNameInflector;
use EventSauce\EventSourcing\EventSourcedAggregateRootRepository;
use EventSauce\EventSourcing\MessageDecorator;
use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\MessageRepository;
use PaymentSystem\Stripe\Repositories\StripeTokenRepositoryInterface;
use PaymentSystem\Stripe\TokenAggregateRoot;

/**
 * @extends EventSourcedAggregateRootRepository<TokenAggregateRoot>
 */
class StripeTokenRepository extends EventSourcedAggregateRootRepository implements StripeTokenRepositoryInterface
{
    public function __construct(
        MessageRepository $messageRepository,
        ?MessageDispatcher $dispatcher = null,
        ?MessageDecorator $decorator = null,
        ?ClassNameInflector $classNameInflector = null
    ) {
        parent::__construct(TokenAggregateRoot::class, $messageRepository, $dispatcher, $decorator, $classNameInflector);
    }

    public function retrieve(AggregateRootId $aggregateRootId): TokenAggregateRoot
    {
        return parent::retrieve($aggregateRootId); // TODO: Change the autogenerated stub
    }
}
