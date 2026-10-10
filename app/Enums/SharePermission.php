<?php

namespace App\Enums;

enum SharePermission: string
{
    case View = 'view';
    case Edit = 'edit';

    public function label(): string
    {
        return match ($this) {
            self::View => 'Dapat melihat',
            self::Edit => 'Dapat mengubah',
        };
    }
}
