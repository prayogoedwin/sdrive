@props(['section' => null, 'interactive' => false])

<div x-data="{ nav: false }" class="flex h-dvh overflow-hidden bg-ink text-paper">
    <div
        x-cloak
        x-show="nav"
        class="fixed inset-0 z-30 bg-black/60 md:hidden"
        @click="nav = false"
    ></div>

    @include('partials.sidebar', ['section' => $section, 'interactive' => $interactive])

    <div class="flex min-w-0 flex-1 flex-col">
        @include('partials.topbar')
        <main class="min-h-0 flex-1 overflow-hidden">
            {{ $slot }}
        </main>
    </div>
</div>
