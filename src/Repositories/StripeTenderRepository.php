<?php

namespace PaymentSystem\Laravel\Stripe\Repositories;

use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\ClassNameInflector;
use EventSauce\EventSourcing\DefaultHeadersDecorator;
use EventSauce\EventSourcing\DotSeparatedSnakeCaseInflector;
use EventSauce\EventSourcing\Header;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDecorator;
use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\MessageRepository;
use EventSauce\EventSourcing\SynchronousMessageDispatcher;
use EventSauce\EventSourcing\UnableToReconstituteAggregateRoot;
use Generator;
use PaymentSystem\Stripe\Repositories\StripeTenderRepositoryInterface;
use PaymentSystem\Stripe\TenderInterface;

class StripeTenderRepository implements StripeTenderRepositoryInterface
{
    public function __construct(
        private MessageRepository $messages,
        private MessageDispatcher $dispatcher = new SynchronousMessageDispatcher(),
        private MessageDecorator $decorator = new DefaultHeadersDecorator(),
        private ClassNameInflector $classNameInflector = new DotSeparatedSnakeCaseInflector()
    ) {
    }

    public function retrieve(AggregateRootId $aggregateRootId): TenderInterface
    {
        try {
            $messages = $this->messages->retrieveAll($aggregateRootId);
            /** @var class-string<TenderInterface> $className */
            $className = $this->classNameInflector
                ->typeToClassName($messages->current()->header(Header::AGGREGATE_ROOT_TYPE));

            assert(is_a($className, TenderInterface::class, true));

            return $className::reconstituteFromEvents($aggregateRootId, (function (Generator $messages): Generator {
                foreach ($messages as $message) {
                    yield $message->payload();
                }

                return $messages->getReturn();
            })($messages));
        } catch (\Throwable $throwable) {
            throw UnableToReconstituteAggregateRoot::becauseOf($throwable->getMessage(), $throwable);
        }
    }

    public function persist(TenderInterface $tender): void
    {
        $this->persistEvents(
            $tender->aggregateRootId(),
            $tender->aggregateRootVersion(),
            ...$tender->releaseEvents()
        );
    }

    public function persistEvents(AggregateRootId $aggregateRootId, int $aggregateRootVersion, object ...$events): void
    {
        if (count($events) === 0) {
            return;
        }

        // decrease the aggregate root version by the number of raised events
        // so the version of each message represents the version at the time
        // of recording.
        $aggregateRootVersion = $aggregateRootVersion - count($events);
        $metadata = [
            Header::AGGREGATE_ROOT_ID => $aggregateRootId,
            Header::AGGREGATE_ROOT_TYPE => $this->classNameInflector->classNameToType($this->aggregateRootClassName),
        ];
        $messages = array_map(function (object $event) use ($metadata, &$aggregateRootVersion) {
            return $this->decorator->decorate(new Message(
                $event,
                $metadata + [Header::AGGREGATE_ROOT_VERSION => ++$aggregateRootVersion]
            ));
        }, $events);

        $this->messages->persist(...$messages);
        $this->dispatcher->dispatch(...$messages);
    }
}