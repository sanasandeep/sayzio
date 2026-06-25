<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\SiteStat;
use Illuminate\Http\Request;

class SiteStatController extends Controller
{
    public function index()
    {
        $stats = SiteStat::query()->orderBy('sort_order')->orderBy('id')->get();
        return view('admin.site-stats.index', compact('stats'));
    }

    public function create()
    {
        return view('admin.site-stats.create');
    }

    public function store(Request $request)
    {
        SiteStat::create($this->validated($request));
        return redirect()->route('admin.site-stats.index')->with('success', 'Stat added.');
    }

    public function edit(SiteStat $siteStat)
    {
        return view('admin.site-stats.edit', ['stat' => $siteStat]);
    }

    public function update(Request $request, SiteStat $siteStat)
    {
        $siteStat->update($this->validated($request));
        return redirect()->route('admin.site-stats.index')->with('success', 'Stat updated.');
    }

    public function destroy(SiteStat $siteStat)
    {
        $siteStat->delete();
        return redirect()->route('admin.site-stats.index')->with('success', 'Stat deleted.');
    }

    public function toggle(SiteStat $siteStat)
    {
        $siteStat->update(['is_active' => !$siteStat->is_active]);
        return back()->with('success', $siteStat->is_active ? 'Stat enabled.' : 'Stat disabled.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'label'      => ['required', 'string', 'max:160'],
            'value'      => ['required', 'string', 'max:32'],
            'suffix'     => ['nullable', 'string', 'max:16'],
            'icon'       => ['nullable', 'string', 'max:64'],
            'color'      => ['nullable', 'string', 'max:16', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'is_active'  => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
        ]);
        $data['icon']       = $data['icon']       ?: 'fa-chart-line';
        $data['color']      = $data['color']      ?: '#3d6bff';
        $data['is_active']  = (bool) $request->input('is_active', false);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        return $data;
    }
}
