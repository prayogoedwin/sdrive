<?php

namespace App\Enums;

enum AccountStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu verifikasi',
            self::Active => 'Aktif',
            self::Suspended => 'Nonaktif',
        };
    }
}
