@php
    use App\Support\ByteFormatter;

    $current = $section ?? null;
    $onDrive = ($interactive ?? false) || request()->routeIs('drive');
    $quota = max(1, (int) ($storageQuota ?? 1));
    $used = (int) ($storageUsed ?? 0);
    $percent = (int) min(100, round($used / $quota * 100));
    $links = [
        ['key' => 'my-drive', 'label' => auth()->user()->isManager() ? 'Semua file' : 'Drive saya', 'icon' => 'drive'],
        ['key' => 'common', 'label' => 'Drive Bersama', 'icon' => 'common'],
        ['key' => 'shared', 'label' => 'Dibagikan ke saya', 'icon' => 'share'],
        ['key' => 'recent', 'label' => 'Terbaru', 'icon' => 'clock'],
        ['key' => 'starred', 'label' => 'Berbintang', 'icon' => 'star'],
        ['key' => 'trash', 'label' => 'Sampah', 'icon' => 'trash'],
    ];
@endphp

<aside
    class="fixed inset-y-0 left-0 z-40 flex w-72 shrink-0 flex-col border-r border-line bg-panel transition max-md:-translate-x-full md:static md:translate-x-0"
    :style="nav ? 'transform: translateX(0)' : ''"
>
    <div class="flex items-center gap-3 px-5 py-5">
        <span class="grid size-10 place-items-center rounded-2xl bg-accent text-lg font-bold text-accent-ink">S</span>
        <div>
            <p class="text-base font-semibold tracking-tight">SDrive</p>
            <p class="text-xs text-mist">SelfDrive by GMT</p>
        </div>
    </div>

    <nav class="flex flex-1 flex-col gap-1 px-3">
        @foreach ($links as $link)
            @php $active = $onDrive && $current === $link['key']; @endphp
            @if ($onDrive)
                <button
                    type="button"
                    wire:click="showSection('{{ $link['key'] }}')"
                    @click="nav = false"
                    @class([
                        'flex items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition',
                        'bg-accent/15 text-accent' => $active,
                        'text-mist hover:bg-white/5 hover:text-paper' => ! $active,
                    ])
                >
                    <x-icon :name="$link['icon']" @class(['size-5', 'text-amber-300' => $link['key'] === 'starred' && $active]) />
                    {{ $link['label'] }}
                </button>
            @else
                <a
                    href="{{ route('drive', ['section' => $link['key']]) }}"
                    @class([
                        'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-mist transition hover:bg-white/5 hover:text-paper',
                    ])
                >
                    <x-icon :name="$link['icon']" class="size-5" />
                    {{ $link['label'] }}
                </a>
            @endif
        @endforeach

        <a
            href="{{ route('access.requests') }}"
            @click="nav = false"
            @class([
                'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                'bg-accent/15 text-accent' => request()->routeIs('access.requests'),
                'text-mist hover:bg-white/5 hover:text-paper' => ! request()->routeIs('access.requests'),
            ])
        >
            <x-icon name="bell" class="size-5" />
            <span class="flex-1 text-left">Permintaan akses</span>
            @if (($accessRequestCount ?? 0) > 0)
                <span class="rounded-full bg-accent px-2 py-0.5 text-xs font-semibold text-accent-ink">{{ $accessRequestCount }}</span>
            @endif
        </a>

        @if (auth()->user()->isManager())
            <a
                href="{{ route('users.index') }}"
                @class([
                    'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                    'bg-violet/15 text-violet' => request()->routeIs('users.index'),
                    'text-mist hover:bg-white/5 hover:text-paper' => ! request()->routeIs('users.index'),
                ])
            >
                <x-icon name="users" class="size-5" />
                Pengguna
            </a>
        @endif
    </nav>

    <div class="px-5 py-5">
        <div class="mb-2 flex items-center justify-between text-xs text-mist">
            <span>Drive saya</span>
            <span>{{ $percent }}%</span>
        </div>
        <div class="h-1.5 overflow-hidden rounded-full bg-white/10">
            <div class="h-full rounded-full bg-accent" style="width: {{ $percent }}%"></div>
        </div>
        <p class="mt-2 text-xs text-mist">{{ ByteFormatter::human($used) }} dari {{ ByteFormatter::human($quota) }}</p>
    </div>
</aside>
