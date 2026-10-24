<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Admin\Models\Admin;
use App\Modules\User\Models\Workspace;
use Illuminate\Http\Request;

class AttendanceController extends \App\Modules\User\Controllers\AttendanceController
{
    protected function actor(Request $request)
    {
        return auth('admin')->user();
    }

    protected function staffRelation(): string
    {
        return 'admin';
    }

    protected function actorGuard(): string
    {
        return 'admin';
    }

    protected function routePrefix(): string
    {
        return 'admin.attendance';
    }

    protected function layout(): string
    {
        return 'admin.layouts.app';
    }

    protected function can(Request $request, Workspace $ws, string $ability): bool
    {
        return in_array($ability, ['view', 'create']) || $this->actor($request)->hasPermission('attendance.'.$ability);
    }

    protected function directory(Workspace $ws)
    {
        return Admin::where('status', 'active')->orderBy('name')->get(['id', 'name', 'email']);
    }

    protected function workspace(Request $request, string $ability = 'view'): Workspace
    {
        $actor = $this->actor($request);
        abort_unless($actor && $actor->status === 'active', 403);
        $ws = new Workspace(['name' => 'Sayzio internal staff']);
        $ws->id = 0;
        abort_unless($this->can($request, $ws, $ability), 403);

        return $ws;
    }
}
