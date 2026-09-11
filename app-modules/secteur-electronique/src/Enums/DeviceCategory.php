<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Enums;

enum DeviceCategory: string
{
    case Smartphone = 'smartphone';
    case Laptop     = 'laptop';
    case Tv         = 'tv';
    case Tablet     = 'tablet';
    case Audio      = 'audio';
    case Accessory  = 'accessory';
    case Other      = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Smartphone => 'Smartphone / Téléphone',
            self::Laptop     => 'Ordinateur portable',
            self::Tv         => 'Téléviseur',
            self::Tablet     => 'Tablette',
            self::Audio      => 'Audio / Son',
            self::Accessory  => 'Accessoire',
            self::Other      => 'Autre',
        };
    }

    /** Returns true for categories that require IMEI tracking by default. */
    public function requiresImei(): bool
    {
        return match ($this) {
            self::Smartphone, self::Tablet => true,
            default                        => false,
        };
    }
}
