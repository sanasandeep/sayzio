<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class LinkController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()->links()->with(['project', 'domain']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhere('alias', 'ilike', "%{$search}%")
                  ->orWhere('long_url', 'ilike', "%{$search}%");
            });
        }

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($projectId = $request->get('project_id')) {
            $query->where('project_id', $projectId);
        }

        if ($request->get('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->get('status') === 'inactive') {
            $query->where('is_active', false);
        }

        $links = $query->latest()->paginate(15)->withQueryString();
        $projects = $request->user()->projects()->orderBy('name')->get();

        return view('user.links.index', compact('links', 'projects'));
    }

    public function create(Request $request)
    {
        $projects = $request->user()->projects()->orderBy('name')->get();
        $pixels = $request->user()->pixels()->orderBy('name')->get();
        $domains = $request->user()->domains()->where('is_verified', true)->get();

        return view('user.links.create', compact('projects', 'pixels', 'domains'));
    }

    public function store(Request $request)
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'type' => 'required|in:url,biolink,file,ics,vcf',
            'long_url' => 'required_if:type,url|nullable|url|max:2048',
            'redirect_type' => 'nullable|in:301,302',
            'alias' => 'nullable|string|max:50|unique:links,alias|alpha_dash',
            'title' => 'nullable|string|max:255',
            'project_id' => "nullable|exists:projects,id,user_id,{$userId}",
            'domain_id' => "nullable|exists:domains,id,user_id,{$userId}",
            'is_password_protected' => 'boolean',
            'password' => 'nullable|string|min:3|max:100',
            'expires_at' => 'nullable|date|after:now',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'utm_source' => 'nullable|string|max:255',
            'utm_medium' => 'nullable|string|max:255',
            'utm_campaign' => 'nullable|string|max:255',
            'utm_term' => 'nullable|string|max:255',
            'utm_content' => 'nullable|string|max:255',
            'pixel_ids' => 'nullable|array',
            'pixel_ids.*' => "exists:pixels,id,user_id,{$userId}",
            'seo_image' => 'nullable|image|max:2048',
            'favicon' => 'nullable|image|max:1024',
            'country_restrictions' => 'nullable|string|max:500',
            'device_targeting' => 'nullable|array',
            'device_targeting.*' => 'in:desktop,mobile,tablet',
        ]);

        if (empty($validated['alias'])) {
            $validated['alias'] = Link::generateAlias();
        }

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
            $validated['is_password_protected'] = true;
        }

        if ($request->hasFile('seo_image')) {
            $disk = config('filesystems.default') === 's3' ? 's3' : 'public';
            $validated['seo_image'] = $request->file('seo_image')->store('seo-images', $disk);
            if ($disk === 'public') {
                $validated['seo_image'] = Storage::disk('public')->url($validated['seo_image']);
            } else {
                $validated['seo_image'] = Storage::disk('s3')->url($validated['seo_image']);
            }
        }
        if ($request->hasFile('favicon')) {
            $disk = config('filesystems.default') === 's3' ? 's3' : 'public';
            $validated['favicon'] = $request->file('favicon')->store('favicons', $disk);
            if ($disk === 'public') {
                $validated['favicon'] = Storage::disk('public')->url($validated['favicon']);
            } else {
                $validated['favicon'] = Storage::disk('s3')->url($validated['favicon']);
            }
        }

        $settings = [];
        if (!empty($validated['country_restrictions'])) {
            $settings['country_restrictions'] = array_map('trim', explode(',', $validated['country_restrictions']));
        }
        if (!empty($validated['device_targeting'])) {
            $settings['device_targeting'] = $validated['device_targeting'];
        }
        $validated['settings'] = !empty($settings) ? $settings : null;
        unset($validated['country_restrictions'], $validated['device_targeting']);

        $validated['user_id'] = $request->user()->id;

        $pixelIds = $validated['pixel_ids'] ?? [];
        unset($validated['pixel_ids']);

        $link = Link::create($validated);

        if (!empty($pixelIds)) {
            $link->pixels()->sync($pixelIds);
        }

        return redirect()->route('user.links.index')
            ->with('success', 'Link created successfully.');
    }

    public function show(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);

        $link->load(['project', 'domain', 'pixels']);

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

        $osStats = $link->clicks()
            ->selectRaw("os, COUNT(*) as count")
            ->whereNotNull('os')
            ->groupBy('os')
            ->orderByDesc('count')
            ->get();

        $countryStats = $link->clicks()
            ->selectRaw("country_code, COUNT(*) as count")
            ->whereNotNull('country_code')
            ->groupBy('country_code')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $deviceStats = $link->clicks()
            ->selectRaw("device_type, COUNT(*) as count")
            ->whereNotNull('device_type')
            ->groupBy('device_type')
            ->orderByDesc('count')
            ->get();

        return view('user.links.show', compact(
            'link', 'clicksOverTime', 'topReferrers',
            'browserStats', 'osStats', 'countryStats', 'deviceStats'
        ));
    }

    public function edit(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);

        $projects = $request->user()->projects()->orderBy('name')->get();
        $pixels = $request->user()->pixels()->orderBy('name')->get();
        $domains = $request->user()->domains()->where('is_verified', true)->get();
        $link->load('pixels');

        return view('user.links.edit', compact('link', 'projects', 'pixels', 'domains'));
    }

    public function update(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);

        $userId = $request->user()->id;

        $validated = $request->validate([
            'long_url' => 'nullable|url|max:2048',
            'redirect_type' => 'nullable|in:301,302',
            'title' => 'nullable|string|max:255',
            'project_id' => "nullable|exists:projects,id,user_id,{$userId}",
            'domain_id' => "nullable|exists:domains,id,user_id,{$userId}",
            'is_active' => 'boolean',
            'is_password_protected' => 'boolean',
            'password' => 'nullable|string|min:3|max:100',
            'expires_at' => 'nullable|date',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'utm_source' => 'nullable|string|max:255',
            'utm_medium' => 'nullable|string|max:255',
            'utm_campaign' => 'nullable|string|max:255',
            'utm_term' => 'nullable|string|max:255',
            'utm_content' => 'nullable|string|max:255',
            'pixel_ids' => 'nullable|array',
            'pixel_ids.*' => "exists:pixels,id,user_id,{$userId}",
            'seo_image' => 'nullable|image|max:2048',
            'favicon' => 'nullable|image|max:1024',
            'country_restrictions' => 'nullable|string|max:500',
            'device_targeting' => 'nullable|array',
            'device_targeting.*' => 'in:desktop,mobile,tablet',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
            $validated['is_password_protected'] = true;
        } else {
            unset($validated['password']);
            if (empty($validated['is_password_protected'])) {
                $validated['password'] = null;
                $validated['is_password_protected'] = false;
            }
        }

        if ($request->hasFile('seo_image')) {
            $disk = config('filesystems.default') === 's3' ? 's3' : 'public';
            $validated['seo_image'] = $request->file('seo_image')->store('seo-images', $disk);
            if ($disk === 'public') {
                $validated['seo_image'] = Storage::disk('public')->url($validated['seo_image']);
            } else {
                $validated['seo_image'] = Storage::disk('s3')->url($validated['seo_image']);
            }
        } else {
            unset($validated['seo_image']);
        }

        if ($request->hasFile('favicon')) {
            $disk = config('filesystems.default') === 's3' ? 's3' : 'public';
            $validated['favicon'] = $request->file('favicon')->store('favicons', $disk);
            if ($disk === 'public') {
                $validated['favicon'] = Storage::disk('public')->url($validated['favicon']);
            } else {
                $validated['favicon'] = Storage::disk('s3')->url($validated['favicon']);
            }
        } else {
            unset($validated['favicon']);
        }

        $settings = $link->settings ?? [];
        if (isset($validated['country_restrictions']) && $validated['country_restrictions']) {
            $settings['country_restrictions'] = array_map('trim', explode(',', $validated['country_restrictions']));
        } else {
            unset($settings['country_restrictions']);
        }
        if (!empty($validated['device_targeting'])) {
            $settings['device_targeting'] = $validated['device_targeting'];
        } else {
            unset($settings['device_targeting']);
        }
        $validated['settings'] = !empty($settings) ? $settings : null;
        unset($validated['country_restrictions'], $validated['device_targeting']);

        $pixelIds = $validated['pixel_ids'] ?? [];
        unset($validated['pixel_ids']);

        $link->update($validated);
        $link->pixels()->sync($pixelIds);

        return redirect()->route('user.links.show', $link)
            ->with('success', 'Link updated successfully.');
    }

    public function destroy(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);

        $link->delete();

        return redirect()->route('user.links.index')
            ->with('success', 'Link deleted successfully.');
    }

    public function toggleActive(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);

        $link->update(['is_active' => !$link->is_active]);

        return back()->with('success', 'Link status updated.');
    }

    public function updateAlias(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'alias' => [
                'required',
                'string',
                'min:3',
                'max:60',
                'regex:/^[a-zA-Z0-9_-]+$/',
                'unique:links,alias,' . $link->id,
            ],
        ], [
            'alias.regex' => 'Only letters, numbers, hyphens and underscores are allowed.',
            'alias.unique' => 'This alias is already taken. Please choose another.',
        ]);

        $link->update(['alias' => $validated['alias']]);

        return back()->with('success', 'URL alias updated successfully.');
    }
}
