<?php

namespace App\Support;

class InventoryQuantity
{
    public const MAX = 999_999_999_999;

    public const UNSIGNED_RULE = 'regex:/\A[0-9]{1,9}(?:[.,][0-9]{1,3})?\z/';

    public const SIGNED_RULE = 'regex:/\A-?[0-9]{1,9}(?:[.,][0-9]{1,3})?\z/';

    public static function parse(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', str_replace(',', '.', ltrim($value, '-')), 2), 2, '');
        $quantity = (int) $whole * 1000 + (int) str_pad($fraction, 3, '0');

        return str_starts_with($value, '-') ? -$quantity : $quantity;
    }

    public static function format(int $quantity): string
    {
        $absolute = abs($quantity);
        $whole = (string) intdiv($absolute, 1000);
        $fraction = rtrim(str_pad((string) ($absolute % 1000), 3, '0', STR_PAD_LEFT), '0');

        return ($quantity < 0 ? '-' : '').($fraction === '' ? $whole : $whole.','.$fraction);
    }
}
