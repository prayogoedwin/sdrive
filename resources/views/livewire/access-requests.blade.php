<x-shell section="requests">
    <div class="h-full overflow-y-auto px-4 py-6 md:px-8">
        <div class="mx-auto max-w-3xl">
            <h1 class="text-2xl font-semibold">Permintaan akses</h1>
            <p class="mt-1 text-sm text-mist">Orang yang hanya dapat melihat bisa meminta izin mengubah. Keputusan ada di pemilik file.</p>

            @if ($notice)
                <p class="mt-4 rounded-xl border border-accent/30 bg-accent/10 px-3 py-2 text-sm text-accent">{{ $notice }}</p>
            @endif

            <ul class="mt-6 flex flex-col gap-3">
                @forelse ($requests as $request)
                    <li class="rounded-2xl border border-line bg-raised/70 px-4 py-4" wire:key="request-{{ $request->id }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-medium">{{ $request->item?->name ?? 'Item dihapus' }}</p>
                                <p class="mt-1 text-sm text-mist">
                                    {{ $request->user?->displayNameFor(auth()->user()) ?? 'Pengguna' }}
                                    meminta akses mengubah
                                    · {{ $request->created_at?->timezone(config('app.timezone'))->translatedFormat('d M Y, H:i') }}
                                </p>
                            </div>
                            <span @class([
                                'rounded-full px-2.5 py-1 text-xs font-medium',
                                'bg-amber-400/15 text-amber-200' => $request->status->value === 'pending',
                                'bg-accent/15 text-accent' => $request->status->value === 'granted',
                                'bg-rose-400/15 text-rose-200' => $request->status->value === 'denied',
                            ])>{{ $request->status->label() }}</span>
                        </div>
                        @if ($request->status->value === 'pending')
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button type="button" class="btn-primary" wire:click="grant({{ $request->id }})">Berikan akses ubah</button>
                                <button type="button" class="btn-ghost" wire:click="deny({{ $request->id }})">Tolak</button>
                            </div>
                        @endif
                    </li>
                @empty
                    <li class="rounded-2xl border border-dashed border-line px-4 py-10 text-center text-sm text-mist">Belum ada permintaan akses.</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-shell>
