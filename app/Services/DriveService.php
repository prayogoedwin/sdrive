<?php

namespace App\Services;

use App\Enums\AccessRequestStatus;
use App\Enums\AccountStatus;
use App\Enums\DriveItemType;
use App\Enums\DriveSpace;
use App\Enums\SharePermission;
use App\Enums\UserRole;
use App\Models\DriveAccessRequest;
use App\Models\DriveItem;
use App\Models\DriveOpen;
use App\Models\DriveShare;
use App\Models\User;
use App\Notifications\EditAccessRequested;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class DriveService
{
    public function __construct(private DriveAccess $access) {}

    /**
     * @return Collection<int, DriveItem>
     */
    public function browse(User $user, string $section, ?int $folderId, string $search, string $sort, string $direction): Collection
    {
        $sort = in_array($sort, ['name', 'updated_at', 'size'], true) ? $sort : 'name';
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        if (trim($search) !== '') {
            return $this->search($user, trim($search), $sort, $direction);
        }

        if ($section === 'trash') {
            return $this->trashedItems($user, $sort, $direction);
        }

        if ($section === 'starred') {
            $query = $this->baseQuery($user)->whereHas('stars', fn (Builder $q) => $q->where('user_id', $user->id));
            $this->access->scopeVisible($query, $user);

            return $this->ordered($query, $sort, $direction)->limit(200)->get();
        }

        if ($section === 'recent') {
            return $this->recent($user);
        }

        if ($section === 'shared' && $folderId === null) {
            return $this->sharedRoots($user, $sort, $direction);
        }

        if ($folderId !== null) {
            $folder = DriveItem::query()->findOrFail($folderId);
            $this->allow($user, 'view', $folder);

            return $this->ordered(
                $this->baseQuery($user)->where('parent_id', $folder->id),
                $sort,
                $direction,
            )->limit(200)->get();
        }

        if ($section === 'common') {
            return $this->ordered(
                $this->baseQuery($user)->whereNull('parent_id')->where('space', DriveSpace::Common->value),
                $sort,
                $direction,
            )->limit(200)->get();
        }

        $query = $this->baseQuery($user)->whereNull('parent_id')->where('space', DriveSpace::Personal->value);

        if (! $user->isManager()) {
            $query->where('owner_id', $user->id);
        }

        return $this->ordered($query, $sort, $direction)->limit(200)->get();
    }

    public function createFolder(User $actor, ?int $parentId, string $name, string $errorKey = 'name', DriveSpace $space = DriveSpace::Personal): DriveItem
    {
        $parent = $this->resolveParent($actor, $parentId);
        $space = $this->inheritedSpace($parent, $space);
        $name = $this->cleanName($name, $errorKey);

        if ($this->nameTaken($parent?->id, $name, null, $space)) {
            throw ValidationException::withMessages([
                $errorKey => 'Nama sudah dipakai di folder ini.',
            ]);
        }

        return DriveItem::query()->create([
            'parent_id' => $parent?->id,
            'owner_id' => $actor->id,
            'created_by' => $actor->id,
            'name' => $name,
            'type' => DriveItemType::Folder,
            'space' => $space,
            'size' => 0,
            'path' => $parent?->pathForChild(),
        ]);
    }

    public function storeUpload(User $actor, ?int $parentId, UploadedFile $file, DriveSpace $space = DriveSpace::Personal): DriveItem
    {
        $parent = $this->resolveParent($actor, $parentId);
        $space = $this->inheritedSpace($parent, $space);
        if ($space === DriveSpace::Personal) {
            $this->assertQuota($actor, (int) $file->getSize());
        }
        $this->assertServerDisk((int) $file->getSize());

        $original = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $original = str_replace(["\0", '/', '\\'], '', $original);
        if ($original === '' || $original === '.' || $original === '..') {
            $original = 'berkas';
        }

        $name = $this->uniqueName($parent?->id, $original, $space);
        $extension = strtolower((string) preg_replace('/[^a-z0-9]+/', '', (string) $file->getClientOriginalExtension()));
        $storedName = (string) Str::uuid().($extension !== '' ? '.'.$extension : '');
        $path = $file->storeAs('drive/'.$actor->id, $storedName, 'local');

        try {
            return DriveItem::query()->create([
                'parent_id' => $parent?->id,
                'owner_id' => $actor->id,
                'created_by' => $actor->id,
                'name' => $name,
                'type' => DriveItemType::File,
                'space' => $space,
                'mime_type' => $file->getMimeType(),
                'extension' => $extension !== '' ? $extension : null,
                'size' => (int) $file->getSize(),
                'storage_path' => $path,
                'path' => $parent?->pathForChild(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }

    public function createTextFile(User $actor, ?int $parentId, string $name, string $contents, DriveSpace $space = DriveSpace::Personal): DriveItem
    {
        $parent = $this->resolveParent($actor, $parentId);
        $space = $this->inheritedSpace($parent, $space);
        $name = $this->uniqueName($parent?->id, $this->cleanName($name, 'name'), $space);
        $bytes = strlen($contents);
        if ($space === DriveSpace::Personal) {
            $this->assertQuota($actor, $bytes);
        }
        $this->assertServerDisk($bytes);
        $path = 'drive/'.$actor->id.'/'.Str::uuid().'.txt';
        Storage::disk('local')->put($path, $contents);

        return DriveItem::query()->create([
            'parent_id' => $parent?->id,
            'owner_id' => $actor->id,
            'created_by' => $actor->id,
            'name' => $name,
            'type' => DriveItemType::File,
            'space' => $space,
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size' => $bytes,
            'storage_path' => $path,
            'path' => $parent?->pathForChild(),
        ]);
    }

    public function rename(User $actor, DriveItem $item, string $name, string $errorKey = 'name'): void
    {
        $this->allow($actor, 'update', $item);
        $name = $this->cleanName($name, $errorKey);

        if ($this->nameTaken($item->parent_id, $name, $item->id, $item->space ?? DriveSpace::Personal)) {
            throw ValidationException::withMessages([
                $errorKey => 'Nama sudah dipakai di folder ini.',
            ]);
        }

        $item->name = $name;
        $item->save();
    }

    public function move(User $actor, DriveItem $item, ?int $parentId, ?DriveSpace $rootSpace = null): void
    {
        $this->allow($actor, 'update', $item);

        $parent = null;
        if ($parentId !== null) {
            $parent = DriveItem::query()->findOrFail($parentId);
            if (! $parent->isFolder()) {
                throw ValidationException::withMessages(['move' => 'Tujuan harus berupa folder.']);
            }
            $this->allow($actor, 'createIn', $parent);
            $ancestors = $parent->ancestorIds();
            $ancestors[] = $parent->id;
            if (in_array($item->id, $ancestors, true)) {
                throw ValidationException::withMessages([
                    'move' => 'Folder tidak bisa dipindah ke dalam dirinya sendiri.',
                ]);
            }
        }

        $destination = $parent?->space ?? ($rootSpace ?? DriveSpace::Personal);

        if ($destination === DriveSpace::Personal && ! $actor->isManager() && (int) $item->owner_id !== (int) $actor->id) {
            throw ValidationException::withMessages([
                'move' => $item->isCommon()
                    ? 'Hanya pemilik atau admin yang dapat mengeluarkan item dari Drive Bersama.'
                    : 'Hanya pemilik yang dapat memindahkan item ke Drive saya.',
            ]);
        }

        if ($item->parent_id === $parent?->id && $item->space === $destination) {
            return;
        }

        if ($this->nameTaken($parent?->id, $item->name, $item->id, $destination)) {
            throw ValidationException::withMessages([
                'move' => 'Nama sudah dipakai di folder tujuan.',
            ]);
        }

        if ($destination === DriveSpace::Personal && $item->space !== DriveSpace::Personal) {
            $this->assertIncomingPersonalQuota($item, 'move');
        }

        DB::transaction(function () use ($item, $parent, $destination): void {
            $item->parent_id = $parent?->id;
            $item->space = $destination;
            $item->save();
            $this->recomputeSubtree($item->fresh() ?? $item);
        });
    }

    public function trash(User $actor, DriveItem $item): void
    {
        $this->allow($actor, 'delete', $item);

        if ($item->trashed()) {
            return;
        }

        $batch = (string) Str::uuid();
        $ids = $this->subtreeIds($item);

        DriveItem::query()->whereIn('id', $ids)->update([
            'deleted_at' => now(),
            'trash_batch' => $batch,
        ]);
    }

    public function restore(User $actor, DriveItem $item): void
    {
        $this->allow($actor, 'restore', $item);

        if (! $item->trashed()) {
            return;
        }

        DB::transaction(function () use ($item): void {
            $rows = DriveItem::onlyTrashed()
                ->when(
                    $item->trash_batch,
                    fn (Builder $query) => $query->where('trash_batch', $item->trash_batch),
                    fn (Builder $query) => $query->whereKey($item->id),
                )
                ->get();

            foreach ($rows as $row) {
                $row->deleted_at = null;
                $row->trash_batch = null;
                $row->save();
            }

            $root = DriveItem::query()->findOrFail($item->id);
            $parent = $root->parent_id ? DriveItem::withTrashed()->find($root->parent_id) : null;
            if ($parent === null || $parent->trashed()) {
                $root->parent_id = null;
                $root->path = null;
                $root->save();
            }

            $this->recomputeSubtree($root->fresh() ?? $root);
        });
    }

    public function forceDelete(User $actor, DriveItem $item): void
    {
        $this->allow($actor, 'forceDelete', $item);

        $ids = $this->subtreeIds($item, true);
        $nodes = DriveItem::withTrashed()
            ->whereIn('id', $ids)
            ->get()
            ->sortByDesc(fn (DriveItem $node): int => strlen((string) $node->path));

        DB::transaction(function () use ($nodes): void {
            foreach ($nodes as $node) {
                if ($node->isFile() && $node->storage_path) {
                    Storage::disk('local')->delete($node->storage_path);
                }
                $node->forceDelete();
            }
        });
    }

    public function emptyTrash(User $actor): void
    {
        $roots = $this->trashedItems($actor, 'name', 'asc');

        foreach ($roots as $root) {
            if (! Gate::forUser($actor)->allows('forceDelete', $root)) {
                continue;
            }

            $this->forceDelete($actor, $root);
        }
    }

    public function toggleStar(User $actor, DriveItem $item): void
    {
        $this->allow($actor, 'view', $item);

        $already = $item->stars()->where('user_id', $actor->id)->exists();

        if ($already) {
            $item->stars()->detach($actor->id);

            return;
        }

        $item->stars()->attach($actor->id);
    }

    public function share(User $actor, DriveItem $item, string $email, SharePermission $permission, string $errorKey = 'shareEmail'): DriveShare
    {
        $this->allow($actor, 'share', $item);

        $recipient = User::query()
            ->when(! $actor->isSuperAdmin(), fn (Builder $query) => $query->where('role', '!=', UserRole::SuperAdmin->value))
            ->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])
            ->first();

        if (! $recipient || $recipient->status !== AccountStatus::Active) {
            throw ValidationException::withMessages([
                $errorKey => 'Pengguna aktif tidak ditemukan.',
            ]);
        }

        if ($recipient->id === $actor->id) {
            throw ValidationException::withMessages([
                $errorKey => 'Anda tidak perlu berbagi ke akun sendiri.',
            ]);
        }

        if ($recipient->id === $item->owner_id) {
            throw ValidationException::withMessages([
                $errorKey => 'Pemilik sudah memiliki akses penuh.',
            ]);
        }

        return DriveShare::query()->updateOrCreate(
            ['drive_item_id' => $item->id, 'user_id' => $recipient->id],
            ['shared_by' => $actor->id, 'permission' => $permission],
        );
    }

    public function updateShare(User $actor, DriveShare $share, SharePermission $permission): void
    {
        $item = $share->item()->firstOrFail();
        $this->allow($actor, 'share', $item);
        $share->permission = $permission;
        $share->save();
    }

    public function revokeShare(User $actor, DriveShare $share): void
    {
        $item = $share->item()->firstOrFail();
        $this->allow($actor, 'share', $item);
        $share->delete();
    }

    public function setLinkAccess(User $actor, DriveItem $item, ?SharePermission $permission): DriveItem
    {
        $this->allow($actor, 'share', $item);

        if ($permission === null) {
            $item->link_permission = null;
            $item->link_token = null;
            $item->save();

            return $item;
        }

        if ($item->link_token === null || $item->link_token === '') {
            $item->link_token = Str::random(48);
        }

        $item->link_permission = $permission;
        $item->save();

        return $item;
    }

    public function redeemLink(User $actor, string $token): DriveItem
    {
        $item = DriveItem::query()
            ->where('link_token', $token)
            ->whereNotNull('link_permission')
            ->first();

        if (! $item) {
            abort(404);
        }

        if ($actor->isManager() || (int) $item->owner_id === (int) $actor->id) {
            return $item;
        }

        $existing = DriveShare::query()
            ->where('drive_item_id', $item->id)
            ->where('user_id', $actor->id)
            ->first();

        $permission = $existing?->permission === SharePermission::Edit
            ? SharePermission::Edit
            : $item->link_permission;

        DriveShare::query()->updateOrCreate(
            ['drive_item_id' => $item->id, 'user_id' => $actor->id],
            ['shared_by' => $item->owner_id, 'permission' => $permission],
        );

        return $item;
    }

    public function requestEdit(User $actor, DriveItem $item): DriveAccessRequest
    {
        $this->allow($actor, 'view', $item);

        if ($this->access->canEdit($actor, $item)) {
            throw ValidationException::withMessages([
                'access' => 'Anda sudah dapat mengubah item ini.',
            ]);
        }

        $existing = DriveAccessRequest::query()
            ->where('drive_item_id', $item->id)
            ->where('user_id', $actor->id)
            ->where('status', AccessRequestStatus::Pending)
            ->first();

        if ($existing) {
            return $existing;
        }

        $request = DriveAccessRequest::query()->create([
            'drive_item_id' => $item->id,
            'user_id' => $actor->id,
            'status' => AccessRequestStatus::Pending,
        ]);

        $item->owner?->notify(new EditAccessRequested($request));

        return $request;
    }

    public function grantAccess(User $actor, DriveAccessRequest $request): void
    {
        $this->resolveAccess($actor, $request, AccessRequestStatus::Granted);
    }

    public function denyAccess(User $actor, DriveAccessRequest $request): void
    {
        $this->resolveAccess($actor, $request, AccessRequestStatus::Denied);
    }

    public function changeOwner(User $actor, DriveItem $item, int $newOwnerId): void
    {
        $this->allow($actor, 'changeOwner', $item);

        $newOwner = User::query()->visibleTo($actor)->whereKey($newOwnerId)->first();

        if (! $newOwner || $newOwner->status !== AccountStatus::Active) {
            throw ValidationException::withMessages([
                'newOwnerId' => 'Pilih pemilik yang aktif.',
            ]);
        }

        if ($newOwner->id === $item->owner_id) {
            return;
        }

        $ids = $this->subtreeIds($item, true);
        $incoming = (int) DriveItem::withTrashed()
            ->whereIn('id', $ids)
            ->where('owner_id', $item->owner_id)
            ->where('type', DriveItemType::File)
            ->where('space', DriveSpace::Personal->value)
            ->sum('size');
        $this->assertQuota($newOwner, $incoming, 'newOwnerId');

        DriveItem::withTrashed()
            ->whereIn('id', $ids)
            ->where('owner_id', $item->owner_id)
            ->update(['owner_id' => $newOwner->id]);
    }

    public function recordOpen(User $actor, DriveItem $item): void
    {
        DriveOpen::query()->updateOrCreate(
            ['user_id' => $actor->id, 'drive_item_id' => $item->id],
            ['opened_at' => now()],
        );
    }

    public function usage(User $user): int
    {
        return (int) DriveItem::withTrashed()
            ->where('owner_id', $user->id)
            ->where('type', DriveItemType::File)
            ->where('space', DriveSpace::Personal->value)
            ->sum('size');
    }

    public function download(User $actor, DriveItem $item): BinaryFileResponse|StreamedResponse
    {
        $this->allow($actor, 'view', $item);
        $this->recordOpen($actor, $item);

        if ($item->isFile()) {
            $path = $this->storedPath($item);

            return Storage::disk('local')->download($path, $item->name);
        }

        return $this->downloadFolder($item);
    }

    public function preview(User $actor, DriveItem $item): BinaryFileResponse
    {
        $this->allow($actor, 'view', $item);
        abort_unless($item->isFile(), 404);
        $this->recordOpen($actor, $item);
        $path = $this->storedPath($item);
        $safe = str_replace(['"', '\\'], '', $item->name);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $item->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$safe.'"',
        ]);
    }

    /**
     * @return Collection<int, DriveItem>
     */
    public function breadcrumbs(DriveItem $folder): Collection
    {
        $ids = $folder->ancestorIds();
        $ids[] = $folder->id;
        $nodes = DriveItem::query()->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)
            ->map(fn (int $id) => $nodes->get($id))
            ->filter()
            ->values();
    }

    /**
     * @param  Builder<DriveItem>  $query
     * @return Builder<DriveItem>
     */
    private function ordered(Builder $query, string $sort, string $direction): Builder
    {
        return $query
            ->orderByRaw('case when type = ? then 0 else 1 end', [DriveItemType::Folder->value])
            ->orderBy($sort, $direction);
    }

    /**
     * @return Builder<DriveItem>
     */
    private function baseQuery(User $user): Builder
    {
        return DriveItem::query()
            ->with(['owner:id,name,email,role,status'])
            ->withCount('shares')
            ->withExists(['stars as starred_by_me' => fn (Builder $query) => $query->where('user_id', $user->id)]);
    }

    /**
     * @return Collection<int, DriveItem>
     */
    private function search(User $user, string $term, string $sort, string $direction): Collection
    {
        $escaped = str_replace(['%', '_'], ['\%', '\_'], $term);
        $query = $this->baseQuery($user)->where('name', 'like', '%'.$escaped.'%');
        $this->access->scopeVisible($query, $user);

        return $this->ordered($query, $sort, $direction)->limit(50)->get();
    }

    /**
     * @return Collection<int, DriveItem>
     */
    private function trashedItems(User $user, string $sort, string $direction): Collection
    {
        $query = $this->baseQuery($user)->onlyTrashed()->where(function (Builder $inner): void {
            $inner->whereNull('parent_id')
                ->orWhereNotExists(function ($sub): void {
                    $sub->selectRaw('1')
                        ->from('drive_items as drive_parents')
                        ->whereColumn('drive_parents.id', 'drive_items.parent_id')
                        ->whereNotNull('drive_parents.deleted_at');
                });
        });

        if (! $user->isManager()) {
            $this->access->scopeVisible($query, $user);
        }

        return $this->ordered($query, $sort, $direction)->limit(200)->get();
    }

    /**
     * @return Collection<int, DriveItem>
     */
    private function recent(User $user): Collection
    {
        $opens = DriveOpen::query()
            ->where('user_id', $user->id)
            ->orderByDesc('opened_at')
            ->limit(40)
            ->get();

        $items = $this->baseQuery($user)
            ->whereIn('id', $opens->pluck('drive_item_id'))
            ->get()
            ->keyBy('id');

        $map = $this->access->shareMap($user);

        return $opens
            ->map(fn (DriveOpen $open) => $items->get($open->drive_item_id))
            ->filter(fn (?DriveItem $item): bool => $item !== null && $this->access->permissionFromMap($user, $item, $map) !== null)
            ->values();
    }

    /**
     * @return Collection<int, DriveItem>
     */
    private function sharedRoots(User $user, string $sort, string $direction): Collection
    {
        $sharedIds = DriveShare::query()->where('user_id', $user->id)->pluck('drive_item_id')->map(fn ($id): int => (int) $id);
        $items = $this->baseQuery($user)
            ->whereIn('id', $sharedIds)
            ->where('owner_id', '!=', $user->id)
            ->where('space', '!=', DriveSpace::Common->value)
            ->get()
            ->filter(function (DriveItem $item) use ($sharedIds): bool {
                foreach ($item->ancestorIds() as $ancestorId) {
                    if ($sharedIds->contains($ancestorId)) {
                        return false;
                    }
                }

                return true;
            });

        $value = function (DriveItem $item) use ($sort): int|string {
            if ($sort === 'updated_at') {
                return $item->updated_at?->getTimestamp() ?? 0;
            }

            if ($sort === 'size') {
                return (int) $item->size;
            }

            return mb_strtolower($item->name);
        };

        return $items
            ->sort(function (DriveItem $left, DriveItem $right) use ($direction, $value): int {
                $folder = ($left->isFolder() ? 0 : 1) <=> ($right->isFolder() ? 0 : 1);
                if ($folder !== 0) {
                    return $folder;
                }

                $result = $value($left) <=> $value($right);

                return $direction === 'desc' ? -$result : $result;
            })
            ->values();
    }

    private function resolveParent(User $actor, ?int $parentId): ?DriveItem
    {
        if ($parentId === null) {
            return null;
        }

        $parent = DriveItem::query()->findOrFail($parentId);

        if (! $parent->isFolder()) {
            throw ValidationException::withMessages(['folder' => 'Tujuan harus berupa folder.']);
        }

        $this->allow($actor, 'createIn', $parent);

        return $parent;
    }

    private function cleanName(string $name, string $errorKey): string
    {
        $name = trim($name);

        if ($name === '' || $name === '.' || $name === '..' || str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, "\0")) {
            throw ValidationException::withMessages([$errorKey => 'Nama tidak valid.']);
        }

        if (mb_strlen($name) > 255) {
            throw ValidationException::withMessages([$errorKey => 'Nama terlalu panjang.']);
        }

        return $name;
    }

    private function inheritedSpace(?DriveItem $parent, DriveSpace $requested): DriveSpace
    {
        return $parent?->space ?? $requested;
    }

    private function nameTaken(?int $parentId, string $name, ?int $ignoreId = null, DriveSpace $space = DriveSpace::Personal): bool
    {
        return DriveItem::query()
            ->inFolder($parentId)
            ->when($parentId === null, fn (Builder $query) => $query->where('space', $space->value))
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    private function uniqueName(?int $parentId, string $name, DriveSpace $space = DriveSpace::Personal): string
    {
        $candidate = $name;
        $index = 1;

        while ($this->nameTaken($parentId, $candidate, null, $space)) {
            $index++;
            $dot = mb_strrpos($name, '.');
            $candidate = $dot !== false && $dot > 0
                ? mb_substr($name, 0, $dot).' ('.$index.')'.mb_substr($name, $dot)
                : $name.' ('.$index.')';
        }

        return $candidate;
    }

    private function assertQuota(User $owner, int $incoming, string $errorKey = 'uploads'): void
    {
        if ($incoming < 1) {
            return;
        }

        if ($this->usage($owner) + $incoming > (int) $owner->storage_quota) {
            throw ValidationException::withMessages([
                $errorKey => 'Kuota penyimpanan tidak cukup.',
            ]);
        }
    }

    private function assertIncomingPersonalQuota(DriveItem $item, string $errorKey): void
    {
        $totals = DriveItem::withTrashed()
            ->whereIn('id', $this->subtreeIds($item, true))
            ->where('type', DriveItemType::File)
            ->where('space', '!=', DriveSpace::Personal->value)
            ->selectRaw('owner_id, SUM(size) as bytes')
            ->groupBy('owner_id')
            ->get();

        foreach ($totals as $row) {
            $owner = User::query()->find($row->owner_id);
            if ($owner) {
                $this->assertQuota($owner, (int) $row->bytes, $errorKey);
            }
        }
    }

    private function assertServerDisk(int $incoming): void
    {
        if ($incoming < 1) {
            return;
        }

        $free = disk_free_space(Storage::disk('local')->path(''));
        if ($free !== false && $incoming > $free) {
            throw ValidationException::withMessages([
                'uploads' => 'Penyimpanan server penuh.',
            ]);
        }
    }

    /**
     * @return list<int>
     */
    private function subtreeIds(DriveItem $item, bool $withTrashed = false): array
    {
        $prefix = $item->pathForChild();
        $query = $withTrashed ? DriveItem::withTrashed() : DriveItem::query();
        $descendantIds = $query
            ->where(function (Builder $inner) use ($prefix): void {
                $inner->where('path', $prefix)->orWhere('path', 'like', $prefix.'/%');
            })
            ->pluck('id')
            ->all();

        return array_values(array_unique(array_merge([$item->id], $descendantIds)));
    }

    private function recomputeSubtree(DriveItem $root): void
    {
        $this->syncPathFromParent($root);
        $root->refresh();
        $queue = [$root->id];

        while ($queue !== []) {
            $currentId = array_shift($queue);
            $current = DriveItem::withTrashed()->find($currentId);
            if (! $current) {
                continue;
            }

            $children = DriveItem::withTrashed()->where('parent_id', $current->id)->get();
            foreach ($children as $child) {
                $child->path = $current->pathForChild();
                $child->space = $current->space;
                $child->save();
                $queue[] = $child->id;
            }
        }
    }

    private function syncPathFromParent(DriveItem $item): void
    {
        if ($item->parent_id === null) {
            $item->path = null;
            $item->save();

            return;
        }

        $parent = DriveItem::query()->find($item->parent_id);
        if (! $parent) {
            $item->parent_id = null;
            $item->path = null;
            $item->save();

            return;
        }

        $item->path = $parent->pathForChild();
        $item->save();
    }

    private function resolveAccess(User $actor, DriveAccessRequest $request, AccessRequestStatus $status): void
    {
        $item = $request->item()->firstOrFail();

        if ((int) $item->owner_id !== (int) $actor->id) {
            abort(403);
        }

        if ($request->status !== AccessRequestStatus::Pending) {
            return;
        }

        if ($status === AccessRequestStatus::Granted) {
            DriveShare::query()->updateOrCreate(
                ['drive_item_id' => $item->id, 'user_id' => $request->user_id],
                ['shared_by' => $actor->id, 'permission' => SharePermission::Edit],
            );
        }

        $request->status = $status;
        $request->resolved_by = $actor->id;
        $request->resolved_at = now();
        $request->save();

        $actor->unreadNotifications()->get()->each(function ($notification) use ($request): void {
            if ((int) ($notification->data['request_id'] ?? 0) === (int) $request->id) {
                $notification->markAsRead();
            }
        });
    }

    private function allow(User $actor, string $ability, mixed $target): void
    {
        Gate::forUser($actor)->authorize($ability, $target);
    }

    private function storedPath(DriveItem $item): string
    {
        $path = (string) $item->storage_path;

        if ($path === '' || str_contains($path, '..') || ! str_starts_with($path, 'drive/') || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return $path;
    }

    private function downloadFolder(DriveItem $folder): BinaryFileResponse
    {
        if (! class_exists(ZipArchive::class)) {
            abort(500, 'Zip tidak tersedia di server.');
        }

        $directory = storage_path('app/private/tmp');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $zipPath = $directory.'/'.Str::uuid().'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $files = DriveItem::query()
            ->where('type', DriveItemType::File)
            ->where(function (Builder $inner) use ($folder): void {
                $prefix = $folder->pathForChild();
                $inner->where('path', $prefix)->orWhere('path', 'like', $prefix.'/%');
            })
            ->get();

        if ($files->isEmpty()) {
            $zip->addFromString('kosong.txt', 'Folder ini kosong.');
        }

        foreach ($files as $file) {
            if (! $file->storage_path || ! Storage::disk('local')->exists($file->storage_path)) {
                continue;
            }
            $zip->addFile(Storage::disk('local')->path($file->storage_path), $this->zipName($folder, $file));
        }

        $zip->close();

        return response()->download($zipPath, $folder->name.'.zip')->deleteFileAfterSend(true);
    }

    private function zipName(DriveItem $root, DriveItem $file): string
    {
        $chain = array_values(array_filter(
            $file->ancestorIds(),
            fn (int $id): bool => $id !== $root->id && ! in_array($id, $root->ancestorIds(), true),
        ));
        $names = DriveItem::query()->whereIn('id', $chain)->pluck('name', 'id');
        $parts = [];
        foreach ($chain as $id) {
            $parts[] = str_replace(['/', '\\'], '-', (string) ($names[$id] ?? 'folder'));
        }
        $parts[] = str_replace(['/', '\\'], '-', $file->name);

        return implode('/', $parts);
    }
}
