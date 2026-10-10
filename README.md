# SDrive

SelfDrive by GMT. Penyimpanan file berbasis Laravel 13 dengan alur yang mirip Google Drive dan tampilan gelap.

## Peran

| Peran | Akses |
| --- | --- |
| Super Admin | Developer. Melihat seluruh pengguna, termasuk sesama super admin. Tidak bisa membuat super admin baru dari formulir. |
| Admin | Pemilik aplikasi. Mengelola pengguna dan seluruh folder, tanpa melihat data akun super admin. |
| Pengguna | Hanya folder/file miliknya dan yang dibagikan kepadanya. Drive Bersama bisa dilihat dan diubah oleh semua akun yang masuk. |

Akun yang mendaftar sendiri berstatus menunggu verifikasi sampai admin atau super admin mengaktifkannya.

Berbagi bisa lewat email atau tautan. Penerima yang hanya dapat melihat bisa meminta akses mengubah. Pemilik menerima notifikasi dan menanggapinya di menu Permintaan akses.

## Menjalankan lokal

```bash
composer install
npm install
npm run build
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

Buka `http://127.0.0.1:8000`.

## Akun contoh

| Email | Kata sandi | Peran |
| --- | --- | --- |
| superadmin@sdrive.test | SDrive#Super | Super Admin |
| admin@sdrive.test | SDrive#Admin | Admin |
| user@sdrive.test | SDrive#User | Pengguna |
| rekan@sdrive.test | SDrive#User | Pengguna, menerima berbagi folder |
| pending@sdrive.test | SDrive#User | Menunggu verifikasi |

Batas unggah mengikuti `SDRIVE_MAX_UPLOAD_KB` dan `upload_max_filesize` PHP.
