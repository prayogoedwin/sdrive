<?php

namespace App\Enums;

enum AccessRequestStatus: string
{
    case Pending = 'pending';
    case Granted = 'granted';
    case Denied = 'denied';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Granted => 'Diberikan',
            self::Denied => 'Ditolak',
        };
    }
}
