<x-shell>
    <div class="h-full overflow-y-auto px-4 py-6 md:px-8">
        <div class="mx-auto max-w-xl">
            <h1 class="text-2xl font-semibold">Akun saya</h1>
            <p class="mt-1 text-sm text-mist">{{ auth()->user()->role->label() }} · {{ auth()->user()->status->label() }}</p>
            @if ($notice)
                <p class="mt-4 rounded-xl border border-accent/30 bg-accent/10 px-3 py-2 text-sm text-accent">{{ $notice }}</p>
            @endif
            <form wire:submit="save" class="mt-6 flex flex-col gap-4">
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
                    <span class="text-mist">Kata sandi saat ini</span>
                    <input class="field" type="password" wire:model="current_password" autocomplete="current-password">
                    @error('current_password') <span class="text-rose-300">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-2 text-sm">
                    <span class="text-mist">Kata sandi baru</span>
                    <input class="field" type="password" wire:model="password" autocomplete="new-password">
                    @error('password') <span class="text-rose-300">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-2 text-sm">
                    <span class="text-mist">Ulangi kata sandi baru</span>
                    <input class="field" type="password" wire:model="password_confirmation" autocomplete="new-password">
                </label>
                <button class="btn-primary" type="submit">Simpan akun</button>
            </form>
        </div>
    </div>
</x-shell>
