<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\DriveSpace;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'status', 'storage_quota'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => AccountStatus::class,
            'storage_quota' => 'integer',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isManager(): bool
    {
        return $this->isSuperAdmin() || $this->isAdmin();
    }

    public function isActive(): bool
    {
        return $this->status === AccountStatus::Active;
    }

    public function displayNameFor(User $actor): string
    {
        if ($this->isSuperAdmin() && $actor->isAdmin()) {
            return 'Super Admin';
        }

        return $this->name;
    }

    public function displayEmailFor(User $actor): ?string
    {
        if ($this->isSuperAdmin() && $actor->isAdmin()) {
            return null;
        }

        return $this->email;
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        if ($actor->isSuperAdmin()) {
            return $query;
        }

        if ($actor->isAdmin()) {
            return $query->where('role', '!=', UserRole::SuperAdmin->value);
        }

        return $query->whereKey($actor->id);
    }

    public function ownedItems(): HasMany
    {
        return $this->hasMany(DriveItem::class, 'owner_id');
    }

    public function ownedFiles(): HasMany
    {
        return $this->hasMany(DriveItem::class, 'owner_id')
            ->withTrashed()
            ->where('type', 'file')
            ->where('space', DriveSpace::Personal->value);
    }
}
