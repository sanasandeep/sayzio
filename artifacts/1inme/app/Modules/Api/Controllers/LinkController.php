<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Api\Resources\LinkResource;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LinkController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        $q = Link::where('user_id', $request->user()->id);

        if ($type = $request->string('type')->toString()) {
            $q->where('type', $type);
        }
        if ($search = $request->string('q')->toString()) {
            $q->where(function ($w) use ($search) {
                $w->where('title', 'ilike', "%{$search}%")
                  ->orWhere('alias', 'ilike', "%{$search}%")
                  ->orWhere('long_url', 'ilike', "%{$search}%");
            });
        }

        $page = $q->orderByDesc('id')->paginate(min(100, max(1, (int) $request->input('per_page', 20))));

        return $this->ok([
            'items' => collect($page->items())->map(fn ($l) => LinkResource::toArray($l))->all(),
            'meta'  => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type'       => ['required', Rule::in(['short', 'biolink', 'file', 'qr', 'event', 'vcard', 'social', 'sms', 'wifi', 'pdf'])],
            'alias'      => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('links', 'alias')],
            'title'      => ['nullable', 'string', 'max:200'],
            'long_url'   => ['nullable', 'url', 'max:2048'],
            'visibility' => ['nullable', Rule::in(['public', 'registered', 'followers', 'subscribers'])],
            'is_active'  => ['nullable', 'boolean'],
            'seo_title'  => ['nullable', 'string', 'max:200'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'expires_at' => ['nullable', 'date'],
            'settings'   => ['nullable', 'array'],
        ]);

        $alias = $data['alias'] ?? Str::lower(Str::random(7));
        while (Link::where('alias', $alias)->exists()) {
            $alias = Str::lower(Str::random(7));
        }

        $link = Link::create([
            'user_id'    => $request->user()->id,
            'type'       => $data['type'],
            'alias'      => $alias,
            'title'      => $data['title'] ?? null,
            'long_url'   => $data['long_url'] ?? null,
            'visibility' => $data['visibility'] ?? 'public',
            'is_active'  => $data['is_active'] ?? true,
            'seo_title'  => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'settings'   => $data['settings'] ?? [],
        ]);

        return $this->created(['link' => LinkResource::toArray($link)]);
    }

    public function show(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');
        return $this->ok(['link' => LinkResource::toArray($link)]);
    }

    public function update(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');

        $data = $request->validate([
            'title'      => ['sometimes', 'nullable', 'string', 'max:200'],
            'long_url'   => ['sometimes', 'nullable', 'url', 'max:2048'],
            'alias'      => ['sometimes', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('links', 'alias')->ignore($link->id)],
            'visibility' => ['sometimes', Rule::in(['public', 'registered', 'followers', 'subscribers'])],
            'is_active'  => ['sometimes', 'boolean'],
            'seo_title'  => ['sometimes', 'nullable', 'string', 'max:200'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'settings'   => ['sometimes', 'nullable', 'array'],
        ]);

        if (array_key_exists('settings', $data)) {
            // Deep-merge supplied keys into the existing settings JSON so
            // mobile clients can patch a single sub-key (e.g. just
            // `appearance.theme`) without clobbering the rest.
            $existing = (array) ($link->settings ?? []);
            $patch    = (array) ($data['settings'] ?? []);
            $data['settings'] = array_replace_recursive($existing, $patch);
        }

        $link->fill($data)->save();
        return $this->ok(['link' => LinkResource::toArray($link->fresh())]);
    }

    /**
     * Reset all click counters & analytics rows for a link the caller
     * owns. Used by the mobile "Reset" action under link settings.
     */
    public function reset(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');

        DB::transaction(function () use ($link) {
            $link->forceFill([
                'total_clicks'  => 0,
                'unique_clicks' => 0,
            ])->save();

            if (Schema::hasTable('click_events')) {
                DB::table('click_events')->where('link_id', $link->id)->delete();
            }
            if (Schema::hasTable('link_clicks')) {
                DB::table('link_clicks')->where('link_id', $link->id)->delete();
            }
        });

        return $this->ok(['link' => LinkResource::toArray($link->fresh())]);
    }

    public function destroy(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');
        $link->delete();
        return $this->noContent();
    }

    /**
     * Per-link analytics summary for the mobile dashboard. Aggregates
     * the click_events table over an optional [from, to] window and
     * groups by day, country, referrer, and device. Falls back to the
     * Link model's denormalised counters when the click_events table is
     * unavailable (older installs / read-replica latency) so the mobile
     * client always gets a usable response.
     */
    public function analytics(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');

        $from = $request->date('from') ?? now()->subDays(30);
        $to   = $request->date('to')   ?? now();

        $payload = [
            'link_id'       => $link->id,
            'alias'         => $link->alias,
            'total_clicks'  => (int) ($link->total_clicks ?? 0),
            'unique_clicks' => (int) ($link->unique_clicks ?? 0),
            'window'        => [
                'from' => $from->toIso8601String(),
                'to'   => $to->toIso8601String(),
            ],
            'by_day'      => [],
            'by_country'  => [],
            'by_referrer' => [],
            'by_device'   => [],
            'by_source'   => [],
        ];

        if (\Schema::hasTable('click_events')) {
            $base = \DB::table('click_events')
                ->where('link_id', $link->id)
                ->whereBetween('created_at', [$from, $to]);

            $payload['by_day'] = (clone $base)
                ->selectRaw("to_char(created_at, 'YYYY-MM-DD') as day, count(*) as clicks")
                ->groupBy('day')->orderBy('day')->get()->all();
            $payload['by_country'] = (clone $base)
                ->selectRaw('country, count(*) as clicks')
                ->groupBy('country')->orderByDesc('clicks')->limit(50)->get()->all();
            $payload['by_referrer'] = (clone $base)
                ->selectRaw('referrer_host, count(*) as clicks')
                ->groupBy('referrer_host')->orderByDesc('clicks')->limit(50)->get()->all();
            $payload['by_device'] = (clone $base)
                ->selectRaw('device_type, count(*) as clicks')
                ->groupBy('device_type')->orderByDesc('clicks')->get()->all();
        }

        // Mobile-app vs web split — pulled directly from `link_clicks` since
        // that's where the LinkTrackingService records the source tag. Works
        // independently of the optional `click_events` rollup table.
        $payload['by_source'] = \DB::table('link_clicks')
            ->where('link_id', $link->id)
            ->whereBetween('clicked_at', [$from, $to])
            ->selectRaw("COALESCE(source, 'unknown') as source, count(*) as clicks")
            ->groupBy('source')
            ->orderByDesc('clicks')
            ->get()
            ->all();

        return $this->ok(['analytics' => $payload]);
    }
}
