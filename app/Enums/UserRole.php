<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::User => 'Pengguna',
        };
    }

    /**
     * Roles that may be chosen in user management.
     *
     * @return list<string>
     */
    public static function assignable(): array
    {
        return [self::Admin->value, self::User->value];
    }
}
