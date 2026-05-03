<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;

/**
 * Per-link AR Business Card settings (creator-side).
 * Routes are nested under the existing user.links group so the standard
 * workspace + permissions middleware applies.
 */
class ArSettingsController extends Controller
{
    /**
     * Block types that resolve to a destination URL via the standard
     * `redirect.block` pipeline. Anything else (headings, dividers, forms,
     * embeds) would dead-end an AR tap, so we hide them from the picker.
     */
    public const TAPPABLE_TYPES = [
        'link', 'link_big', 'featured_pin',
        'social_profile', 'whatsapp_number', 'whatsapp_channel',
        'phone', 'sms', 'email',
        'app_store', 'play_store',
        'youtube', 'spotify', 'tiktok', 'instagram',
        'twitter', 'facebook', 'linkedin', 'github', 'website',
        'donation', 'paypal', 'buy_me_coffee', 'patreon', 'ko_fi',
        'product', 'service', 'coupon', 'one_time_offer',
    ];

    public function edit(Request $request, Link $link)
    {
        $this->authorizeOwnership($link);

        $blocks = BiolinkBlock::where('link_id', $link->id)
            ->where('is_active', true)
            ->whereIn('type', self::TAPPABLE_TYPES)
            ->orderBy('sort_order')
            ->get();

        $cfg = is_array($link->ar_settings) ? $link->ar_settings : [];

        return view('user.links.settings.ar', [
            'link'           => $link,
            'blocks'         => $blocks,
            'tappableTypes'  => self::TAPPABLE_TYPES,
            'cfg'            => array_merge([
                'block_ids'     => [],
                'headline'      => '',
                'subtitle'      => '',
                'display_name'  => $link->title ?: $link->alias,
                'accent_color'  => '#7c3aed',
                'avatar_url'    => '',
            ], $cfg),
        ]);
    }

    public function update(Request $request, Link $link)
    {
        $this->authorizeOwnership($link);

        $data = $request->validate([
            'enabled'       => 'sometimes|boolean',
            'headline'      => 'nullable|string|max:120',
            'subtitle'      => 'nullable|string|max:120',
            'display_name'  => 'nullable|string|max:80',
            'accent_color'  => 'nullable|string|max:9',
            'avatar_url'    => 'nullable|url|max:500',
            'block_ids'     => 'nullable|array|max:6',
            'block_ids.*'   => 'integer',
        ]);

        // Strip block_ids that don't belong to this link OR aren't a tappable
        // (URL-resolving) block type — silently, no error, so an attacker
        // can't enumerate other workspaces' block IDs and so creators can't
        // accidentally pin a heading/form block as an AR hotspot.
        $allowedIds = BiolinkBlock::where('link_id', $link->id)
            ->whereIn('type', self::TAPPABLE_TYPES)
            ->whereIn('id', $data['block_ids'] ?? [])
            ->pluck('id')->all();

        $accent = $data['accent_color'] ?? '#7c3aed';
        if (!preg_match('/^#?[0-9a-fA-F]{6}$/', $accent)) {
            $accent = '#7c3aed';
        }
        if ($accent[0] !== '#') $accent = '#' . $accent;

        $link->ar_enabled = (bool) ($data['enabled'] ?? false);
        $link->ar_settings = [
            'block_ids'    => array_values(array_map('intval', $allowedIds)),
            'headline'     => trim((string) ($data['headline'] ?? '')),
            'subtitle'     => trim((string) ($data['subtitle'] ?? '')),
            'display_name' => trim((string) ($data['display_name'] ?? ''))
                ?: ($link->title ?: $link->alias),
            'accent_color' => $accent,
            'avatar_url'   => trim((string) ($data['avatar_url'] ?? '')) ?: null,
        ];
        $link->save();

        return redirect()->route('user.links.settings.ar', $link)
            ->with('success', 'AR Business Card settings saved.');
    }

    protected function authorizeOwnership(Link $link): void
    {
        $userId = auth()->id();
        if ($link->user_id !== $userId) {
            // Workspace.scope middleware will already filter, but guard
            // explicitly in case the route is reached differently.
            abort(403);
        }
    }
}
