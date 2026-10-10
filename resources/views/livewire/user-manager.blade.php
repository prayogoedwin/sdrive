<x-shell>
    <div class="h-full overflow-y-auto px-4 py-6 md:px-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Pengguna</h1>
                <p class="mt-1 text-sm text-mist">Verifikasi pendaftaran dan kelola akun. Role Super Admin tidak bisa dibuat dari sini.</p>
            </div>
            <button type="button" class="btn-primary" wire:click="create">Pengguna baru</button>
        </div>

        @if ($notice)
            <p class="mt-4 rounded-xl border border-accent/30 bg-accent/10 px-3 py-2 text-sm text-accent">{{ $notice }}</p>
        @endif
        @if ($formError)
            <p class="mt-4 rounded-xl border border-rose-400/30 bg-rose-400/10 px-3 py-2 text-sm text-rose-300">{{ $formError }}</p>
        @endif

        <div class="mt-6 flex flex-wrap gap-3">
            <input class="field max-w-xs" type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama atau email">
            <select class="field w-40" wire:model.live="roleFilter">
                <option value="">Semua role</option>
                <option value="admin">Admin</option>
                <option value="user">Pengguna</option>
            </select>
            <select class="field w-52" wire:model.live="statusFilter">
                <option value="">Semua status</option>
                <option value="pending">Menunggu verifikasi</option>
                <option value="active">Aktif</option>
                <option value="suspended">Nonaktif</option>
            </select>
        </div>

        <div class="mt-4 overflow-hidden rounded-2xl border border-line">
            <table class="w-full text-left text-sm">
                <thead class="bg-raised text-xs uppercase tracking-wide text-mist">
                    <tr>
                        <th class="px-4 py-3 font-medium">Nama</th>
                        <th class="hidden px-4 py-3 font-medium md:table-cell">Role</th>
                        <th class="hidden px-4 py-3 font-medium lg:table-cell">Status</th>
                        <th class="hidden px-4 py-3 font-medium xl:table-cell">Drive saya</th>
                        <th class="px-4 py-3 font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $person)
                        <tr class="border-t border-line" wire:key="user-{{ $person->id }}">
                            <td class="px-4 py-3">
                                <span class="block font-medium">{{ $person->displayNameFor(auth()->user()) }}</span>
                                @if ($email = $person->displayEmailFor(auth()->user()))
                                    <span class="text-xs text-mist">{{ $email }}</span>
                                @endif
                            </td>
                            <td class="hidden px-4 py-3 md:table-cell">{{ $person->isSuperAdmin() && auth()->user()->isAdmin() ? 'Super Admin' : $person->role->label() }}</td>
                            <td class="hidden px-4 py-3 lg:table-cell">
                                <span @class([
                                    'rounded-full px-2 py-1 text-xs',
                                    'bg-amber-400/15 text-amber-200' => $person->status->value === 'pending',
                                    'bg-accent/15 text-accent' => $person->status->value === 'active',
                                    'bg-rose-400/15 text-rose-200' => $person->status->value === 'suspended',
                                ])>{{ $person->status->label() }}</span>
                            </td>
                            <td class="hidden px-4 py-3 text-mist xl:table-cell">{{ \App\Support\ByteFormatter::human((int) $person->storage_used) }} / {{ \App\Support\ByteFormatter::human((int) $person->storage_quota) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    @if ($person->status->value === 'pending')
                                        <button type="button" class="btn-ghost" wire:click="approve({{ $person->id }})">Verifikasi</button>
                                    @endif
                                    @if ($person->id !== auth()->id() && $person->status->value !== 'suspended')
                                        <button type="button" class="btn-ghost" wire:click="suspend({{ $person->id }})">Nonaktifkan</button>
                                    @endif
                                    <button type="button" class="btn-ghost" wire:click="edit({{ $person->id }})">Ubah</button>
                                    @if ($person->id !== auth()->id())
                                        <button type="button" class="btn-danger" wire:click="delete({{ $person->id }})" wire:confirm="Hapus pengguna ini?">Hapus</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $users->links() }}</div>
    </div>

    @if ($modal)
        <x-modal :title="$editingId ? 'Ubah pengguna' : 'Pengguna baru'">
            <form wire:submit="save" class="flex flex-col gap-4">
                <label class="flex flex-col gap-2 text-sm">
                    <span class="text-mist">Nama</span>
                    <input class="field" wire:model="name">
                    @error('name') <span class="text-rose-300">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-2 text-sm">
                    <span class="text-mist">Email</span>
                    <input class="field" type="email" wire:model="email">
                    @error('email') <span class="text-rose-300">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-2 text-sm">
                    <span class="text-mist">{{ $editingId ? 'Kata sandi baru (opsional)' : 'Kata sandi' }}</span>
                    <input class="field" type="password" wire:model="password">
                    @error('password') <span class="text-rose-300">{{ $message }}</span> @enderror
                </label>
                @if ($editingSuperAdmin)
                    <p class="rounded-xl border border-line px-3 py-2 text-sm text-mist">Role: Super Admin. Role ini tidak bisa diubah dari formulir.</p>
                @else
                    <label class="flex flex-col gap-2 text-sm">
                        <span class="text-mist">Role</span>
                        <select class="field" wire:model="role">
                            <option value="admin">Admin</option>
                            <option value="user">Pengguna</option>
                        </select>
                        @error('role') <span class="text-rose-300">{{ $message }}</span> @enderror
                    </label>
                @endif
                <label class="flex flex-col gap-2 text-sm">
                    <span class="text-mist">Batas penyimpanan (GB)</span>
                    <input class="field" type="number" min="1" max="10240" step="1" wire:model="quotaGb">
                    @error('quotaGb') <span class="text-rose-300">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-2 text-sm">
                    <span class="text-mist">Status</span>
                    <select class="field" wire:model="status" @disabled($editingId === auth()->id())>
                        <option value="pending">Menunggu verifikasi</option>
                        <option value="active">Aktif</option>
                        <option value="suspended">Nonaktif</option>
                    </select>
                    @error('status') <span class="text-rose-300">{{ $message }}</span> @enderror
                </label>
                <button class="btn-primary" type="submit">Simpan</button>
            </form>
        </x-modal>
    @endif
</x-shell>
