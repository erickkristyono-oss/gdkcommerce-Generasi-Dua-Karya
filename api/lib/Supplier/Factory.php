<?php
declare(strict_types=1);

namespace Supplier;

final class Factory
{
    private static ?SupplierInterface $instance = null;

    public static function make(): SupplierInterface
    {
        if (self::$instance) return self::$instance;
        $driver = config('supplier.driver', 'mock');
        switch ($driver) {
            case 'digiflazz':
                return self::$instance = new DigiflazzSupplier(config('supplier.digiflazz', []));
            case 'mock':
                return self::$instance = new MockSupplier();
            default:
                throw new \RuntimeException("Supplier driver tidak dikenal: {$driver}");
        }
    }
}
