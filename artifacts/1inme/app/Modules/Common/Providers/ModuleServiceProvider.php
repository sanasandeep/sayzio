<?php

namespace App\Modules\Common\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use App\Modules\Common\Services\S3Service;
use App\Modules\Common\Services\LinkTrackingService;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(S3Service::class);
        $this->app->singleton(LinkTrackingService::class);
    }

    public function boot(): void
    {
        Route::middleware('web')
            ->group(base_path('routes/modules/admin.php'));

        Route::middleware('web')
            ->group(base_path('routes/modules/user.php'));
    }
}
