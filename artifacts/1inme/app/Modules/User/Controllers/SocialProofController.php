<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\SocialProof;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SocialProofController extends Controller
{
    /**
     * The account that owns the active workspace's Buzz campaigns — Buzz
     * impressions are metered per owning account, so usage indicators read
     * the owner's plan + counter rather than the acting team member's.
     */
    private function workspaceOwner(): ?User
    {
        return User::find(workspace_owner_id());
    }

    public function index(Request $request)
    {
        $proofs = SocialProof::where('user_id', workspace_owner_id())
            ->orderByDesc('id')
            ->get();
        $buzzUsage = \App\Services\BuzzImpressionMeter::usageSummary($this->workspaceOwner());
        return view('user.social-proofs.index', compact('proofs', 'buzzUsage'));
    }

    public function create()
    {
        return view('user.social-proofs.create');
    }

    /**
     * Create an empty campaign envelope with one starter notification, then
     * redirect into the editor.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
        ]);

        $proof = SocialProof::create([
            'user_id'       => workspace_owner_id(),
            'name'          => $validated['name'],
            'type'          => 'recent_activity', // legacy column — first notification's type
            'is_active'     => true,
            'settings'      => [],
            'design'        => SocialProof::defaultDesign(),
            'targeting'     => SocialProof::defaultTargeting(),
            'schedule'      => [],
            'notifications' => [SocialProof::newNotification('recent_activity', 'Recent activity')],
        ]);

        return redirect()->route('user.social-proofs.edit', $proof)
            ->with('success', 'Campaign created — add as many notifications as you like.');
    }

    public function edit(Request $request, SocialProof $socialProof)
    {
        abort_if($socialProof->user_id !== workspace_owner_id(), 403);
        $stats = $this->statsFor($socialProof);

        // Make sure notifications is an array even on legacy rows where the
        // backfill migration somehow didn't populate it.
        if (!is_array($socialProof->notifications) || empty($socialProof->notifications)) {
            $socialProof->notifications = [SocialProof::newNotification($socialProof->type ?: 'recent_activity', $socialProof->name)];
            $socialProof->save();
        }

        return view('user.social-proofs.edit', [
            'proof'     => $socialProof,
            'stats'     => $stats,
            'buzzUsage' => \App\Services\BuzzImpressionMeter::usageSummary($this->workspaceOwner()),
        ]);
    }

    public function update(Request $request, SocialProof $socialProof)
    {
        abort_if($socialProof->user_id !== workspace_owner_id(), 403);

        $validated = $request->validate([
            'name'              => 'required|string|max:120',
            'is_active'         => 'sometimes|boolean',
            'design'            => 'nullable|array',
            'targeting'         => 'nullable|array',
            'schedule'          => 'nullable|array',
            'notifications_json'=> 'required|string',
            'directory_badge_notification_id' => 'nullable|string|max:64',
        ]);

        // Decode + normalize the notifications array from the editor's hidden JSON field.
        // We REJECT invalid input rather than silently resetting an existing campaign —
        // a corrupted client serialization should not destroy a multi-notification setup.
        $decoded = json_decode($validated['notifications_json'], true);
        if (!is_array($decoded) || empty($decoded)) {
            return back()->withErrors(['notifications_json' => 'Notifications payload is invalid or empty.'])->withInput();
        }

        $notifications = [];
        foreach (array_values($decoded) as $i => $n) {
            if (!is_array($n)) continue;
            $n['sort_order'] = $i;
            $norm = SocialProof::normalizeNotification($n);
            if ($norm['type'] === 'custom_html' && isset($norm['settings']['html'])) {
                $norm['settings']['html'] = $this->sanitizeHtml((string) $norm['settings']['html']);
            }
            $notifications[] = $norm;
        }
        if (empty($notifications)) {
            return back()->withErrors(['notifications_json' => 'No valid notifications in payload.'])->withInput();
        }

        $design    = array_merge(SocialProof::defaultDesign(),    (array)($socialProof->design ?? []),    (array)($validated['design'] ?? []));
        $targeting = array_merge(SocialProof::defaultTargeting(), (array)($socialProof->targeting ?? []), (array)($validated['targeting'] ?? []));

        // Coerce design booleans (checkboxes post '0'/'1')
        $design['shadow']     = (bool)($design['shadow'] ?? false);
        $design['show_close'] = (bool)($design['show_close'] ?? false);

        // Targeting normalization
        foreach (['delay', 'interval', 'duration', 'max_per_session'] as $k) {
            $targeting[$k] = max(0, (int)($targeting[$k] ?? 0));
        }
        $devices = array_values(array_intersect((array)($targeting['devices'] ?? []), ['desktop', 'tablet', 'mobile']));
        $targeting['devices'] = $devices ?: ['desktop', 'tablet', 'mobile'];
        foreach (['pages_include', 'pages_exclude'] as $k) {
            $val = $targeting[$k] ?? [];
            if (is_string($val)) $val = preg_split('/\r?\n/', $val);
            $targeting[$k] = array_values(array_filter(array_map('trim', (array)$val), fn($s) => $s !== ''));
        }

        // Directory badge: only persist when the supplied id matches one of the
        // current notifications AND that notification's type is eligible to
        // render in the Creators directory. Anything else is cleared so we
        // never store a dangling reference.
        $badgeId = $validated['directory_badge_notification_id'] ?? null;
        if ($badgeId !== null && $badgeId !== '') {
            $match = null;
            foreach ($notifications as $n) {
                if (($n['id'] ?? null) === $badgeId) { $match = $n; break; }
            }
            $badgeId = ($match && in_array($match['type'] ?? '', SocialProof::DIRECTORY_BADGE_TYPES, true))
                ? $badgeId : null;
        } else {
            $badgeId = null;
        }

        // A creator can only have ONE pinned directory badge across all of
        // their campaigns. When this campaign is being assigned the badge,
        // clear it from every other campaign in a single transaction so the
        // directory resolver never has to break a tie between two preferences.
        DB::transaction(function () use ($socialProof, $request, $badgeId, $validated, $design, $targeting, $notifications) {
            if ($badgeId !== null) {
                SocialProof::where('user_id', workspace_owner_id())
                    ->where('id', '!=', $socialProof->id)
                    ->whereNotNull('directory_badge_notification_id')
                    ->update(['directory_badge_notification_id' => null]);
            }

            $socialProof->update([
                'name'          => $validated['name'],
                'is_active'     => (bool)($validated['is_active'] ?? $socialProof->is_active),
                'design'        => $design,
                'targeting'     => $targeting,
                'schedule'      => (array)($validated['schedule'] ?? $socialProof->schedule ?? []),
                'notifications' => $notifications,
                'directory_badge_notification_id' => $badgeId,
                'type'          => $notifications[0]['type'] ?? $socialProof->type, // keep legacy column in sync with first notification
            ]);
        });

        return redirect()->route('user.social-proofs.edit', $socialProof)->with('success', 'Saved.');
    }

    public function toggleActive(Request $request, SocialProof $socialProof)
    {
        abort_if($socialProof->user_id !== workspace_owner_id(), 403);
        $socialProof->update(['is_active' => !$socialProof->is_active]);
        return back()->with('success', $socialProof->is_active ? 'Activated.' : 'Paused.');
    }

    public function destroy(Request $request, SocialProof $socialProof)
    {
        abort_if($socialProof->user_id !== workspace_owner_id(), 403);
        $socialProof->delete();
        return redirect()->route('user.social-proofs.index')->with('success', 'Campaign deleted.');
    }

    /* -------- Sanitization (custom_html) — strict ALLOWLIST DOM-based -------- */
    private function sanitizeHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '') return '';

        $allowedTags = [
            'div','span','p','a','strong','em','b','i','u','small','sub','sup','br','hr',
            'h1','h2','h3','h4','h5','h6','ul','ol','li','blockquote','code','pre',
            'img','figure','figcaption','section','article','header','footer','nav',
            'table','thead','tbody','tr','th','td','caption',
        ];
        $allowedAttrs = ['id','class','style','title','alt','role','aria-label','aria-hidden','data-action','data-id'];
        $urlAttrs = ['href','src'];
        $safeSchemes = ['http', 'https', 'mailto', 'tel', '#'];

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        // Wrap so we get a clean root and force UTF-8
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="__sp_root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $doc->getElementById('__sp_root');
        if (!$root) return '';

        $walk = function (\DOMNode $node) use (&$walk, $allowedTags, $allowedAttrs, $urlAttrs, $safeSchemes) {
            // Snapshot children — we may mutate during iteration
            $children = iterator_to_array($node->childNodes);
            foreach ($children as $child) {
                if ($child->nodeType !== XML_ELEMENT_NODE) {
                    if ($child->nodeType !== XML_TEXT_NODE && $child->nodeType !== XML_CDATA_SECTION_NODE) {
                        $node->removeChild($child);
                    }
                    continue;
                }
                /** @var \DOMElement $child */
                $tag = strtolower($child->tagName);
                if (!in_array($tag, $allowedTags, true)) {
                    // Drop the element AND all of its descendants entirely
                    $node->removeChild($child);
                    continue;
                }
                // Strip every attribute not in our allowlist; sanitize URL attrs.
                $attrs = [];
                foreach ($child->attributes as $a) $attrs[] = $a->name;
                foreach ($attrs as $aName) {
                    $aLower = strtolower($aName);
                    $isUrlAttr = in_array($aLower, $urlAttrs, true);
                    if (!in_array($aLower, $allowedAttrs, true) && !$isUrlAttr) {
                        $child->removeAttribute($aName);
                        continue;
                    }
                    $val = (string) $child->getAttribute($aName);
                    if ($isUrlAttr) {
                        $clean = $this->cleanUrl($val, $safeSchemes);
                        if ($clean === null) { $child->removeAttribute($aName); continue; }
                        $child->setAttribute($aName, $clean);
                    }
                    if ($aLower === 'style') {
                        // Drop expressions and javascript: in style values
                        if (preg_match('/expression\s*\(|javascript:|vbscript:|@import|behavior\s*:/i', $val)) {
                            $child->removeAttribute($aName);
                        }
                    }
                }
                // Force safe target on links
                if ($tag === 'a') {
                    $child->setAttribute('rel', 'noopener noreferrer');
                    if (!$child->hasAttribute('target')) $child->setAttribute('target', '_blank');
                }
                $walk($child);
            }
        };
        $walk($root);

        $out = '';
        foreach ($root->childNodes as $c) {
            $out .= $doc->saveHTML($c);
        }
        return $out;
    }

    private function cleanUrl(string $url, array $safeSchemes): ?string
    {
        $url = trim($url);
        if ($url === '') return null;
        if ($url[0] === '#' || $url[0] === '/') return $url;
        // Reject anything with an embedded scheme that isn't safe
        if (preg_match('#^([a-z][a-z0-9+.-]*):#i', $url, $m)) {
            $scheme = strtolower($m[1]);
            if (!in_array($scheme, $safeSchemes, true)) return null;
            return $url;
        }
        // No scheme + not absolute path → treat as relative (allow)
        return $url;
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
