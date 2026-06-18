<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Support\SchemaHealth;
use App\Modules\User\Models\User;
use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Plan;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('status', 'active')->count(),
            'total_staff' => Admin::count(),
            'total_plans' => Plan::where('status', 'active')->count(),
            'recent_users' => User::latest()->take(5)->get(),
            'users_today' => User::whereDate('created_at', today())->count(),
            'users_this_month' => User::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
        ];

        // Proactive out-of-date-schema warning (Task #1679). Cached so it
        // adds at most one cheap query every couple of minutes to the
        // dashboard render.
        $schemaHealth = SchemaHealth::cached();

        return view('admin.dashboard.index', compact('stats', 'schemaHealth'));
    }
}
