<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\DriveItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UserAdminService
{
    /**
     * @param  array{name: string, email: string, password?: string|null, role: string, status: string, storage_quota?: int}  $data
     */
    public function create(User $actor, array $data): User
    {
        Gate::forUser($actor)->authorize('create', User::class);
        $role = $this->assignableRole($data['role']);
        $status = AccountStatus::from($data['status']);

        return User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $role,
            'status' => $status,
            'storage_quota' => $this->quotaBytes($data['storage_quota'] ?? (int) config('sdrive.quota_bytes')),
            'email_verified_at' => $status === AccountStatus::Active ? now() : null,
        ]);
    }

    /**
     * @param  array{name: string, email: string, password?: string|null, role?: string|null, status: string, storage_quota?: int}  $data
     */
    public function update(User $actor, User $target, array $data): User
    {
        Gate::forUser($actor)->authorize('update', $target);

        $status = AccountStatus::from($data['status']);
        if ($actor->id === $target->id && $status !== $target->status) {
            throw ValidationException::withMessages([
                'status' => 'Anda tidak dapat mengubah status akun sendiri.',
            ]);
        }

        $target->name = $data['name'];
        $target->email = $data['email'];
        $target->status = $status;

        if ($status === AccountStatus::Active && $target->email_verified_at === null) {
            $target->email_verified_at = now();
        }

        if (! $target->isSuperAdmin() && isset($data['role'])) {
            $target->role = $this->assignableRole($data['role']);
        }

        if (! empty($data['password'])) {
            $target->password = $data['password'];
        }

        if (isset($data['storage_quota'])) {
            $target->storage_quota = $this->quotaBytes((int) $data['storage_quota']);
        }

        $target->save();

        return $target;
    }

    public function setStatus(User $actor, User $target, AccountStatus $status): void
    {
        Gate::forUser($actor)->authorize('update', $target);

        if ($actor->id === $target->id) {
            throw ValidationException::withMessages([
                'status' => 'Anda tidak dapat mengubah status akun sendiri.',
            ]);
        }

        $target->status = $status;
        if ($status === AccountStatus::Active && $target->email_verified_at === null) {
            $target->email_verified_at = now();
        }
        $target->save();
    }

    public function delete(User $actor, User $target): void
    {
        Gate::forUser($actor)->authorize('delete', $target);

        $ownsFiles = DriveItem::withTrashed()->where('owner_id', $target->id)->exists();
        if ($ownsFiles) {
            throw ValidationException::withMessages([
                'user' => 'Pindahkan dulu kepemilikan folder dan file pengguna ini.',
            ]);
        }

        DB::transaction(function () use ($target): void {
            $target->delete();
        });
    }

    private function quotaBytes(int $bytes): int
    {
        $gigabyte = 1024 * 1024 * 1024;

        if ($bytes < $gigabyte || $bytes > 10240 * $gigabyte) {
            throw ValidationException::withMessages([
                'quotaGb' => 'Batas penyimpanan antara 1 dan 10240 GB.',
            ]);
        }

        return $bytes;
    }

    private function assignableRole(string $role): UserRole
    {
        $parsed = UserRole::tryFrom($role);

        if ($parsed === null || $parsed === UserRole::SuperAdmin) {
            throw ValidationException::withMessages([
                'role' => 'Role Super Admin tidak dapat diberikan.',
            ]);
        }

        return $parsed;
    }
}
