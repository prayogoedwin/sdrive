<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\PendingAccountController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DriveFileController;
use App\Http\Controllers\OfficeEditorController;
use App\Http\Controllers\ShareLinkController;
use App\Livewire\AccessRequests;
use App\Livewire\AccountPage;
use App\Livewire\DriveBrowser;
use App\Livewire\UserManager;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/daftar', [RegisterController::class, 'create'])->name('register');
    Route::post('/daftar', [RegisterController::class, 'store']);
    Route::get('/lupa-sandi', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/lupa-sandi', [PasswordResetController::class, 'send'])->name('password.email');
    Route::get('/reset-sandi/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-sandi', [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::middleware('signed')->group(function (): void {
    Route::get('/office/{item}/berkas', [OfficeEditorController::class, 'file'])->name('office.file');
    Route::post('/office/{item}/callback', [OfficeEditorController::class, 'callback'])->name('office.callback');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/menunggu-verifikasi', PendingAccountController::class)->name('account.pending');

    Route::middleware('account.active')->group(function (): void {
        Route::get('/', DriveBrowser::class)->name('drive');
        Route::get('/t/{token}', ShareLinkController::class)->name('drive.link');
        Route::get('/permintaan-akses', AccessRequests::class)->name('access.requests');
        Route::get('/akun', AccountPage::class)->name('account.edit');
        Route::get('/drive/items/{item}/unduh', [DriveFileController::class, 'download'])->name('drive.download');
        Route::get('/drive/items/{item}/pratinjau', [DriveFileController::class, 'preview'])->name('drive.preview');
        Route::get('/office/{item}', [OfficeEditorController::class, 'show'])->name('office.show');
        Route::get('/office/{item}/konfigurasi', [OfficeEditorController::class, 'config'])->name('office.config');

        Route::middleware('account.manager')->group(function (): void {
            Route::get('/pengguna', UserManager::class)->name('users.index');
        });
    });
});
