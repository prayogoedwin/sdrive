<?php

namespace App\Livewire;

use App\Enums\AccessRequestStatus;
use App\Enums\DriveSpace;
use App\Enums\SharePermission;
use App\Models\DriveAccessRequest;
use App\Models\DriveItem;
use App\Models\DriveShare;
use App\Models\User;
use App\Services\DriveAccess;
use App\Services\DrivePresenter;
use App\Services\DriveService;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.drive')]
class DriveBrowser extends Component
{
    use WithFileUploads;

    public string $section = 'my-drive';

    public ?int $folderId = null;

    public string $layout = 'grid';

    public string $sort = 'name';

    public string $direction = 'asc';

    public string $search = '';

    public ?int $selectedId = null;

    public bool $detailsOpen = true;

    public bool $folderModal = false;

    public string $folderName = '';

    public bool $renameModal = false;

    public ?int $renameId = null;

    public string $renameName = '';

    public bool $shareModal = false;

    public ?int $shareItemId = null;

    public string $shareEmail = '';

    public string $sharePermission = 'view';

    public string $linkAccess = 'off';

    public bool $moveModal = false;

    public ?int $moveItemId = null;

    public ?int $pickerFolderId = null;

    public string $pickerSpace = 'personal';

    public bool $ownerModal = false;

    public ?int $ownerItemId = null;

    public ?int $newOwnerId = null;

    public bool $previewOpen = false;

    /** @var array<int, mixed> */
    public array $uploads = [];

    public string $notice = '';

    public function mount(): void
    {
        $section = (string) request()->query('section', 'my-drive');
        if (in_array($section, $this->sections(), true)) {
            $this->section = $section;
        }

        $folder = request()->integer('folder');
        $this->folderId = $folder > 0 ? $folder : null;
        $this->search = (string) request()->query('q', '');

        if (! in_array($this->section, $this->folderSections(), true)) {
            $this->folderId = null;
        }

        $this->ensureFolderAccess();
        $this->alignSectionToFolder();
        $this->openFromQuery();
    }

    public function showSection(string $section): void
    {
        abort_unless(in_array($section, $this->sections(), true), 404);
        $this->section = $section;
        $this->folderId = null;
        $this->search = '';
        $this->clearSelection();
        $this->notice = '';
        $this->syncUrl();
    }

    public function openItem(int $id): void
    {
        $item = $this->visibleItem($id);
        app(DriveService::class)->recordOpen(auth()->user(), $item);

        if ($item->isFolder()) {
            $this->enterFolder($item);

            return;
        }

        $this->selectedId = $item->id;
        $this->previewOpen = true;
        $this->detailsOpen = true;
    }

    public function selectItem(int $id): void
    {
        $item = $this->visibleItem($id, $this->section === 'trash');
        $this->selectedId = $item->id;
        $this->detailsOpen = true;
        $this->previewOpen = false;
    }

    public function goToFolder(?int $id): void
    {
        $this->folderId = $id;
        $this->search = '';
        $this->clearSelection();
        $this->ensureFolderAccess();

        if ($id === null && $this->section === 'shared') {
            $this->section = 'shared';
        }

        $this->syncUrl();
    }

    public function setLayout(string $layout): void
    {
        abort_unless(in_array($layout, ['grid', 'list'], true), 404);
        $this->layout = $layout;
    }

    public function setSort(string $sort): void
    {
        abort_unless(in_array($sort, ['name', 'updated_at', 'size'], true), 404);
        if ($this->sort === $sort) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sort = $sort;
        $this->direction = 'asc';
    }

    public function openFolderModal(): void
    {
        abort_unless($this->canCreateHere(), 403);
        $this->folderName = '';
        $this->folderModal = true;
    }

    public function createFolder(DriveService $drive): void
    {
        abort_unless($this->canCreateHere(), 403);
        $this->validate([
            'folderName' => ['required', 'string', 'max:255'],
        ], [
            'folderName.required' => 'Nama folder wajib diisi.',
        ]);

        $drive->createFolder(auth()->user(), $this->writableParentId(), $this->folderName, 'folderName', $this->targetSpace());
        $this->folderModal = false;
        $this->folderName = '';
        $this->notice = 'Folder dibuat.';
    }

