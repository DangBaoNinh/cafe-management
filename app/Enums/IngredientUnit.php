<?php

namespace App\Enums;

enum IngredientUnit: string
{
    case Gram = 'g';
    case Milliliter = 'ml';
    case Piece = 'piece';

    public function label(): string
    {
        return match ($this) {
            self::Gram => 'g',
            self::Milliliter => 'ml',
            self::Piece => 'cái',
        };
    }
}
