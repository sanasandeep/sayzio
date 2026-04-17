<?php

namespace App\Providers;

use App\Modules\User\Services\Calendar\CalendarProviderRegistry;
use App\Modules\User\Services\Calendar\GoogleCalendarProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CalendarProviderRegistry::class, function () {
            $r = new CalendarProviderRegistry();
            $r->register('google', fn () => new GoogleCalendarProvider());
            // Microsoft + CalDAV drivers will register here once implemented.
            return $r;
        });
    }

    public function boot(): void
    {
        //
    }
}
