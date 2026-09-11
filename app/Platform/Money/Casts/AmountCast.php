<?php

declare(strict_types=1);

namespace App\Platform\Money\Casts;

use App\Platform\Money\Amount;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** @implements CastsAttributes<Amount|null, Amount|int|null> */
final class AmountCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Amount
    {
        return $value !== null ? Amount::fromInt((int) $value) : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value instanceof Amount) {
            return $value->toInt();
        }

        if (is_int($value)) {
            return $value;
        }

        return null;
    }
}
