@props(['title'])

<div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
    <button type="button" class="absolute inset-0 bg-black/70" wire:click="closeModals" aria-label="Tutup"></button>
    <div class="relative w-full max-w-lg rounded-3xl border border-line bg-panel p-6 shadow-2xl">
        <div class="flex items-start justify-between gap-4">
            <h2 class="text-lg font-semibold">{{ $title }}</h2>
            <button type="button" class="btn-ghost" wire:click="closeModals" aria-label="Tutup">
                <x-icon name="x" class="size-4" />
            </button>
        </div>
        <div class="mt-4">
            {{ $slot }}
        </div>
    </div>
</div>
