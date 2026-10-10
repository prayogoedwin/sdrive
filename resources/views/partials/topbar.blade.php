<header class="flex h-16 items-center justify-between gap-3 border-b border-line px-4 md:px-6">
    <button type="button" class="btn-ghost md:hidden" @click="nav = true" aria-label="Buka menu">
        <x-icon name="menu" class="size-5" />
    </button>

    <p class="truncate text-sm text-mist md:text-base">
        <span class="font-medium text-paper">SDrive</span>
        <span class="hidden sm:inline"> · SelfDrive by GMT</span>
    </p>

    <div class="flex items-center gap-2">
        <a href="{{ route('access.requests') }}" class="btn-ghost relative" aria-label="Permintaan akses">
            <x-icon name="bell" class="size-5" />
            @if (($accessRequestCount ?? 0) > 0)
                <span class="absolute -right-1 -top-1 grid min-w-4 place-items-center rounded-full bg-accent px-1 text-[10px] font-semibold leading-4 text-accent-ink">{{ $accessRequestCount }}</span>
            @endif
        </a>
        <div class="relative" x-data="{ open: false }">
        <button type="button" class="flex items-center gap-3 rounded-full border border-line bg-raised py-1 pl-1 pr-3" @click="open = !open">
            <span class="grid size-8 place-items-center rounded-full bg-violet/20 text-sm font-semibold text-violet">
                {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
            </span>
            <span class="hidden text-left sm:block">
                <span class="block text-sm font-medium leading-tight">{{ auth()->user()->name }}</span>
                <span class="block text-xs text-mist">{{ auth()->user()->role->label() }}</span>
            </span>
        </button>
        <div x-cloak x-show="open" @click.outside="open = false" class="absolute right-0 z-20 mt-2 w-56 rounded-2xl border border-line bg-panel p-2 shadow-2xl">
            <a href="{{ route('account.edit') }}" class="block rounded-xl px-3 py-2 text-sm hover:bg-white/5">Akun saya</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-sm text-rose-300 hover:bg-white/5">
                    <x-icon name="logout" class="size-4" />
                    Keluar
                </button>
            </form>
        </div>
        </div>
    </div>
</header>
