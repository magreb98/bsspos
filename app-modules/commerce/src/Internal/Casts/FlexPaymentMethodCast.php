<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Modules\Commerce\Internal\Enums\PaymentMethod;

/**
 * Stores the payment method as a plain string in the DB.
 * On read, returns a PaymentMethod enum when the value matches a known case,
 * or a plain string for dynamically configured methods.
 *
 * @implements CastsAttributes<PaymentMethod|string, PaymentMethod|string>
 */
final class FlexPaymentMethodCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): PaymentMethod|string
    {
        if ($value === null) {
            return '';
        }

        return PaymentMethod::tryFrom((string) $value) ?? (string) $value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof PaymentMethod) {
            return $value->value;
        }

        return (string) $value;
    }
}
