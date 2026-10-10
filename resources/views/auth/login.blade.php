<x-guest-layout title="Masuk">
    <div class="grid min-h-dvh lg:grid-cols-2">
        <section class="relative hidden overflow-hidden border-r border-line bg-panel lg:flex lg:flex-col lg:justify-between lg:p-12">
            <div class="absolute -left-20 top-10 size-72 rounded-full bg-accent/20 blur-3xl"></div>
            <div class="absolute bottom-0 right-0 size-80 rounded-full bg-violet/20 blur-3xl"></div>
            <div class="relative flex items-center gap-3">
                <span class="grid size-12 place-items-center rounded-2xl bg-accent text-xl font-bold text-accent-ink">S</span>
                <div>
                    <p class="text-lg font-semibold">SDrive</p>
                    <p class="text-sm text-mist">SelfDrive by GMT</p>
                </div>
            </div>
            <div class="relative max-w-md">
                <h1 class="text-4xl font-semibold tracking-tight">File Anda, dalam kendali Anda.</h1>
                <p class="mt-4 text-mist">Simpan, bagikan, dan atur folder seperti Drive — dengan tampilan gelap khas SDrive.</p>
            </div>
            <p class="relative text-sm text-mist">Pengurus Pusat aplikasi memverifikasi akun baru sebelum bisa masuk ke drive.</p>
        </section>

        <section class="flex items-center justify-center px-6 py-12">
            <div class="w-full max-w-md">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <span class="grid size-10 place-items-center rounded-2xl bg-accent font-bold text-accent-ink">S</span>
                    <div>
                        <p class="font-semibold">SDrive</p>
                        <p class="text-xs text-mist">SelfDrive by GMT</p>
                    </div>
                </div>
                <h2 class="text-2xl font-semibold">Masuk</h2>
                <p class="mt-2 text-sm text-mist">Gunakan email dan kata sandi akun SDrive.</p>

                @if (session('status'))
                    <p class="mt-4 rounded-xl border border-accent/30 bg-accent/10 px-3 py-2 text-sm text-accent">{{ session('status') }}</p>
                @endif

                <form method="POST" action="{{ route('login') }}" class="mt-8 flex flex-col gap-4">
                    @csrf
                    <label class="flex flex-col gap-2 text-sm">
                        <span class="text-mist">Email</span>
                        <input class="field" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                        @error('email') <span class="text-rose-300">{{ $message }}</span> @enderror
                    </label>
                    <label class="flex flex-col gap-2 text-sm">
                        <span class="text-mist">Kata sandi</span>
                        <input class="field" type="password" name="password" required autocomplete="current-password">
                    </label>
                    <label class="flex items-center gap-2 text-sm text-mist">
                        <input type="checkbox" name="remember" class="rounded border-line bg-ink text-accent focus:ring-accent">
                        Ingat saya
                    </label>
                    <button class="btn-primary" type="submit">Masuk</button>
                </form>

                <div class="mt-6 flex flex-col gap-2 text-sm text-mist">
                    <a class="text-accent hover:underline" href="{{ route('password.request') }}">Lupa kata sandi?</a>
                    <p>Belum punya akun? <a class="text-accent hover:underline" href="{{ route('register') }}">Daftar</a></p>
                </div>
            </div>
        </section>
    </div>
</x-guest-layout>
