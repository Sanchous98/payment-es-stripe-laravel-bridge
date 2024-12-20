<?php

namespace PaymentSystem\Laravel\Stripe\Serializer;

use PaymentSystem\Gateway\Resources\PaymentMethodInterface;
use PaymentSystem\Laravel\Stripe\Gateway\PaymentMethod;
use PaymentSystem\Laravel\Uuid;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class PaymentMethodNormalizer implements DenormalizerInterface, NormalizerInterface
{
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        assert($data instanceof PaymentMethod);

        return [
            'account_id' => $data->accountId->toString(),
            'payment_method' => $data->paymentMethod->toArray(),
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof PaymentMethod;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): PaymentMethod
    {
        return new PaymentMethod(
            Uuid::fromString($data['account_id']),
            \Stripe\PaymentMethod::constructFrom($data['payment_method']),
        );
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return is_a($type, PaymentMethodInterface::class, true)
            && isset($data['payment_method']['id'])
            && str_starts_with($data['payment_method']['id'], 'pm_');
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            PaymentMethodInterface::class => false,
            PaymentMethod::class => true,
        ];
    }
}