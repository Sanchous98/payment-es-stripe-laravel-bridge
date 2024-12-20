<?php

namespace PaymentSystem\Laravel\Stripe;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use PaymentSystem\Events\PaymentIntentAuthorized;
use PaymentSystem\Events\PaymentIntentCanceled;
use PaymentSystem\Events\PaymentIntentCaptured;
use PaymentSystem\Events\PaymentMethodCreated;
use PaymentSystem\Events\PaymentMethodUpdated;
use PaymentSystem\Events\RefundCanceled;
use PaymentSystem\Events\RefundCreated;
use PaymentSystem\Events\TokenCreated;
use PaymentSystem\Laravel\Stripe\Listeners\PaymentAuthorizeListener;
use PaymentSystem\Laravel\Stripe\Listeners\PaymentCancelListener;
use PaymentSystem\Laravel\Stripe\Listeners\PaymentCaptureListener;
use PaymentSystem\Laravel\Stripe\Listeners\PaymentMethodCreateListener;
use PaymentSystem\Laravel\Stripe\Listeners\PaymentMethodUpdateListener;
use PaymentSystem\Laravel\Stripe\Listeners\RefundCancelListener;
use PaymentSystem\Laravel\Stripe\Listeners\RefundCreateListener;
use PaymentSystem\Laravel\Stripe\Listeners\TokenCreateListener;
use PaymentSystem\Laravel\Stripe\Migrations\CredentialsMigration;
use PaymentSystem\Laravel\Stripe\Serializer\PaymentIntentNormalizer;
use PaymentSystem\Laravel\Stripe\Serializer\PaymentMethodNormalizer;
use PaymentSystem\Laravel\Stripe\Serializer\RefundNormalizer;
use PaymentSystem\Laravel\Stripe\Serializer\TokenNormalizer;

class PaymentStripeProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([CredentialsMigration::class], 'payment-migrations');

        Event::listen(PaymentIntentAuthorized::class, PaymentAuthorizeListener::class);
        Event::listen(PaymentIntentCanceled::class, PaymentCancelListener::class);
        Event::listen(PaymentIntentCaptured::class, PaymentCaptureListener::class);
        Event::listen(PaymentMethodCreated::class, PaymentMethodCreateListener::class);
        Event::listen(PaymentMethodUpdated::class, PaymentMethodUpdateListener::class);
        Event::listen(RefundCanceled::class, RefundCancelListener::class);
        Event::listen(RefundCreated::class, RefundCreateListener::class);
        Event::listen(TokenCreated::class, TokenCreateListener::class);
        
        $this->app->tag([
            PaymentIntentNormalizer::class,
            PaymentMethodNormalizer::class,
            RefundNormalizer::class,
            TokenNormalizer::class,
        ], 'normalizers');
    }
}
