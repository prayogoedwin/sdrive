<x-guest-layout title="Kata sandi baru">
    <div class="flex min-h-dvh items-center justify-center px-6 py-12">
        <div class="w-full max-w-md">
            <h1 class="text-2xl font-semibold">Kata sandi baru</h1>
            <form method="POST" action="{{ route('password.update') }}" class="mt-8 flex flex-col gap-4">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <label class="flex flex-col gap-2 text-sm">
                    <span class="text-mist">Email</span>
                    <input class="field" type="email" name="email" value="{{ old('email', $email) }}" required>
                    @error('email') <span class="text-rose-300">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-2 text-sm">
                    <span class="text-mist">Kata sandi baru</span>
                    <input class="field" type="password" name="password" required>
                    @error('password') <span class="text-rose-300">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-2 text-sm">
                    <span class="text-mist">Ulangi kata sandi</span>
                    <input class="field" type="password" name="password_confirmation" required>
                </label>
                <button class="btn-primary" type="submit">Simpan</button>
            </form>
        </div>
    </div>
</x-guest-layout>
