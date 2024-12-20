<?php

namespace PaymentSystem\Laravel\Stripe\Migrations;

use PaymentSystem\Laravel\Contracts\MigrationTemplateInterface;

final class CredentialsMigration implements MigrationTemplateInterface
{
    public function getStubPath(): string
    {
        return __DIR__ . '/stubs/create_stripe_credentials_table.stub';
    }

    public function getTableName(): string
    {
        return 'stripe_credentials';
    }
}