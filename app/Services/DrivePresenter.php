<?php

namespace App\Services;

use App\Enums\SharePermission;
use App\Models\DriveItem;
use App\Models\User;
use App\Support\ByteFormatter;
use Illuminate\Support\Collection;

class DrivePresenter
{
    public function __construct(private DriveAccess $access, private OnlyOfficeEditor $office) {}

    /**
     * @param  Collection<int, DriveItem>  $items
     * @param  Collection<int, SharePermission>  $shares
     * @param  Collection<int, int>  $pendingRequestIds
     * @return list<array<string, mixed>>
     */
    public function many(Collection $items, User $actor, Collection $shares, bool $inTrash = false, ?Collection $pendingRequestIds = null): array
    {
        $pendingRequestIds ??= collect();

        return $items
            ->map(fn (DriveItem $item): array => $this->one($item, $actor, $shares, $inTrash, $pendingRequestIds))
            ->all();
    }

    /**
     * @param  Collection<int, SharePermission>  $shares
     * @param  Collection<int, int>  $pendingRequestIds
     * @return array<string, mixed>
     */
    public function one(DriveItem $item, User $actor, Collection $shares, bool $inTrash = false, ?Collection $pendingRequestIds = null): array
    {
        $pendingRequestIds ??= collect();
        $permission = $this->access->permissionFromMap($actor, $item, $shares);
        $canEdit = ! $inTrash && $permission === SharePermission::Edit;
        $canRequest = ! $inTrash && $permission === SharePermission::View;
        $kind = $this->kind($item);

        return [
            'id' => $item->id,
            'name' => $item->name,
            'folder' => $item->isFolder(),
            'kind' => $kind,
            'size' => $item->isFolder() ? '—' : ByteFormatter::human((int) $item->size),
            'updated' => $item->updated_at?->timezone(config('app.timezone'))->translatedFormat('d M Y, H:i'),
            'owner' => $item->owner?->displayNameFor($actor) ?? '—',
            'owner_email' => $item->owner?->displayEmailFor($actor),
            'creator' => $item->relationLoaded('creator') ? $item->creator?->displayNameFor($actor) : null,
            'starred' => (bool) $item->starred_by_me,
            'shared' => ($item->shares_count ?? 0) > 0,
            'common' => $item->isCommon(),
            'can_edit' => $canEdit,
            'can_request' => $canRequest,
            'request_pending' => $canRequest && $pendingRequestIds->contains($item->id),
            'can_share' => $canEdit,
            'can_owner' => ! $inTrash && $actor->can('changeOwner', $item),
            'can_delete' => $canEdit,
            'can_restore' => $inTrash && $actor->can('restore', $item),
            'can_force' => $inTrash && $actor->can('forceDelete', $item),
            'office' => $item->isFile() && $this->office->enabled() && $this->office->supports($item)
                ? route('office.show', $item)
                : null,
            'preview' => $item->isFile() ? route('drive.preview', $item) : null,
            'download' => route('drive.download', $item),
            'mime' => $item->mime_type,
        ];
    }

    public function kind(DriveItem $item): string
    {
        if ($item->isFolder()) {
            return 'folder';
        }

        $mime = (string) $item->mime_type;
        $ext = strtolower((string) $item->extension);

        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            $mime === 'application/pdf' || $ext === 'pdf' => 'pdf',
            in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'], true) => 'archive',
            in_array($ext, ['xls', 'xlsx', 'csv', 'ods'], true) => 'sheet',
            in_array($ext, ['ppt', 'pptx', 'odp'], true) => 'slide',
            in_array($ext, ['doc', 'docx', 'odt', 'rtf'], true) => 'doc',
            str_starts_with($mime, 'text/') || in_array($ext, ['txt', 'md', 'json'], true) => 'text',
            default => 'file',
        };
    }
}