    public function updatedUploads(DriveService $drive): void
    {
        abort_unless($this->canCreateHere(), 403);

        $this->validate([
            'uploads' => ['array', 'max:20'],
            'uploads.*' => ['file', 'max:'.config('sdrive.max_upload_kb')],
        ], [
            'uploads.max' => 'Maksimal 20 file sekaligus.',
            'uploads.*.max' => 'Ukuran file melebihi batas unggah.',
        ]);

        $count = 0;
        foreach ($this->uploads as $upload) {
            $drive->storeUpload(auth()->user(), $this->writableParentId(), $upload, $this->targetSpace());
            $count++;
        }

        $this->reset('uploads');
        $this->notice = $count.' file diunggah.';
    }

    public function startRename(int $id): void
    {
        $item = $this->visibleItem($id);
        $this->authorize('update', $item);
        $this->renameId = $item->id;
        $this->renameName = $item->name;
        $this->renameModal = true;
    }

    public function rename(DriveService $drive): void
    {
        $item = $this->visibleItem((int) $this->renameId);
        $this->validate([
            'renameName' => ['required', 'string', 'max:255'],
        ]);
        $drive->rename(auth()->user(), $item, $this->renameName, 'renameName');
        $this->renameModal = false;
        $this->notice = 'Nama diperbarui.';
    }

    public function startShare(int $id): void
    {
        $item = $this->visibleItem($id);
        $this->authorize('share', $item);
        $this->shareItemId = $item->id;
        $this->shareEmail = '';
        $this->sharePermission = 'view';
        $this->linkAccess = $item->link_permission?->value ?? 'off';
        $this->shareModal = true;
    }

    public function setLinkAccess(string $access, DriveService $drive): void
    {
        abort_unless(in_array($access, ['off', 'view', 'edit'], true), 422);
        $item = $this->visibleItem((int) $this->shareItemId);
        $permission = $access === 'off' ? null : SharePermission::from($access);
        $updated = $drive->setLinkAccess(auth()->user(), $item, $permission);
        $this->linkAccess = $updated->link_permission?->value ?? 'off';
        $this->notice = $permission === null ? 'Tautan dibagikan dimatikan.' : 'Tautan siap dibagikan.';
    }

    public function requestEdit(int $id, DriveService $drive): void
    {
        $item = $this->visibleItem($id);
        $drive->requestEdit(auth()->user(), $item);
        $this->notice = 'Permintaan akses terkirim ke pemilik.';
    }

    public function share(DriveService $drive): void
    {
        $item = $this->visibleItem((int) $this->shareItemId);
        $this->validate([
            'shareEmail' => ['required', 'email'],
            'sharePermission' => ['required', Rule::in(['view', 'edit'])],
        ], [
            'shareEmail.required' => 'Email penerima wajib diisi.',
        ]);

        $drive->share(
            auth()->user(),
            $item,
            $this->shareEmail,
            SharePermission::from($this->sharePermission),
            'shareEmail',
        );
        $this->shareEmail = '';
        $this->notice = 'Akses dibagikan.';
    }

    public function updateSharePermission(int $shareId, string $permission, DriveService $drive): void
    {
        abort_unless(in_array($permission, ['view', 'edit'], true), 422);
        $share = DriveShare::query()->findOrFail($shareId);
        $drive->updateShare(auth()->user(), $share, SharePermission::from($permission));
    }

    public function revokeShare(int $shareId, DriveService $drive): void
    {
        $share = DriveShare::query()->findOrFail($shareId);
        $drive->revokeShare(auth()->user(), $share);
        $this->notice = 'Akses dicabut.';
    }

    public function startMove(int $id): void
    {
        $item = $this->visibleItem($id);
        $this->authorize('update', $item);
        $this->moveItemId = $item->id;
        $this->pickerFolderId = null;
        $this->pickerSpace = $item->isCommon() ? DriveSpace::Common->value : DriveSpace::Personal->value;
        $this->moveModal = true;
    }

    public function enterPicker(int $id): void
    {
        $folder = DriveItem::query()->findOrFail($id);
        $this->authorize('view', $folder);
        abort_unless($folder->isFolder(), 404);
        $this->pickerFolderId = $folder->id;
    }

    public function pickerUp(): void
    {
        if ($this->pickerFolderId === null) {
            return;
        }

        $folder = DriveItem::query()->find($this->pickerFolderId);
        if ($folder?->parent_id) {
            $this->pickerFolderId = $folder->parent_id;

            return;
        }

        $this->pickerSpace = $folder?->space?->value ?? DriveSpace::Personal->value;
        $this->pickerFolderId = null;
    }

