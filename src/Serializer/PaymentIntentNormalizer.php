<?php

namespace PaymentSystem\Laravel\Stripe\Serializer;

use PaymentSystem\Gateway\Resources\PaymentIntentInterface;
use PaymentSystem\Laravel\Stripe\Gateway\PaymentIntent;
use PaymentSystem\Laravel\Uuid;
use Stripe\BalanceTransaction;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class PaymentIntentNormalizer implements DenormalizerInterface, NormalizerInterface
{
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        assert($data instanceof PaymentIntent);

        return [
            'account_id' => $data->accountId->toString(),
            'payment_intent' => $data->paymentIntent->toArray(),
            'balance_transactions' => array_map(fn(BalanceTransaction $tx) => $tx->toArray(), $data->transactions),
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof PaymentIntent;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): PaymentIntent
    {
        return new PaymentIntent(
            Uuid::fromString($data['account_id']),
            \Stripe\PaymentIntent::constructFrom($data['payment_intent']),
            ...array_map(fn(array $tx) => BalanceTransaction::constructFrom($tx) ,$data['balance_transactions']),
        );
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return is_a($type, PaymentIntentInterface::class, true)
            && isset($data['payment_intent']['id'])
            && str_starts_with($data['payment_intent']['id'], 'pi_');
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            PaymentIntentInterface::class => false,
            PaymentIntent::class => true,
        ];
    }
}