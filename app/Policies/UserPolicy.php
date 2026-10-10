<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    public function view(User $user, User $model): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isAdmin()) {
            return ! $model->isSuperAdmin();
        }

        return $user->id === $model->id;
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isManager() && $this->view($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        return $this->update($user, $model) && $user->id !== $model->id;
    }
}