    public function pickSpace(string $space): void
    {
        abort_unless(in_array($space, [DriveSpace::Personal->value, DriveSpace::Common->value], true), 404);
        $this->pickerSpace = $space;
        $this->pickerFolderId = null;
    }

    public function confirmMove(DriveService $drive): void
    {
        $item = $this->visibleItem((int) $this->moveItemId);
        $drive->move(
            auth()->user(),
            $item,
            $this->pickerFolderId,
            $this->pickerFolderId === null ? DriveSpace::from($this->pickerSpace) : null,
        );
        $this->moveModal = false;
        $this->notice = 'Item dipindahkan.';
    }

    public function startOwner(int $id): void
    {
        $item = $this->visibleItem($id);
        $this->authorize('changeOwner', $item);
        $this->ownerItemId = $item->id;
        $this->newOwnerId = $item->owner_id;
        $this->ownerModal = true;
    }

    public function changeOwner(DriveService $drive): void
    {
        $item = $this->visibleItem((int) $this->ownerItemId);
        $this->validate([
            'newOwnerId' => ['required', 'integer'],
        ]);
        $drive->changeOwner(auth()->user(), $item, (int) $this->newOwnerId);
        $this->ownerModal = false;
        $this->notice = 'Kepemilikan diperbarui.';
    }

    public function toggleStar(int $id, DriveService $drive): void
    {
        $item = $this->visibleItem($id, $this->section === 'trash');
        $drive->toggleStar(auth()->user(), $item);
    }

    public function trash(int $id, DriveService $drive): void
    {
        $item = $this->visibleItem($id);
        $drive->trash(auth()->user(), $item);
        if ($this->selectedId === $id) {
            $this->clearSelection();
        }
        if ($this->folderId === $id) {
            $this->folderId = $item->parent_id;
        }
        $this->notice = 'Dipindahkan ke sampah.';
    }

    public function restore(int $id, DriveService $drive): void
    {
        $item = DriveItem::onlyTrashed()->findOrFail($id);
        $drive->restore(auth()->user(), $item);
        $this->notice = 'Item dipulihkan.';
    }

    public function forceDelete(int $id, DriveService $drive): void
    {
        $item = DriveItem::withTrashed()->findOrFail($id);
        $drive->forceDelete(auth()->user(), $item);
        if ($this->selectedId === $id) {
            $this->clearSelection();
        }
        $this->notice = 'Item dihapus permanen.';
    }

    public function emptyTrash(DriveService $drive): void
    {
        $drive->emptyTrash(auth()->user());
        $this->clearSelection();
        $this->notice = 'Sampah dikosongkan.';
    }

    public function closeModals(): void
    {
        $this->folderModal = false;
        $this->renameModal = false;
        $this->shareModal = false;
        $this->moveModal = false;
        $this->ownerModal = false;
        $this->previewOpen = false;
    }

    public function render(DriveService $drive, DrivePresenter $presenter, DriveAccess $access)
    {
        $user = auth()->user();
        $items = $drive->browse($user, $this->section, $this->folderId, $this->search, $this->sort, $this->direction);
        $shares = $access->shareMap($user);
        $pendingIds = $this->pendingRequestIds($user);
        $inTrash = $this->section === 'trash' && $this->search === '';
        $crumbs = $this->crumbModels();
        $sharedItem = $this->shareModal && $this->shareItemId
            ? DriveItem::query()->find($this->shareItemId)
            : null;

        return view('livewire.drive-browser', [
            'nodes' => $presenter->many($items, $user, $shares, $inTrash, $pendingIds),
            'crumbs' => $crumbs,
            'heading' => $this->heading($crumbs),
            'canCreate' => $this->canCreateHere(),
            'inTrash' => $inTrash,
            'details' => $this->details($presenter, $user, $shares, $inTrash, $pendingIds),
            'shareRows' => $this->shareRows(),
            'linkUrl' => $sharedItem?->link_token ? route('drive.link', $sharedItem->link_token) : '',
            'pickerFolders' => $this->pickerFolders(),
            'pickerName' => $this->pickerLabel(),
            'ownerChoices' => $this->ownerChoices(),
            'preview' => $this->previewPayload($presenter, $user, $shares, $pendingIds),
        ])->layout('layouts.drive', [
            'title' => $this->heading($crumbs).' — SDrive',
        ]);
    }

