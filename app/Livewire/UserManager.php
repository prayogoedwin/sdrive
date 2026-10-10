<?php

namespace App\Livewire;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\UserAdminService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.drive')]
class UserManager extends Component
{
    use WithPagination;

    public string $search = '';

    public string $roleFilter = '';

    public string $statusFilter = '';

    public bool $modal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = 'user';

    public string $status = 'active';

    public string $quotaGb = '15';

    public string $notice = '';

    public string $formError = '';

    public function mount(): void
    {
        $this->notice = (string) session('users.notice', '');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRoleFilter(string $value): void
    {
        if (! in_array($value, ['', 'admin', 'user'], true)) {
            $this->roleFilter = '';
        }
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('create', User::class);
        $this->resetForm();
        $this->modal = true;
    }

    public function edit(int $userId): void
    {
        $target = $this->findVisible($userId);
        $this->authorize('update', $target);
        $this->editingId = $target->id;
        $this->name = $target->name;
        $this->email = $target->email;
        $this->password = '';
        $this->role = $target->isSuperAdmin() ? UserRole::SuperAdmin->value : $target->role->value;
        $this->status = $target->status->value;
        $this->quotaGb = (string) self::gigabytes((int) $target->storage_quota);
        $this->formError = '';
        $this->modal = true;
    }

    public function save(UserAdminService $users): void
    {
        $actor = auth()->user();
        $target = $this->editingId ? $this->findVisible($this->editingId) : null;
        $editingSuperAdmin = $target?->isSuperAdmin() ?? false;

        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'password' => [$this->editingId ? 'nullable' : 'required', 'string', 'min:8'],
            'status' => ['required', Rule::in(['pending', 'active', 'suspended'])],
            'quotaGb' => ['required', 'integer', 'min:1', 'max:10240'],
        ];

        if (! $editingSuperAdmin) {
            $rules['role'] = ['required', Rule::in(UserRole::assignable())];
        }

        $data = $this->validate($rules, [
            'role.in' => 'Role Super Admin tidak dapat diberikan.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'quotaGb.required' => 'Batas penyimpanan wajib diisi.',
            'quotaGb.integer' => 'Batas penyimpanan diisi dalam satuan GB, bilangan bulat.',
            'quotaGb.min' => 'Batas penyimpanan minimal 1 GB.',
            'quotaGb.max' => 'Batas penyimpanan maksimal 10240 GB.',
        ]);

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'] ?? null,
            'status' => $data['status'],
            'storage_quota' => ((int) $data['quotaGb']) * 1024 * 1024 * 1024,
        ];

        if (! $editingSuperAdmin) {
            $payload['role'] = $data['role'];
        }

        if ($target) {
            $users->update($actor, $target, $payload);
            $message = 'Pengguna diperbarui.';
        } else {
            $this->authorize('create', User::class);
            $users->create($actor, $payload);
            $message = 'Pengguna dibuat.';
        }

        session()->flash('users.notice', $message);
        $this->redirect(route('users.index'));
    }

    public function approve(int $userId, UserAdminService $users): void
    {
        $users->setStatus(auth()->user(), $this->findVisible($userId), AccountStatus::Active);
        $this->notice = 'Pengguna diverifikasi.';
    }

    public function suspend(int $userId, UserAdminService $users): void
    {
        $users->setStatus(auth()->user(), $this->findVisible($userId), AccountStatus::Suspended);
        $this->notice = 'Pengguna dinonaktifkan.';
    }

    public function delete(int $userId, UserAdminService $users): void
    {
        try {
            $users->delete(auth()->user(), $this->findVisible($userId));
            $this->notice = 'Pengguna dihapus.';
        } catch (ValidationException $exception) {
            $this->formError = $exception->validator->errors()->first() ?: 'Pengguna tidak bisa dihapus.';
        }
    }

    public function closeModals(): void
    {
        $this->modal = false;
        $this->resetForm();
    }

    public function render()
    {
        $actor = auth()->user();

        $users = User::query()
            ->visibleTo($actor)
            ->withSum('ownedFiles as storage_used', 'size')
            ->when($this->search !== '', function ($query): void {
                $term = str_replace(['%', '_'], ['\%', '\_'], $this->search);
                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', '%'.$term.'%')
                        ->orWhere('email', 'like', '%'.$term.'%');
                });
            })
            ->when(in_array($this->roleFilter, UserRole::assignable(), true), fn ($query) => $query->where('role', $this->roleFilter))
            ->when(in_array($this->statusFilter, ['pending', 'active', 'suspended'], true), fn ($query) => $query->where('status', $this->statusFilter))
            ->orderBy('name')
            ->paginate(10);

        $editing = $this->editingId ? User::query()->visibleTo($actor)->find($this->editingId) : null;

        return view('livewire.user-manager', [
            'users' => $users,
            'editingSuperAdmin' => $editing?->isSuperAdmin() ?? false,
        ])->layout('layouts.drive', [
            'title' => 'Pengguna — SDrive',
        ]);
    }

    private static function gigabytes(int $bytes): int
    {
        return max(1, (int) round($bytes / (1024 * 1024 * 1024)));
    }

    private function findVisible(int $userId): User
    {
        return User::query()->visibleTo(auth()->user())->findOrFail($userId);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->role = 'user';
        $this->status = 'active';
        $this->quotaGb = (string) self::gigabytes((int) config('sdrive.quota_bytes'));
        $this->formError = '';
        $this->resetValidation();
    }
}
