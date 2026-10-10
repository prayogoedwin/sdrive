@php
    $tones = [
        'folder' => 'bg-accent/10 text-accent',
        'image' => 'bg-emerald-400/10 text-emerald-300',
        'video' => 'bg-fuchsia-400/10 text-fuchsia-300',
        'audio' => 'bg-pink-400/10 text-pink-300',
        'pdf' => 'bg-rose-400/10 text-rose-300',
        'archive' => 'bg-amber-400/10 text-amber-300',
        'sheet' => 'bg-lime-400/10 text-lime-300',
        'doc' => 'bg-sky-400/10 text-sky-300',
        'text' => 'bg-white/5 text-mist',
        'file' => 'bg-violet/10 text-violet',
    ];
@endphp

<x-shell :section="$section" interactive>
    <div
        class="flex h-full min-h-0"
        x-data="{
            menu: false,
            x: 0,
            y: 0,
            id: null,
            canEdit: false,
            canShare: false,
            canOwner: false,
            canDelete: false,
            canRestore: false,
            canForce: false,
            canRequest: false,
            requestPending: false,
            starred: false,
            folder: false,
            dragging: false,
            clickTimer: null,
            pick(id) {
                clearTimeout(this.clickTimer)
                this.clickTimer = setTimeout(() => $wire.selectItem(id), 220)
            },
            openFromClick(id) {
                clearTimeout(this.clickTimer)
                $wire.openItem(id)
            },
            openMenu(event, id, canEdit, canShare, canOwner, canDelete, canRestore, canForce, starred, folder, canRequest, requestPending) {
                this.id = id
                this.canEdit = canEdit
                this.canShare = canShare
                this.canOwner = canOwner
                this.canDelete = canDelete
                this.canRestore = canRestore
                this.canForce = canForce
                this.starred = starred
                this.folder = folder
                this.canRequest = canRequest
                this.requestPending = requestPending
                this.x = Math.min(event.clientX, window.innerWidth - 230)
                this.y = Math.min(event.clientY, window.innerHeight - 320)
                this.menu = true
            }
        }"
        @click="menu = false"
        @keydown.escape.window="menu = false"
    >
        <section
            class="flex min-w-0 flex-1 flex-col"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="dragging = false; @if ($canCreate) $wire.uploadMultiple('uploads', $event.dataTransfer.files) @endif"
        >
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-4 md:px-6">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-1 text-sm text-mist">
                        @php
                            $rootLabel = match ($section) {
                                'common' => 'Drive Bersama',
                                'shared' => 'Dibagikan ke saya',
                                'recent' => 'Terbaru',
                                'starred' => 'Berbintang',
                                'trash' => 'Sampah',
                                default => auth()->user()->isManager() ? 'Semua file' : 'Drive saya',
                            };
                        @endphp
                        <button type="button" class="hover:text-paper" wire:click="goToFolder(null)">
                            {{ $rootLabel }}
                        </button>
                        @foreach ($crumbs as $crumb)
                            <x-icon name="chevron" class="size-3.5" />
                            <button type="button" class="max-w-40 truncate hover:text-paper" wire:click="goToFolder({{ $crumb->id }})">{{ $crumb->name }}</button>
                        @endforeach
                    </div>
                    <h1 class="mt-1 truncate text-2xl font-semibold tracking-tight">{{ $heading }}</h1>
                    @if ($section === 'common' && $crumbs->isEmpty() && $search === '')
                        <p class="mt-1 text-sm text-mist">Semua akun yang masuk dapat melihat dan mengubah isi di sini. Unggahan di Drive Bersama tidak memotong kuota Drive saya.</p>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <label class="relative">
                        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-mist" />
                        <input wire:model.live.debounce.300ms="search" class="field w-44 pl-9 sm:w-64" type="search" placeholder="Cari di drive">
                    </label>
                    <button type="button" class="btn-ghost" wire:click="setSort('name')">Nama</button>
                    <button type="button" class="btn-ghost" wire:click="setSort('updated_at')">Diubah</button>
                    <button type="button" class="btn-ghost {{ $layout === 'grid' ? 'text-paper' : '' }}" wire:click="setLayout('grid')" aria-label="Kisi">
                        <x-icon name="grid" class="size-4" />
                    </button>
                    <button type="button" class="btn-ghost {{ $layout === 'list' ? 'text-paper' : '' }}" wire:click="setLayout('list')" aria-label="Daftar">
                        <x-icon name="list" class="size-4" />
                    </button>
                    @if ($canCreate)
                        <div class="relative" x-data="{ baru: false }">
                            <button type="button" class="btn-primary" @click="baru = !baru">
                                <x-icon name="plus" class="size-4" />
                                Baru
                            </button>
                            <div x-cloak x-show="baru" @click.outside="baru = false" class="absolute right-0 z-20 mt-2 w-52 rounded-2xl border border-line bg-panel p-2 shadow-2xl">
                                <button type="button" class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-sm hover:bg-white/5" wire:click="openFolderModal" @click="baru = false">
                                    <x-icon name="folder" class="size-4 text-accent" /> Folder baru
                                </button>
                                <button type="button" class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-sm hover:bg-white/5" @click="$refs.picker.click(); baru = false">
                                    <x-icon name="upload" class="size-4 text-violet" /> Unggah file
                                </button>
                            </div>
                        </div>
                        <input x-ref="picker" class="hidden" type="file" multiple wire:model="uploads">
                    @endif
                    @if ($inTrash)
                        <button type="button" class="btn-danger" wire:click="emptyTrash" wire:confirm="Kosongkan sampah secara permanen?">Kosongkan</button>
                    @endif
                </div>
            </div>

            <div wire:loading.flex wire:target="uploads" class="items-center gap-2 border-b border-line bg-accent/10 px-6 py-2 text-sm text-accent">
                Mengunggah file…
            </div>
            @error('uploads') <p class="px-6 py-2 text-sm text-rose-300">{{ $message }}</p> @enderror
            @error('uploads.*') <p class="px-6 py-2 text-sm text-rose-300">{{ $message }}</p> @enderror

            <div class="relative min-h-0 flex-1 overflow-y-auto px-4 py-4 md:px-6">
                <div x-cloak x-show="dragging" class="pointer-events-none absolute inset-4 z-10 grid place-items-center rounded-3xl border-2 border-dashed border-accent bg-accent/10 text-accent">
                    Lepaskan file untuk mengunggah
                </div>

                @if ($notice)
                    <p class="mb-4 rounded-xl border border-accent/30 bg-accent/10 px-3 py-2 text-sm text-accent">{{ $notice }}</p>
                @endif

                @if (count($nodes) === 0)
                    <div class="grid h-full min-h-64 place-items-center text-center">
                        <div>
                            <span class="mx-auto grid size-16 place-items-center rounded-3xl bg-raised text-accent">
                                <x-icon name="folder" class="size-8" />
                            </span>
                            <p class="mt-4 text-lg font-medium">Belum ada item di sini</p>
                            <p class="mt-1 text-sm text-mist">
                                @if ($section === 'common' && $canCreate)
                                    Taruh folder atau file di sini agar semua orang yang login bisa melihat dan mengeditnya.
                                @elseif ($canCreate)
                                    Buat folder atau unggah file untuk mulai.
                                @else
                                    Item yang relevan akan muncul di halaman ini.
                                @endif
                            </p>
                        </div>
                    </div>
                @elseif ($layout === 'grid')
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                        @foreach ($nodes as $node)
                            @php $tone = $tones[$node['kind']] ?? $tones['file']; @endphp
                            <article
                                wire:key="grid-{{ $node['id'] }}"
                                @click="pick({{ $node['id'] }})"
                                @dblclick.prevent="openFromClick({{ $node['id'] }})"
                                @contextmenu.prevent="openMenu($event, {{ $node['id'] }}, {{ $node['can_edit'] ? 'true' : 'false' }}, {{ $node['can_share'] ? 'true' : 'false' }}, {{ $node['can_owner'] ? 'true' : 'false' }}, {{ $node['can_delete'] ? 'true' : 'false' }}, {{ $node['can_restore'] ? 'true' : 'false' }}, {{ $node['can_force'] ? 'true' : 'false' }}, {{ $node['starred'] ? 'true' : 'false' }}, {{ $node['folder'] ? 'true' : 'false' }}, {{ $node['can_request'] ? 'true' : 'false' }}, {{ $node['request_pending'] ? 'true' : 'false' }})"
                                @class([
                                    'cursor-pointer rounded-2xl border bg-raised/70 p-3 text-left transition hover:border-accent/40',
                                    'border-accent ring-2 ring-accent/40' => $selectedId === $node['id'],
                                    'border-line' => $selectedId !== $node['id'],
                                ])
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <span class="grid size-12 place-items-center rounded-2xl {{ $tone }}">
                                        <x-icon :name="$node['kind']" class="size-6" />
                                    </span>
                                    <button type="button" class="text-mist hover:text-amber-300" wire:click.stop="toggleStar({{ $node['id'] }})" aria-label="Bintang">
                                        <x-icon name="star" class="size-4 {{ $node['starred'] ? 'text-amber-300' : '' }}" @if($node['starred']) solid @endif />
                                    </button>
                                </div>
                                <p class="mt-3 truncate text-sm font-medium">{{ $node['name'] }}</p>
                                @if ($node['common'] && $section !== 'common')
                                    <p class="text-xs text-accent">Drive Bersama</p>
                                @endif
                                <p class="mt-1 truncate text-xs text-mist">{{ $node['owner'] }} · {{ $node['updated'] }}</p>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="overflow-hidden rounded-2xl border border-line">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-raised text-xs uppercase tracking-wide text-mist">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Nama</th>
                                    <th class="hidden px-4 py-3 font-medium md:table-cell">Pemilik</th>
                                    <th class="hidden px-4 py-3 font-medium lg:table-cell">Diubah</th>
                                    <th class="px-4 py-3 font-medium">Ukuran</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($nodes as $node)
                                    @php $tone = $tones[$node['kind']] ?? $tones['file']; @endphp
                                    <tr
                                        wire:key="list-{{ $node['id'] }}"
                                        @click="pick({{ $node['id'] }})"
                                        @dblclick.prevent="openFromClick({{ $node['id'] }})"
                                        @contextmenu.prevent="openMenu($event, {{ $node['id'] }}, {{ $node['can_edit'] ? 'true' : 'false' }}, {{ $node['can_share'] ? 'true' : 'false' }}, {{ $node['can_owner'] ? 'true' : 'false' }}, {{ $node['can_delete'] ? 'true' : 'false' }}, {{ $node['can_restore'] ? 'true' : 'false' }}, {{ $node['can_force'] ? 'true' : 'false' }}, {{ $node['starred'] ? 'true' : 'false' }}, {{ $node['folder'] ? 'true' : 'false' }}, {{ $node['can_request'] ? 'true' : 'false' }}, {{ $node['request_pending'] ? 'true' : 'false' }})"
                                        @class([
                                            'cursor-pointer border-t border-line',
                                            'bg-accent/10' => $selectedId === $node['id'],
                                            'hover:bg-white/5' => $selectedId !== $node['id'],
                                        ])
                                    >
                                        <td class="px-4 py-3">
                                            <span class="flex items-center gap-3">
                                                <span class="grid size-9 place-items-center rounded-xl {{ $tone }}">
                                                    <x-icon :name="$node['kind']" class="size-4" />
                                                </span>
                                                <span class="truncate font-medium">{{ $node['name'] }}</span>
                                                @if ($node['common'] && $section !== 'common')
                                                    <span class="shrink-0 text-xs text-accent">Bersama</span>
                                                @elseif ($node['shared'])
                                                    <x-icon name="share" class="size-3.5 text-mist" />
                                                @endif
                                            </span>
                                        </td>
                                        <td class="hidden px-4 py-3 text-mist md:table-cell">{{ $node['owner'] }}</td>
                                        <td class="hidden px-4 py-3 text-mist lg:table-cell">{{ $node['updated'] }}</td>
                                        <td class="px-4 py-3 text-mist">{{ $node['size'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>

        @if ($details)
            <aside class="hidden w-80 shrink-0 flex-col border-l border-line bg-panel/80 xl:flex">
                <div class="border-b border-line px-5 py-4">
                    <p class="text-xs uppercase tracking-wide text-mist">Detail</p>
                    <h2 class="mt-1 break-words text-lg font-semibold">{{ $details['name'] }}</h2>
                </div>
                <dl class="flex flex-col gap-4 px-5 py-5 text-sm">
                    <div>
                        <dt class="text-mist">Pemilik</dt>
                        <dd class="mt-1">{{ $details['owner'] }}</dd>
                        @if ($details['owner_email'])
                            <dd class="text-xs text-mist">{{ $details['owner_email'] }}</dd>
                        @endif
                    </div>
                    <div>
                        <dt class="text-mist">Diubah</dt>
                        <dd class="mt-1">{{ $details['updated'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-mist">Ukuran</dt>
                        <dd class="mt-1">{{ $details['size'] }}</dd>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a class="btn-ghost" href="{{ $details['download'] }}">Unduh</a>
                        @if ($details['office'])
                            <a class="btn-ghost" href="{{ $details['office'] }}">{{ $details['can_edit'] ? 'Edit' : 'Lihat' }}</a>
                        @endif
                        @if ($details['can_share'])
                            <button type="button" class="btn-ghost" wire:click="startShare({{ $details['id'] }})">Bagikan</button>
                        @endif
                        @if ($details['can_request'])
                            <button type="button" class="btn-ghost" wire:click="requestEdit({{ $details['id'] }})" @disabled($details['request_pending'])>
                                {{ $details['request_pending'] ? 'Permintaan terkirim' : 'Minta akses ubah' }}
                            </button>
                        @endif
                        @if ($details['can_owner'])
                            <button type="button" class="btn-ghost" wire:click="startOwner({{ $details['id'] }})">Pemilik</button>
                        @endif
                    </div>
                </dl>
            </aside>
        @endif

        <div
            x-cloak
            x-show="menu"
            class="fixed z-50 w-56 rounded-2xl border border-line bg-panel p-2 shadow-2xl"
            :style="`left:${x}px;top:${y}px`"
            @click.stop
        >
            <template x-if="canEdit">
                <button type="button" class="flex w-full rounded-xl px-3 py-2 text-left text-sm hover:bg-white/5" @click="$wire.startRename(id); menu = false">Ganti nama</button>
            </template>
            <template x-if="canEdit">
                <button type="button" class="flex w-full rounded-xl px-3 py-2 text-left text-sm hover:bg-white/5" @click="$wire.startMove(id); menu = false">Pindahkan</button>
            </template>
            <template x-if="canShare">
                <button type="button" class="flex w-full rounded-xl px-3 py-2 text-left text-sm hover:bg-white/5" @click="$wire.startShare(id); menu = false">Bagikan</button>
            </template>
            <template x-if="canRequest && !requestPending">
                <button type="button" class="flex w-full rounded-xl px-3 py-2 text-left text-sm hover:bg-white/5" @click="$wire.requestEdit(id); menu = false">Minta akses ubah</button>
            </template>
            <template x-if="requestPending">
                <p class="px-3 py-2 text-xs text-mist">Permintaan akses sudah dikirim</p>
            </template>
            <template x-if="canOwner">
                <button type="button" class="flex w-full rounded-xl px-3 py-2 text-left text-sm hover:bg-white/5" @click="$wire.startOwner(id); menu = false">Ubah pemilik</button>
            </template>
            <a class="flex rounded-xl px-3 py-2 text-sm hover:bg-white/5" :href="`{{ url('/drive/items') }}/${id}/unduh`">Unduh</a>
            <button type="button" class="flex w-full rounded-xl px-3 py-2 text-left text-sm hover:bg-white/5" @click="$wire.toggleStar(id); menu = false" x-text="starred ? 'Hapus bintang' : 'Beri bintang'"></button>
            <template x-if="canDelete">
                <button type="button" class="flex w-full rounded-xl px-3 py-2 text-left text-sm text-rose-300 hover:bg-white/5" @click="$wire.trash(id); menu = false">Pindahkan ke sampah</button>
            </template>
            <template x-if="canRestore">
                <button type="button" class="flex w-full rounded-xl px-3 py-2 text-left text-sm hover:bg-white/5" @click="$wire.restore(id); menu = false">Pulihkan</button>
            </template>
            <template x-if="canForce">
                <button type="button" class="flex w-full rounded-xl px-3 py-2 text-left text-sm text-rose-300 hover:bg-white/5" @click="if (confirm('Hapus permanen?')) { $wire.forceDelete(id); menu = false }">Hapus permanen</button>
            </template>
        </div>
    </div>

    @if ($folderModal)
        <x-modal title="Folder baru">
            <form wire:submit="createFolder" class="flex flex-col gap-4">
                <label class="flex flex-col gap-2 text-sm">
                    <span class="text-mist">Nama folder</span>
                    <input class="field" wire:model="folderName" autofocus>
                    @error('folderName') <span class="text-rose-300">{{ $message }}</span> @enderror
                </label>
                <button class="btn-primary" type="submit">Buat</button>
            </form>
        </x-modal>
    @endif

    @if ($renameModal)
        <x-modal title="Ganti nama">
            <form wire:submit="rename" class="flex flex-col gap-4">
                <input class="field" wire:model="renameName">
                @error('renameName') <span class="text-sm text-rose-300">{{ $message }}</span> @enderror
                <button class="btn-primary" type="submit">Simpan</button>
            </form>
        </x-modal>
    @endif

    @if ($shareModal)
        <x-modal title="Bagikan">
            <div class="mb-5 rounded-2xl border border-line p-3">
                <p class="text-sm font-medium">Tautan</p>
                <p class="mt-1 text-xs text-mist">Siapa saja yang punya tautan dan sudah masuk bisa membuka item ini.</p>
                <select class="field mt-3" wire:change="setLinkAccess($event.target.value)">
                    <option value="off" @selected($linkAccess === 'off')>Hanya orang yang ditambahkan</option>
                    <option value="view" @selected($linkAccess === 'view')>Siapa saja dengan tautan dapat melihat</option>
                    <option value="edit" @selected($linkAccess === 'edit')>Siapa saja dengan tautan dapat mengubah</option>
                </select>
                @if ($linkUrl !== '')
                    <div class="mt-3 flex gap-2">
                        <input class="field" readonly value="{{ $linkUrl }}">
                        <button type="button" class="btn-ghost shrink-0" x-data="{ copied: false }" @click="navigator.clipboard.writeText(@js($linkUrl)); copied = true">
                            <span x-show="!copied">Salin</span>
                            <span x-show="copied" x-cloak>Tersalin</span>
                        </button>
                    </div>
                @endif
            </div>
            <form wire:submit="share" class="flex flex-col gap-3">
                <p class="text-sm font-medium">Orang tertentu</p>
                <input class="field" type="email" wire:model="shareEmail" placeholder="email pengguna">
                <select class="field" wire:model="sharePermission">
                    <option value="view">Dapat melihat</option>
                    <option value="edit">Dapat mengubah</option>
                </select>
                @error('shareEmail') <span class="text-sm text-rose-300">{{ $message }}</span> @enderror
                <button class="btn-primary" type="submit">Bagikan</button>
            </form>
            <ul class="mt-5 flex flex-col gap-2">
                @forelse ($shareRows as $share)
                    <li class="flex items-center justify-between gap-3 rounded-xl border border-line px-3 py-2 text-sm" wire:key="share-{{ $share->id }}">
                        <span>
                            <span class="block font-medium">{{ $share->user?->displayNameFor(auth()->user()) }}</span>
                            @if ($email = $share->user?->displayEmailFor(auth()->user()))
                                <span class="text-xs text-mist">{{ $email }}</span>
                            @endif
                        </span>
                        <span class="flex items-center gap-2">
                            <select class="field w-36 py-1.5" wire:change="updateSharePermission({{ $share->id }}, $event.target.value)">
                                <option value="view" @selected($share->permission->value === 'view')>Melihat</option>
                                <option value="edit" @selected($share->permission->value === 'edit')>Mengubah</option>
                            </select>
                            <button type="button" class="btn-danger" wire:click="revokeShare({{ $share->id }})">Cabut</button>
                        </span>
                    </li>
                @empty
                    <li class="text-sm text-mist">Belum dibagikan ke siapa pun.</li>
                @endforelse
            </ul>
        </x-modal>
    @endif

    @if ($moveModal)
        <x-modal title="Pindahkan">
            <div class="mb-3 flex items-center justify-between gap-3">
                <p class="truncate text-sm font-medium">{{ $pickerName }}</p>
                <button type="button" class="btn-ghost" wire:click="pickerUp">Naik</button>
            </div>
            @if ($pickerFolderId === null)
                <div class="mb-3 flex gap-2">
                    <button type="button" class="btn-ghost {{ $pickerSpace === 'personal' ? 'text-paper' : '' }}" wire:click="pickSpace('personal')">{{ auth()->user()->isManager() ? 'Semua file' : 'Drive saya' }}</button>
                    <button type="button" class="btn-ghost {{ $pickerSpace === 'common' ? 'text-accent' : '' }}" wire:click="pickSpace('common')">Drive Bersama</button>
                </div>
            @endif
            <div class="flex max-h-64 flex-col gap-1 overflow-y-auto">
                @forelse ($pickerFolders as $folder)
                    <button type="button" class="flex items-center gap-2 rounded-xl px-3 py-2 text-left text-sm hover:bg-white/5" wire:click="enterPicker({{ $folder->id }})" wire:key="pick-{{ $folder->id }}">
                        <x-icon name="folder" class="size-4 text-accent" />
                        {{ $folder->name }}
                    </button>
                @empty
                    <p class="px-3 py-4 text-sm text-mist">Tidak ada subfolder.</p>
                @endforelse
            </div>
            @error('move') <p class="mt-3 text-sm text-rose-300">{{ $message }}</p> @enderror
            <button type="button" class="btn-primary mt-4" wire:click="confirmMove">Pindahkan ke sini</button>
        </x-modal>
    @endif

    @if ($ownerModal)
        <x-modal title="Ubah pemilik">
            <form wire:submit="changeOwner" class="flex flex-col gap-4">
                <select class="field" wire:model="newOwnerId">
                    @foreach ($ownerChoices as $person)
                        <option value="{{ $person->id }}">
                            {{ $person->displayNameFor(auth()->user()) }}
                            @if ($email = $person->displayEmailFor(auth()->user()))
                                — {{ $email }}
                            @endif
                        </option>
                    @endforeach
                </select>
                @error('newOwnerId') <span class="text-sm text-rose-300">{{ $message }}</span> @enderror
                <button class="btn-primary" type="submit">Simpan pemilik</button>
            </form>
        </x-modal>
    @endif

    @if ($preview)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4">
            <div class="flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-3xl border border-line bg-panel">
                <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3">
                    <h2 class="truncate font-medium">{{ $preview['name'] }}</h2>
                    <div class="flex items-center gap-2">
                        <a class="btn-ghost" href="{{ $preview['download'] }}">Unduh</a>
                        @if ($preview['office'])
                            <a class="btn-ghost" href="{{ $preview['office'] }}">{{ $preview['can_edit'] ? 'Edit' : 'Lihat' }}</a>
                        @endif
                        @if ($preview['can_request'])
                            <button type="button" class="btn-ghost" wire:click="requestEdit({{ $preview['id'] }})" @disabled($preview['request_pending'])>
                                {{ $preview['request_pending'] ? 'Permintaan terkirim' : 'Minta akses ubah' }}
                            </button>
                        @endif
                        <button type="button" class="btn-ghost" wire:click="closeModals" aria-label="Tutup">
                            <x-icon name="x" class="size-4" />
                        </button>
                    </div>
                </div>
                <div class="min-h-80 flex-1 overflow-auto bg-ink p-4">
                    @if ($preview['kind'] === 'image')
                        <img src="{{ $preview['preview'] }}" alt="{{ $preview['name'] }}" class="mx-auto max-h-[70vh] rounded-xl">
                    @elseif ($preview['kind'] === 'pdf' || $preview['kind'] === 'text')
                        <iframe title="{{ $preview['name'] }}" src="{{ $preview['preview'] }}" class="h-[70vh] w-full rounded-xl bg-white"></iframe>
                    @elseif ($preview['kind'] === 'video')
                        <video class="mx-auto max-h-[70vh] w-full" controls src="{{ $preview['preview'] }}"></video>
                    @elseif ($preview['kind'] === 'audio')
                        <audio class="w-full" controls src="{{ $preview['preview'] }}"></audio>
                    @else
                        <div class="grid h-64 place-items-center text-center text-mist">
                            <div>
                                <p>Pratinjau tidak tersedia untuk jenis file ini.</p>
                                <div class="mt-4 flex justify-center gap-2">
                                    @if ($preview['office'])
                                        <a class="btn-primary" href="{{ $preview['office'] }}">{{ $preview['can_edit'] ? 'Edit' : 'Lihat' }}</a>
                                    @endif
                                    <a class="btn-ghost" href="{{ $preview['download'] }}">Unduh file</a>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</x-shell>
