<?php


namespace PaymentSystem\Laravel\Stripe;

use EventSauce\EventSourcing\MessageDecorator;
use EventSauce\EventSourcing\MessageRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use PaymentSystem\Laravel\Stripe\Messages\MigrateHeadersDecorator;
use PaymentSystem\Laravel\Stripe\Messages\StripeMessageDispatcher;
use PaymentSystem\Laravel\Stripe\Migrations\Credentials;
use PaymentSystem\Laravel\Stripe\Migrations\SnapshotsMigration;
use PaymentSystem\Laravel\Stripe\Migrations\StoredEventsMigration;
use PaymentSystem\Laravel\Stripe\Repositories\StripePaymentIntentRepository;
use PaymentSystem\Laravel\Stripe\Repositories\StripePaymentMethodRepository;
use PaymentSystem\Laravel\Stripe\Repositories\StripeRefundRepository;
use PaymentSystem\Laravel\Stripe\Repositories\StripeTenderRepository;
use PaymentSystem\Laravel\Stripe\Repositories\StripeTokenRepository;
use PaymentSystem\Stripe\Consumers\PaymentIntentSaga;
use PaymentSystem\Stripe\Consumers\PaymentMethodSaga;
use PaymentSystem\Stripe\Consumers\RefundSaga;
use PaymentSystem\Stripe\Consumers\TokenSaga;
use PaymentSystem\Stripe\Contract\ConfigProviderInterface;
use PaymentSystem\Stripe\Repositories\StripePaymentIntentRepositoryInterface;
use PaymentSystem\Stripe\Repositories\StripePaymentMethodRepositoryInterface;
use PaymentSystem\Stripe\Repositories\StripeRefundRepositoryInterface;
use PaymentSystem\Stripe\Repositories\StripeTenderRepositoryInterface;
use PaymentSystem\Stripe\Repositories\StripeTokenRepositoryInterface;
use Stripe\Service\CustomerService;
use Stripe\Service\PaymentIntentService;
use Stripe\Service\PaymentMethodService;
use Stripe\Service\RefundService;
use Stripe\Service\TokenService;
use Stripe\StripeClient;

class PaymentStripeProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__) . '/config/payment-es-stripe.php', 'payment-es-stripe');

        $this->app->when(StripePaymentMethodRepository::class)
            ->needs(MessageRepository::class)
            ->give(fn(Application $app) => $app->make(MessageRepository::class, [
                'tableName' => $app['config']->get('payment-es-stripe.events_table'),
                'tableSchema' => new TableSchema()
            ]));

        $this->app->when(StripePaymentIntentRepository::class)
            ->needs(MessageRepository::class)
            ->give(fn(Application $app) => $app->make(MessageRepository::class, [
                'tableName' => $app['config']->get('payment-es-stripe.events_table'),
                'tableSchema' => new TableSchema()
            ]));

        $this->app->when(StripeRefundRepository::class)
            ->needs(MessageRepository::class)
            ->give(fn(Application $app) => $app->make(MessageRepository::class, [
                'tableName' => $app['config']->get('payment-es-stripe.events_table'),
                'tableSchema' => new TableSchema()
            ]));

        $this->app->when(StripeTokenRepository::class)
            ->needs(MessageRepository::class)
            ->give(fn(Application $app) => $app->make(MessageRepository::class, [
                'tableName' => $app['config']->get('payment-es-stripe.events_table'),
                'tableSchema' => new TableSchema()
            ]));

        $this->app->when(StripeTenderRepository::class)
            ->needs(MessageRepository::class)
            ->give(fn(Application $app) => $app->make(MessageRepository::class, [
                'tableName' => $app['config']->get('payment-es-stripe.events_table'),
                'tableSchema' => new TableSchema()
            ]));

        $this->app->when(StripePaymentMethodRepository::class)
            ->needs(MessageDecorator::class)
            ->give(MigrateHeadersDecorator::class);

        $this->app->when(StripePaymentIntentRepository::class)
            ->needs(MessageDecorator::class)
            ->give(MigrateHeadersDecorator::class);

        $this->app->when(StripeRefundRepository::class)
            ->needs(MessageDecorator::class)
            ->give(MigrateHeadersDecorator::class);

        $this->app->when(StripeTokenRepository::class)
            ->needs(MessageDecorator::class)
            ->give(MigrateHeadersDecorator::class);

        $this->app->when(StripeTenderRepository::class)
            ->needs(MessageDecorator::class)
            ->give(MigrateHeadersDecorator::class);

        $this->app->singleton(StripePaymentMethodRepositoryInterface::class, StripePaymentMethodRepository::class);
        $this->app->singleton(StripePaymentIntentRepositoryInterface::class, StripePaymentIntentRepository::class);
        $this->app->singleton(StripeRefundRepositoryInterface::class, StripeRefundRepository::class);
        $this->app->singleton(StripeTokenRepositoryInterface::class, StripeTokenRepository::class);
        $this->app->singleton(StripeTenderRepositoryInterface::class, StripeTenderRepository::class);

        $this->app->when(PaymentMethodSaga::class)
            ->needs(PaymentMethodService::class)
            ->give(fn() => new PaymentMethodService(new StripeClient()));

        $this->app->when(PaymentMethodSaga::class)
            ->needs(CustomerService::class)
            ->give(fn() => new CustomerService(new StripeClient()));

        $this->app->when(PaymentIntentSaga::class)
            ->needs(PaymentIntentService::class)
            ->give(fn() => new PaymentIntentService(new StripeClient()));

        $this->app->when(TokenSaga::class)
            ->needs(TokenService::class)
            ->give(fn() => new TokenService(new StripeClient()));

        $this->app->when(RefundSaga::class)
            ->needs(RefundService::class)
            ->give(fn() => new RefundService(new StripeClient()));

        $this->app->tag([
            PaymentMethodSaga::class,
            PaymentIntentSaga::class,
            RefundSaga::class,
            TokenSaga::class,
        ], 'es_stripe_consumers');

        $this->app->singleton(ConfigProviderInterface::class, AccountConfigProvider::class);
        $this->app->singleton(MigrateHeadersDecorator::class);
        $this->app->singleton(StripeMessageDispatcher::class, function (Application $app) {
            return new StripeMessageDispatcher($app->tagged('es_stripe_consumers'), $app[MigrateHeadersDecorator::class]);
        });

        $this->app->tag([Credentials::class, SnapshotsMigration::class, StoredEventsMigration::class], 'payment-migrations');
        $this->app->tag([StripeMessageDispatcher::class], 'es_dispatchers');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                dirname(__DIR__) . '/config' => $this->app->configPath(),
                dirname(__DIR__) . '/database/migrations' => $this->app->databasePath('migrations'),
            ]);
        }
    }
}
