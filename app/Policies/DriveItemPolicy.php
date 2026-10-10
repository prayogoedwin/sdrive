<?php

namespace App\Policies;

use App\Models\DriveItem;
use App\Models\User;
use App\Services\DriveAccess;

class DriveItemPolicy
{
    public function __construct(private DriveAccess $access) {}

    public function view(User $user, DriveItem $item): bool
    {
        return $this->access->canView($user, $item);
    }

    public function update(User $user, DriveItem $item): bool
    {
        return $this->access->canEdit($user, $item);
    }

    public function delete(User $user, DriveItem $item): bool
    {
        return $this->access->canEdit($user, $item);
    }

    public function createIn(User $user, DriveItem $item): bool
    {
        return $item->isFolder() && $this->access->canEdit($user, $item);
    }

    public function share(User $user, DriveItem $item): bool
    {
        return $this->access->canEdit($user, $item);
    }

    public function changeOwner(User $user, DriveItem $item): bool
    {
        return $user->isManager();
    }

    public function restore(User $user, DriveItem $item): bool
    {
        return $user->isManager() || (int) $item->owner_id === (int) $user->id || $this->access->canEdit($user, $item);
    }

    public function forceDelete(User $user, DriveItem $item): bool
    {
        return $user->isManager() || (int) $item->owner_id === (int) $user->id;
    }
}
