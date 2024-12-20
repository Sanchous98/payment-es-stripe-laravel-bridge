<?php

namespace PaymentSystem\Laravel\Stripe\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\UuidInterface;

/**
 * @property-read UuidInterface $id
 * @property string $api_key
 * @property string $webhook_signing_key
 */
class Credentials extends Model
{
    use HasUuids;

    protected $table = 'stripe_credentials';

    protected $fillable = ['api_key', 'webhook_signing_key'];

    protected $casts = [
        'api_key' => 'encrypted',
        'webhook_signing_key' => 'encrypted',
    ];
}