    private function enterFolder(DriveItem $item): void
    {
        $user = auth()->user();
        $this->folderId = $item->id;
        $this->search = '';
        $this->clearSelection();

        if ($item->isCommon()) {
            $this->section = 'common';
        } elseif (! $user->isManager() && (int) $item->owner_id !== (int) $user->id) {
            $this->section = 'shared';
        } elseif (! in_array($this->section, $this->folderSections(), true)) {
            $this->section = 'my-drive';
        }

        $this->syncUrl();
    }

    private function visibleItem(int $id, bool $trashed = false): DriveItem
    {
        $query = $trashed ? DriveItem::onlyTrashed() : DriveItem::query();
        $item = $query->findOrFail($id);
        $this->authorize('view', $item);

        return $item;
    }

    private function ensureFolderAccess(): void
    {
        if ($this->folderId === null) {
            return;
        }

        $folder = DriveItem::query()->find($this->folderId);
        if (! $folder || ! $folder->isFolder()) {
            abort(404);
        }

        $this->authorize('view', $folder);
    }

    private function canCreateHere(): bool
    {
        if ($this->search !== '' || ! in_array($this->section, $this->folderSections(), true)) {
            return false;
        }

        if ($this->folderId === null) {
            return in_array($this->section, ['my-drive', 'common'], true);
        }

        $folder = DriveItem::query()->find($this->folderId);

        return $folder !== null && auth()->user()->can('createIn', $folder);
    }

    private function writableParentId(): ?int
    {
        return $this->folderId;
    }

    /**
     * @return Collection<int, DriveItem>
     */
    private function crumbModels(): Collection
    {
        if ($this->folderId === null || $this->search !== '') {
            return collect();
        }

        $folder = DriveItem::query()->find($this->folderId);
        if (! $folder) {
            return collect();
        }

        return app(DriveService::class)
            ->breadcrumbs($folder)
            ->filter(fn (DriveItem $item): bool => auth()->user()->can('view', $item))
            ->values();
    }

    /**
     * @param  Collection<int, DriveItem>  $crumbs
     */
    private function heading(Collection $crumbs): string
    {
        if ($this->search !== '') {
            return 'Hasil pencarian';
        }

        if ($crumbs->isNotEmpty()) {
            return (string) $crumbs->last()->name;
        }

        return match ($this->section) {
            'common' => 'Drive Bersama',
            'shared' => 'Dibagikan ke saya',
            'recent' => 'Terbaru',
            'starred' => 'Berbintang',
            'trash' => 'Sampah',
            default => auth()->user()->isManager() ? 'Semua file' : 'Drive saya',
        };
    }

    /**
     * @param  Collection<int, SharePermission>  $shares
     * @param  Collection<int, int>  $pendingIds
     * @return array<string, mixed>|null
     */
    private function details(DrivePresenter $presenter, User $user, Collection $shares, bool $inTrash, Collection $pendingIds): ?array
    {
        if ($this->selectedId === null) {
            return null;
        }

        $query = $inTrash ? DriveItem::onlyTrashed() : DriveItem::query();
        $item = $query->with(['owner:id,name,email,role,status', 'creator:id,name,email,role,status'])->withCount('shares')->find($this->selectedId);
        if (! $item || ! $user->can('view', $item)) {
            return null;
        }

        $item->starred_by_me = $item->stars()->where('user_id', $user->id)->exists();

        return $presenter->one($item, $user, $shares, $inTrash, $pendingIds);
    }

