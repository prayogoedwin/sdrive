<x-guest-layout title="Menunggu verifikasi">
    <div class="flex min-h-dvh items-center justify-center px-6 py-12">
        <div class="w-full max-w-lg rounded-3xl border border-line bg-panel p-8">
            <span class="grid size-12 place-items-center rounded-2xl bg-accent text-lg font-bold text-accent-ink">S</span>
            <h1 class="mt-6 text-2xl font-semibold">
                {{ $user->status->value === 'suspended' ? 'Akun dinonaktifkan' : 'Menunggu verifikasi' }}
            </h1>
            <p class="mt-3 text-sm leading-6 text-mist">
                @if ($user->status->value === 'suspended')
                    Akun {{ $user->email }} sedang nonaktif. Hubungi admin SDrive jika ini tidak sesuai.
                @else
                    Halo {{ $user->name }}. Akun Anda sudah terdaftar, tetapi admin belum memverifikasi. Anda bisa masuk ke drive setelah status berubah menjadi aktif.
                @endif
            </p>
            <form method="POST" action="{{ route('logout') }}" class="mt-8">
                @csrf
                <button class="btn-primary" type="submit">Keluar</button>
            </form>
        </div>
    </div>
</x-guest-layout>
