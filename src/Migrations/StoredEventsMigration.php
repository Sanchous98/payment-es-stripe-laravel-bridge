<?php

namespace PaymentSystem\Laravel\Stripe\Migrations;

use PaymentSystem\Laravel\Contracts\MigrationTemplateInterface;

final class StoredEventsMigration implements MigrationTemplateInterface
{
    public function getStubPath(): string
    {
        return __DIR__ . '/stubs/create_stripe_stored_events_table.stub';
    }

    public function getTableName(): string
    {
        return 'stripe_stored_events';
    }
}