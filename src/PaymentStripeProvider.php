<?php


namespace PaymentSystem\Laravel\Stripe;

use EventSauce\EventSourcing\MessageRepository;
use EventSauce\EventSourcing\Serialization\ConstructingMessageSerializer;
use EventSauce\MessageRepository\IlluminateMessageRepository\IlluminateMessageRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;
use PaymentSystem\Laravel\Serializer\SymfonyPayloadSerializer;
use PaymentSystem\Laravel\Stripe\Repositories\StripeTenderRepository;
use PaymentSystem\Stripe\Consumers\PaymentIntentSaga;
use PaymentSystem\Stripe\Consumers\PaymentMethodSaga;
use PaymentSystem\Stripe\Consumers\RefundSaga;
use PaymentSystem\Stripe\Consumers\TokenSaga;
use PaymentSystem\Stripe\Repositories\StripePaymentIntentRepositoryInterface;
use PaymentSystem\Stripe\Repositories\StripePaymentMethodRepositoryInterface;
use PaymentSystem\Stripe\Repositories\StripeRefundRepositoryInterface;
use PaymentSystem\Stripe\Repositories\StripeTenderRepositoryInterface;
use PaymentSystem\Stripe\Repositories\StripeTokenRepositoryInterface;
use PaymentSystem\Laravel\Stripe\Repositories\StripePaymentIntentRepository;
use PaymentSystem\Laravel\Stripe\Repositories\StripePaymentMethodRepository;
use PaymentSystem\Laravel\Stripe\Repositories\StripeRefundRepository;
use PaymentSystem\Laravel\Stripe\Repositories\StripeTokenRepository;
use Stripe\Service\CustomerService;
use Stripe\Service\PaymentIntentService;
use Stripe\Service\PaymentMethodService;
use Stripe\Service\RefundService;
use Stripe\Service\TokenService;
use Stripe\StripeClient;
use Symfony\Component\Serializer\Serializer;

class PaymentStripeProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__) . '/config/payment-es-stripe.php', 'payment-es-stripe');

        $this->app->singleton(MessageRepository::class, fn(Application $app) => new IlluminateMessageRepository(
            $app[DatabaseManager::class]->connection(),
            config('payment-es-stripe.events_table'),
            serializer: new ConstructingMessageSerializer(
                payloadSerializer: new SymfonyPayloadSerializer(
                    new Serializer(iterator_to_array($app->tagged('normalizers'))),
                ),
            ),
        ));

        $this->app->when(StripePaymentMethodRepository::class)
            ->needs(MessageRepository::class)
            ->give(
                fn(Application $app) => $app
                    ->make(MessageRepository::class, ['tableName' => config('payment-es-stripe.events_table')])
            );

        $this->app->when(StripePaymentIntentRepository::class)
            ->needs(MessageRepository::class)
            ->give(
                fn(Application $app) => $app
                    ->make(MessageRepository::class, ['tableName' => config('payment-es-stripe.events_table')])
            );

        $this->app->when(StripeRefundRepository::class)
            ->needs(MessageRepository::class)
            ->give(
                fn(Application $app) => $app
                    ->make(MessageRepository::class, ['tableName' => config('payment-es-stripe.events_table')])
            );

        $this->app->when(StripeTokenRepository::class)
            ->needs(MessageRepository::class)
            ->give(
                fn(Application $app) => $app
                    ->make(MessageRepository::class, ['tableName' => config('payment-es-stripe.events_table')])
            );

        $this->app->when(StripeTenderRepository::class)
            ->needs(MessageRepository::class)
            ->give(
                fn(Application $app) => $app
                    ->make(MessageRepository::class, ['tableName' => config('payment-es-stripe.events_table')])
            );

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
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                dirname(__DIR__) . '/config' => config_path(),
                dirname(__DIR__) . '/database/migrations' => database_path('migrations'),
            ]);
        }
    }
}
