<?php

namespace PaymentSystem\Laravel\Stripe;

use PaymentSystem\Laravel\Messages\AccountContextDispatcher;

final class TableSchema implements \EventSauce\MessageRepository\TableSchema\TableSchema
{
    public function incrementalIdColumn(): string
    {
        return 'id';
    }

    public function eventIdColumn(): string
    {
        return 'event_id';
    }

    public function aggregateRootIdColumn(): string
    {
        return 'aggregate_root_id';
    }

    public function versionColumn(): string
    {
        return 'version';
    }

    public function payloadColumn(): string
    {
        return 'payload';
    }

    public function additionalColumns(): array
    {
        return [
            'account_id' => AccountContextDispatcher::ACCOUNT_ID_HEADER,
        ];
    }
}