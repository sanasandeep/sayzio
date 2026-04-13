<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\LinkClick;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $user->load('plan');

        $totalLinks = $user->links()->count();
        $totalClicks = $user->links()->sum('total_clicks');
        $totalProjects = $user->projects()->count();
        $activeLinks = $user->links()->where('is_active', true)->count();

        $recentLinks = $user->links()
            ->with('project')
            ->latest()
            ->take(5)
            ->get();

        $clicksToday = LinkClick::whereIn('link_id', $user->links()->pluck('id'))
            ->where('clicked_at', '>=', now()->startOfDay())
            ->count();

        return view('user.dashboard.index', compact(
            'user', 'totalLinks', 'totalClicks', 'totalProjects',
            'activeLinks', 'recentLinks', 'clicksToday'
        ));
    }
}
