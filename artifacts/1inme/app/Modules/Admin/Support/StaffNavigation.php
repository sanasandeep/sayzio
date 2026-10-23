<?php

namespace App\Modules\Admin\Support;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Middleware\CheckPermission;

/** One permission snapshot per render, rather than several database queries per link. */
final class StaffNavigation
{
    private array $slugs;
    private bool $super;
    private bool $identity;

    public function __construct(?Admin $actor)
    {
        $this->super = $actor?->isSuperAdmin() ?? false;
        $this->slugs = $actor?->role?->permissions->pluck('slug')->all() ?? [];
        $this->identity = $this->super || ! array_diff(['users.identity.view', 'users.email.view', 'users.phone.view'], $this->slugs);
    }

    public function allows(string $name): bool
    {
        if (! $this->identity && ! StaffPrivacy::restrictedRoute($name)) return false;
        $route = app('router')->getRoutes()->getByName($name);
        if (! $route) return false;
        foreach ($route->gatherMiddleware() as $middleware) {
            $prefix = CheckPermission::class.':';
            if (str_starts_with($middleware, $prefix) && ! $this->super
                && ! array_intersect(explode('|', substr($middleware, strlen($prefix))), $this->slugs)) return false;
        }
        return true;
    }
}
