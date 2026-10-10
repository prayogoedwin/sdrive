<x-guest-layout title="Daftar">
    <div class="flex min-h-dvh items-center justify-center px-6 py-12">
        <div class="w-full max-w-md">
            <a href="{{ route('login') }}" class="mb-8 flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-2xl bg-accent font-bold text-accent-ink">S</span>
                <span>
                    <span class="block font-semibold">SDrive</span>
                    <span class="block text-xs text-mist">SelfDrive by GMT</span>
                </span>
            </a>
            <h1 class="text-2xl font-semibold">Buat akun</h1>
            <p class="mt-2 text-sm text-mist">Setelah daftar, admin perlu memverifikasi akun sebelum Anda bisa mengelola file.</p>

            <form method="POST" action="{{ route('register') }}" class="mt-8 flex flex-col gap-4">
                @csrf
                <label class="flex flex-col gap-2 text-sm">
                    <span class="text-mist">Nama</span>
                    <input class="field" type="text" name="name" value="{{ old('name') }}" required>
                    @error('name') <span class="text-rose-300">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-2 text-sm">
                    <span class="text-mist">Email</span>
                    <input class="field" type="email" name="email" value="{{ old('email') }}" required>
                    @error('email') <span class="text-rose-300">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-2 text-sm">
                    <span class="text-mist">Kata sandi</span>
                    <input class="field" type="password" name="password" required>
                    @error('password') <span class="text-rose-300">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-2 text-sm">
                    <span class="text-mist">Ulangi kata sandi</span>
                    <input class="field" type="password" name="password_confirmation" required>
                </label>
                <button class="btn-primary" type="submit">Daftar</button>
            </form>
            <p class="mt-6 text-sm text-mist">Sudah punya akun? <a class="text-accent hover:underline" href="{{ route('login') }}">Masuk</a></p>
        </div>
    </div>
</x-guest-layout>
