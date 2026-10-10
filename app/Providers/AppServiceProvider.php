<?php

namespace App\Providers;

use App\Enums\AccessRequestStatus;
use App\Models\DriveAccessRequest;
use App\Models\DriveItem;
use App\Models\User;
use App\Policies\DriveItemPolicy;
use App\Policies\UserPolicy;
use App\Services\DriveService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(DriveItem::class, DriveItemPolicy::class);

        config([
            'livewire.temporary_file_upload.rules' => ['required', 'file', 'max:'.config('sdrive.max_upload_kb')],
        ]);

        Route::bind('item', function (string $value): DriveItem {
            return DriveItem::withTrashed()->whereKey($value)->firstOrFail();
        });

        View::composer(['partials.sidebar', 'partials.topbar'], function ($view): void {
            $user = auth()->user();
            if (! $user) {
                return;
            }

            $used = app(DriveService::class)->usage($user);

            $pending = DriveAccessRequest::query()
                ->where('status', AccessRequestStatus::Pending)
                ->whereHas('item', fn ($query) => $query->where('owner_id', $user->id))
                ->count();

            $view->with([
                'storageUsed' => $used,
                'storageQuota' => (int) $user->storage_quota,
                'accessRequestCount' => $pending,
            ]);
        });
    }
}
