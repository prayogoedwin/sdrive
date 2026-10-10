<x-guest-layout title="Lupa kata sandi">
    <div class="flex min-h-dvh items-center justify-center px-6 py-12">
        <div class="w-full max-w-md">
            <h1 class="text-2xl font-semibold">Reset kata sandi</h1>
            <p class="mt-2 text-sm text-mist">Masukkan email akun. Tautan reset dicatat di log email lokal.</p>
            @if (session('status'))
                <p class="mt-4 rounded-xl border border-accent/30 bg-accent/10 px-3 py-2 text-sm text-accent">{{ session('status') }}</p>
            @endif
            <form method="POST" action="{{ route('password.email') }}" class="mt-8 flex flex-col gap-4">
                @csrf
                <label class="flex flex-col gap-2 text-sm">
                    <span class="text-mist">Email</span>
                    <input class="field" type="email" name="email" value="{{ old('email') }}" required>
                    @error('email') <span class="text-rose-300">{{ $message }}</span> @enderror
                </label>
                <button class="btn-primary" type="submit">Kirim tautan</button>
            </form>
            <a class="mt-6 inline-block text-sm text-accent hover:underline" href="{{ route('login') }}">Kembali masuk</a>
        </div>
    </div>
</x-guest-layout>
