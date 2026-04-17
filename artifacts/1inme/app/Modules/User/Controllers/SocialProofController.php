<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\SocialProof;
use App\Modules\User\Models\SocialProofItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SocialProofController extends Controller
{
    public function index(Request $request)
    {
        $proofs = SocialProof::where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();
        return view('user.social-proofs.index', compact('proofs'));
    }

    public function create()
    {
        return view('user.social-proofs.create', ['types' => SocialProof::TYPES]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'type' => 'required|string|in:' . implode(',', array_keys(SocialProof::TYPES)),
        ]);

        $proof = SocialProof::create([
            'user_id'   => $request->user()->id,
            'name'      => $validated['name'],
            'type'      => $validated['type'],
            'is_active' => true,
            'settings'  => SocialProof::defaultSettingsFor($validated['type']),
            'design'    => SocialProof::defaultDesign(),
            'targeting' => SocialProof::defaultTargeting(),
            'schedule'  => [],
        ]);

        return redirect()->route('user.social-proofs.edit', $proof)->with('success', 'Notification campaign created.');
    }

    public function edit(Request $request, SocialProof $socialProof)
    {
        abort_if($socialProof->user_id !== $request->user()->id, 403);

        $stats = $this->statsFor($socialProof);
        return view('user.social-proofs.edit', [
            'proof' => $socialProof,
            'items' => $socialProof->items,
            'stats' => $stats,
        ]);
    }

    public function update(Request $request, SocialProof $socialProof)
    {
        abort_if($socialProof->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'name'        => 'required|string|max:120',
            'is_active'   => 'sometimes|boolean',
            'settings'    => 'nullable|array',
            'design'      => 'nullable|array',
            'targeting'   => 'nullable|array',
            'schedule'    => 'nullable|array',
        ]);

        $settings = array_merge(
            SocialProof::defaultSettingsFor($socialProof->type),
            (array)($socialProof->settings ?? []),
            (array)($validated['settings'] ?? [])
        );
        // Reviews tab posts a JSON string for the items collection — decode back to an array
        if ($socialProof->type === 'review' && isset($settings['items']) && is_string($settings['items'])) {
            $decoded = json_decode($settings['items'], true);
            $settings['items'] = is_array($decoded) ? array_values($decoded) : [];
        }
        // Custom HTML: defense-in-depth sanitize (script tags + on* handlers + javascript:/data: URIs)
        if ($socialProof->type === 'custom_html' && isset($settings['html'])) {
            $settings['html'] = $this->sanitizeHtml((string) $settings['html']);
        }

        $design    = array_merge(SocialProof::defaultDesign(),    (array)($socialProof->design ?? []),    (array)($validated['design'] ?? []));
        $targeting = array_merge(SocialProof::defaultTargeting(), (array)($socialProof->targeting ?? []), (array)($validated['targeting'] ?? []));

        // Normalize targeting numeric fields
        foreach (['delay', 'interval', 'duration', 'max_per_session'] as $k) {
            $targeting[$k] = max(0, (int)($targeting[$k] ?? 0));
        }
        // Normalize devices to a known set
        $devices = array_values(array_intersect((array)($targeting['devices'] ?? []), ['desktop', 'tablet', 'mobile']));
        $targeting['devices'] = $devices ?: ['desktop', 'tablet', 'mobile'];
        // Page lists -> array of strings (one per line, trimmed)
        foreach (['pages_include', 'pages_exclude'] as $k) {
            $val = $targeting[$k] ?? [];
            if (is_string($val)) $val = preg_split('/\r?\n/', $val);
            $targeting[$k] = array_values(array_filter(array_map('trim', (array)$val), fn($s) => $s !== ''));
        }

        $socialProof->update([
            'name'      => $validated['name'],
            'is_active' => (bool)($validated['is_active'] ?? $socialProof->is_active),
            'settings'  => $settings,
            'design'    => $design,
            'targeting' => $targeting,
            'schedule'  => (array)($validated['schedule'] ?? $socialProof->schedule ?? []),
        ]);

        return redirect()->route('user.social-proofs.edit', $socialProof)->with('success', 'Saved.');
    }

    public function toggleActive(Request $request, SocialProof $socialProof)
    {
        abort_if($socialProof->user_id !== $request->user()->id, 403);
        $socialProof->update(['is_active' => !$socialProof->is_active]);
        return back()->with('success', $socialProof->is_active ? 'Activated.' : 'Paused.');
    }

    public function destroy(Request $request, SocialProof $socialProof)
    {
        abort_if($socialProof->user_id !== $request->user()->id, 403);
        $socialProof->delete();
        return redirect()->route('user.social-proofs.index')->with('success', 'Notification campaign deleted.');
    }

    /* -------- Items (curated activity pool) -------- */

    public function storeItem(Request $request, SocialProof $socialProof)
    {
        abort_if($socialProof->user_id !== $request->user()->id, 403);
        $validated = $request->validate([
            'name'       => 'nullable|string|max:120',
            'location'   => 'nullable|string|max:120',
            'action'     => 'nullable|string|max:200',
            'image_url'  => 'nullable|url|max:500',
            'link_url'   => 'nullable|url|max:1000',
            'time_label' => 'nullable|string|max:60',
        ]);
        $validated['social_proof_id'] = $socialProof->id;
        $validated['sort_order'] = (int)$socialProof->items()->max('sort_order') + 1;
        SocialProofItem::create($validated);
        return back()->with('success', 'Activity added.');
    }

    public function destroyItem(Request $request, SocialProof $socialProof, SocialProofItem $item)
    {
        abort_if($socialProof->user_id !== $request->user()->id, 403);
        abort_if($item->social_proof_id !== $socialProof->id, 404);
        $item->delete();
        return back()->with('success', 'Activity removed.');
    }

    /* -------- Sanitization -------- */
    private function sanitizeHtml(string $html): string
    {
        // Strip <script>…</script> blocks (greedy across newlines)
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
        // Strip <iframe>, <object>, <embed>, <form>, <meta>, <link>, <style>
        $html = preg_replace('#<(iframe|object|embed|form|meta|link|style)\b[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#<(iframe|object|embed|form|meta|link|style)\b[^>]*/?>#is', '', $html);
        // Strip ALL on* event handlers — quoted, single-quoted, and unquoted
        $html = preg_replace('#\son[a-z]+\s*=\s*"[^"]*"#i', '', $html);
        $html = preg_replace("#\son[a-z]+\s*=\s*'[^']*'#i", '', $html);
        $html = preg_replace('#\son[a-z]+\s*=\s*[^\s>]+#i', '', $html);
        // Strip javascript:/vbscript:/data: URIs
        $html = preg_replace('#(href|src|action|formaction|background|cite|poster|data)\s*=\s*("|\')\s*(javascript|vbscript|data)\s*:#i', '$1=$2#', $html);
        return $html;
    }

    /* -------- Analytics -------- */

    private function statsFor(SocialProof $proof): array
    {
        $rows = DB::table('social_proof_events')
            ->select('kind', DB::raw('COUNT(*) as c'))
            ->where('social_proof_id', $proof->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('kind')
            ->pluck('c', 'kind')
            ->toArray();

        return [
            'impressions_30d' => (int)($rows['impression'] ?? 0),
            'clicks_30d'      => (int)($rows['click'] ?? 0),
            'conversions_30d' => (int)($rows['conversion'] ?? 0),
            'ctr'             => $proof->ctr(),
        ];
    }
}
