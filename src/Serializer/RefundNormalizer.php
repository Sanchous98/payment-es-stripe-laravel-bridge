<?php

namespace PaymentSystem\Laravel\Stripe\Serializer;

use PaymentSystem\Gateway\Resources\RefundInterface;
use PaymentSystem\Laravel\Stripe\Gateway\Refund;
use PaymentSystem\Laravel\Uuid;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class RefundNormalizer implements DenormalizerInterface, NormalizerInterface
{
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        assert($data instanceof Refund);

        return [
            'account_id' => $data->accountId->toString(),
            'refund' => $data->refund->toArray(),
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Refund;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Refund
    {
        return new Refund(
            Uuid::fromString($data['account_id']),
            \Stripe\Refund::constructFrom($data['refund'])
        );
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return is_a($type, RefundInterface::class, true)
            && isset($data['refund']['id'])
            && str_starts_with($data['refund']['id'], 're_');
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            RefundInterface::class => false,
            Refund::class => true,
        ];
    }
}