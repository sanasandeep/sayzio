<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;

class LinkManagementController extends Controller
{
    public function index(Request $request)
    {
        $query = Link::with(['user', 'project', 'domain']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhere('alias', 'ilike', "%{$search}%")
                  ->orWhere('long_url', 'ilike', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'ilike', "%{$search}%")
                         ->orWhere('email', 'ilike', "%{$search}%");
                  });
            });
        }

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($request->get('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->get('status') === 'inactive') {
            $query->where('is_active', false);
        }

        if ($userId = $request->get('user_id')) {
            $query->where('user_id', $userId);
        }

        $links = $query->latest()->paginate(20)->withQueryString();

        $stats = [
            'total' => Link::count(),
            'active' => Link::where('is_active', true)->count(),
            'total_clicks' => Link::sum('total_clicks'),
            'types' => Link::selectRaw('type, COUNT(*) as count')->groupBy('type')->pluck('count', 'type'),
        ];

        return view('admin.links.index', compact('links', 'stats'));
    }

    public function show(Link $link)
    {
        $link->load(['user', 'project', 'domain', 'pixels']);

        $clicksOverTime = $link->clicks()
            ->selectRaw("DATE(clicked_at) as date, COUNT(*) as count")
            ->where('clicked_at', '>=', now()->subDays(30))
            ->groupByRaw('DATE(clicked_at)')
            ->orderBy('date')
            ->get();

        $topReferrers = $link->clicks()
            ->selectRaw("referrer, COUNT(*) as count")
            ->whereNotNull('referrer')
            ->groupBy('referrer')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $browserStats = $link->clicks()
            ->selectRaw("browser, COUNT(*) as count")
            ->whereNotNull('browser')
            ->groupBy('browser')
            ->orderByDesc('count')
            ->get();

        return view('admin.links.show', compact('link', 'clicksOverTime', 'topReferrers', 'browserStats'));
    }

    public function toggleActive(Link $link)
    {
        $link->update(['is_active' => !$link->is_active]);

        return back()->with('success', 'Link status updated.');
    }

    public function destroy(Link $link)
    {
        $link->delete();

        return redirect()->route('admin.links.index')
            ->with('success', 'Link deleted successfully.');
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:enable,disable,delete',
            'link_ids' => 'required|array|min:1',
            'link_ids.*' => 'integer|exists:links,id',
        ]);

        $ids = $request->input('link_ids');
        $action = $request->input('action');

        switch ($action) {
            case 'enable':
                Link::whereIn('id', $ids)->update(['is_active' => true]);
                $message = count($ids) . ' link(s) enabled.';
                break;
            case 'disable':
                Link::whereIn('id', $ids)->update(['is_active' => false]);
                $message = count($ids) . ' link(s) disabled.';
                break;
            case 'delete':
                Link::whereIn('id', $ids)->delete();
                $message = count($ids) . ' link(s) deleted.';
                break;
            default:
                $message = 'Unknown action.';
        }

        return back()->with('success', $message);
    }
}
