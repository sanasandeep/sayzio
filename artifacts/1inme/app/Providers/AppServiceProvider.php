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
        // Note: App\Listeners\IssueInvoiceOnSubscriptionActivated is wired to
        // App\Events\SubscriptionActivated by Laravel's event auto-discovery
        // (typed handle() method on a class under app/Listeners). An explicit
        // Event::listen here would register a second subscription and cause
        // double-invoicing.
    }
}
