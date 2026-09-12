<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Support;

/**
 * Pure-integer conversion of a decimal VAT rate to basis points.
 *
 * Avoids casting the decimal string through (float), which can introduce
 * binary floating-point imprecision (e.g. 19.25 * 100 not landing exactly
 * on 1925) into a calculation that feeds monetary totals.
 */
final class VatCalculator
{
    /**
     * Converts a decimal VAT rate (e.g. "19.25") into integer basis points (1925).
     */
    public static function basisPoints(string|int|float $vatRate): int
    {
        $numeric = match (true) {
            is_int($vatRate)   => (string) $vatRate,
            is_float($vatRate) => rtrim(rtrim(sprintf('%.10F', $vatRate), '0'), '.'),
            default            => trim($vatRate),
        };

        if ($numeric === '' || $numeric === '-') {
            $numeric = '0';
        }

        return (int) bcmul($numeric, '100', 0);
    }
}
