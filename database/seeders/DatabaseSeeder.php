<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\SharePermission;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\DriveService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $quota = config('sdrive.quota_bytes');

        $super = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@sdrive.test',
            'password' => 'SDrive#Super',
            'role' => UserRole::SuperAdmin,
            'status' => AccountStatus::Active,
            'storage_quota' => $quota,
            'email_verified_at' => now(),
        ]);

        $admin = User::query()->create([
            'name' => 'Admin GMT',
            'email' => 'admin@sdrive.test',
            'password' => 'SDrive#Admin',
            'role' => UserRole::Admin,
            'status' => AccountStatus::Active,
            'storage_quota' => $quota,
            'email_verified_at' => now(),
        ]);

        $user = User::query()->create([
            'name' => 'Sari Wulandari',
            'email' => 'user@sdrive.test',
            'password' => 'SDrive#User',
            'role' => UserRole::User,
            'status' => AccountStatus::Active,
            'storage_quota' => $quota,
            'email_verified_at' => now(),
        ]);

        $colleague = User::query()->create([
            'name' => 'Rekan Kerja',
            'email' => 'rekan@sdrive.test',
            'password' => 'SDrive#User',
            'role' => UserRole::User,
            'status' => AccountStatus::Active,
            'storage_quota' => $quota,
            'email_verified_at' => now(),
        ]);

        User::query()->create([
            'name' => 'Calon Anggota',
            'email' => 'pending@sdrive.test',
            'password' => 'SDrive#User',
            'role' => UserRole::User,
            'status' => AccountStatus::Pending,
            'storage_quota' => $quota,
        ]);

        $drive = app(DriveService::class);
        $docs = $drive->createFolder($user, null, 'Dokumen GMT');
        $drive->createTextFile($user, $docs->id, 'Selamat datang.txt', "Selamat datang di SDrive.\nSelfDrive by GMT.\n");
        $materials = $drive->createFolder($user, $docs->id, 'Materi');
        $drive->createTextFile($user, $materials->id, 'Catatan.txt', "Catatan bersama tim GMT.\n");
        $drive->share($user, $docs, $colleague->email, SharePermission::View);
        $drive->createFolder($admin, null, 'Arsip Pusat');
        $drive->createFolder($super, null, 'Internal Developer');
    }
}
