<?php

namespace App\Services;

use App\Enums\DriveSpace;
use App\Enums\SharePermission;
use App\Models\DriveItem;
use App\Models\DriveShare;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DriveAccess
{
    public function canView(User $user, DriveItem $item): bool
    {
        return $this->permission($user, $item) !== null;
    }

    public function canEdit(User $user, DriveItem $item): bool
    {
        return $this->permission($user, $item) === SharePermission::Edit;
    }

    public function permission(User $user, DriveItem $item): ?SharePermission
    {
        if ($user->isManager() || (int) $item->owner_id === (int) $user->id || $item->isCommon()) {
            return SharePermission::Edit;
        }

        $ids = $item->ancestorIds();
        $ids[] = $item->id;

        $permissions = DriveShare::query()
            ->where('user_id', $user->id)
            ->whereIn('drive_item_id', $ids)
            ->pluck('permission');

        return $this->strongest($permissions);
    }

    /**
     * @param  Collection<int, SharePermission>  $shares
     */
    public function permissionFromMap(User $user, DriveItem $item, Collection $shares): ?SharePermission
    {
        if ($user->isManager() || (int) $item->owner_id === (int) $user->id || $item->isCommon()) {
            return SharePermission::Edit;
        }

        $matched = collect($item->ancestorIds())
            ->push($item->id)
            ->map(fn (int $id) => $shares->get($id))
            ->filter();

        return $this->strongest($matched);
    }

    /**
     * @param  Builder<DriveItem>  $query
     * @return Builder<DriveItem>
     */
    public function scopeVisible(Builder $query, User $user): Builder
    {
        if ($user->isManager()) {
            return $query;
        }

        $sharedIds = DriveShare::query()
            ->where('user_id', $user->id)
            ->pluck('drive_item_id');

        return $query->where(function (Builder $inner) use ($user, $sharedIds): void {
            $inner->where('owner_id', $user->id)
                ->orWhere('space', DriveSpace::Common->value);

            if ($sharedIds->isEmpty()) {
                return;
            }

            $inner->orWhereIn('id', $sharedIds);

            foreach ($sharedIds as $id) {
                $id = (int) $id;
                $inner->orWhere(function (Builder $path) use ($id): void {
                    $path->where('path', (string) $id)
                        ->orWhere('path', 'like', $id.'/%')
                        ->orWhere('path', 'like', '%/'.$id.'/%')
                        ->orWhere('path', 'like', '%/'.$id);
                });
            }
        });
    }

    /**
     * @return Collection<int, SharePermission>
     */
    public function shareMap(User $user): Collection
    {
        return DriveShare::query()
            ->where('user_id', $user->id)
            ->get()
            ->mapWithKeys(fn (DriveShare $share): array => [(int) $share->drive_item_id => $share->permission]);
    }

    /**
     * @param  Collection<int, mixed>  $permissions
     */
    private function strongest(Collection $permissions): ?SharePermission
    {
        $normalized = $permissions->map(function (mixed $permission): ?SharePermission {
            if ($permission instanceof SharePermission) {
                return $permission;
            }

            return is_string($permission) ? SharePermission::tryFrom($permission) : null;
        })->filter();

        if ($normalized->contains(SharePermission::Edit)) {
            return SharePermission::Edit;
        }

        if ($normalized->isNotEmpty()) {
            return SharePermission::View;
        }

        return null;
    }
}
