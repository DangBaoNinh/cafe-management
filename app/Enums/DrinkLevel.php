<?php

namespace App\Enums;

enum DrinkLevel: string
{
    case None = 'none';
    case Less = 'less';
    case Standard = 'standard';
    case Extra = 'extra';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Không',
            self::Less => 'Ít',
            self::Standard => 'Tiêu chuẩn',
            self::Extra => 'Nhiều',
        };
    }
}