    /**
     * @return Collection<int, DriveShare>
     */
    private function shareRows(): Collection
    {
        if (! $this->shareModal || $this->shareItemId === null) {
            return collect();
        }

        return DriveShare::query()
            ->with('user:id,name,email,role,status')
            ->where('drive_item_id', $this->shareItemId)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, DriveItem>
     */
    private function pickerFolders(): Collection
    {
        if (! $this->moveModal) {
            return collect();
        }

        $user = auth()->user();
        $query = DriveItem::query()->where('type', 'folder')->orderBy('name');

        if ($this->pickerFolderId) {
            $folder = DriveItem::query()->findOrFail($this->pickerFolderId);
            $this->authorize('view', $folder);
            $query->where('parent_id', $folder->id);
        } else {
            $query->whereNull('parent_id')->where('space', $this->pickerSpace);
            if ($this->pickerSpace === DriveSpace::Personal->value && ! $user->isManager()) {
                $editable = DriveShare::query()
                    ->where('user_id', $user->id)
                    ->where('permission', SharePermission::Edit)
                    ->pluck('drive_item_id');
                $query->where(function ($inner) use ($user, $editable): void {
                    $inner->where('owner_id', $user->id);
                    if ($editable->isNotEmpty()) {
                        $inner->orWhereIn('id', $editable);
                    }
                });
            }
        }

        $moving = $this->moveItemId ? DriveItem::query()->find($this->moveItemId) : null;

        return $query->get()->reject(function (DriveItem $folder) use ($moving): bool {
            if (! $moving) {
                return false;
            }

            if ($folder->id === $moving->id) {
                return true;
            }

            return in_array($moving->id, $folder->ancestorIds(), true);
        })->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function ownerChoices(): Collection
    {
        if (! $this->ownerModal) {
            return collect();
        }

        return User::query()
            ->visibleTo(auth()->user())
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'status']);
    }

    /**
     * @param  Collection<int, SharePermission>  $shares
     * @param  Collection<int, int>  $pendingIds
     * @return array<string, mixed>|null
     */
    private function previewPayload(DrivePresenter $presenter, User $user, Collection $shares, Collection $pendingIds): ?array
    {
        if (! $this->previewOpen || $this->selectedId === null) {
            return null;
        }

        $item = DriveItem::query()->with(['owner:id,name,email,role,status'])->withCount('shares')->find($this->selectedId);
        if (! $item || $item->isFolder() || ! $user->can('view', $item)) {
            return null;
        }

        $item->starred_by_me = false;

        return $presenter->one($item, $user, $shares, false, $pendingIds);
    }

    /**
     * @return list<string>
     */
    private function sections(): array
    {
        return ['my-drive', 'common', 'shared', 'recent', 'starred', 'trash'];
    }

    /**
     * @return list<string>
     */
    private function folderSections(): array
    {
        return ['my-drive', 'common', 'shared'];
    }

    private function alignSectionToFolder(): void
    {
        if ($this->folderId === null) {
            return;
        }

        $folder = DriveItem::query()->find($this->folderId);
        if (! $folder) {
            return;
        }

        if ($folder->isCommon()) {
            $this->section = 'common';

            return;
        }

        if ($this->section !== 'common') {
            return;
        }

        $user = auth()->user();
        $this->section = ! $user->isManager() && (int) $folder->owner_id !== (int) $user->id
            ? 'shared'
            : 'my-drive';
    }

    private function targetSpace(): DriveSpace
    {
        if ($this->folderId !== null) {
            return DriveSpace::Personal;
        }

        return $this->section === 'common' ? DriveSpace::Common : DriveSpace::Personal;
    }

    private function pickerLabel(): string
    {
        if ($this->pickerFolderId) {
            return DriveItem::query()->find($this->pickerFolderId)?->name ?? 'Folder';
        }

        if ($this->pickerSpace === DriveSpace::Common->value) {
            return 'Drive Bersama';
        }

        return auth()->user()->isManager() ? 'Semua file' : 'Drive saya';
    }

    /**
     * @return Collection<int, int>
     */
    private function pendingRequestIds(User $user): Collection
    {
        return DriveAccessRequest::query()
            ->where('user_id', $user->id)
            ->where('status', AccessRequestStatus::Pending)
            ->pluck('drive_item_id')
            ->map(fn ($id): int => (int) $id);
    }

    private function openFromQuery(): void
    {
        $open = request()->integer('open');
        if ($open < 1) {
            return;
        }

        $item = DriveItem::query()->find($open);
        if (! $item || ! auth()->user()->can('view', $item)) {
            return;
        }

        $this->openItem($item->id);
    }

    private function clearSelection(): void
    {
        $this->selectedId = null;
        $this->previewOpen = false;
    }

    private function syncUrl(): void
    {
        $params = ['section' => $this->section];
        if ($this->folderId) {
            $params['folder'] = $this->folderId;
        }

        $this->js('history.replaceState({}, "", '.json_encode(route('drive', $params)).')');
    }
}
