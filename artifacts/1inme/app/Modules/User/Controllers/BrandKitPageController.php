<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\Link;
use App\Modules\User\Support\BrandKitPageTemplates;
use Illuminate\Http\Request;

/**
 * Creator-side management for the standalone Brand / Press Kit link type
 * (Task #2663).
 *
 * A Brand / Press Kit page repackages the creator's saved AI Brand Kit
 * (palette, fonts, voice, taglines, boilerplate) as a polished, shareable
 * public page: logo downloads, copy-able colour swatches, the font pairing,
 * brand voice, socials and a contact line. This controller owns the per-link
 * presentation config stored under `links.settings['brand_kit']` plus the
 * page-level public / gated toggle. AI generation lives elsewhere — this
 * surface only consumes a kit the user already saved.
 *
 * Mirrors {@see PaidPageController}.
 */
class BrandKitPageController extends Controller
{
    private function ownLinkOrFail(Link $link): void
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_if($link->type !== Link::TYPE_BRAND_KIT, 404);
    }

    public function editor(Request $request, Link $link)
    {
        $this->ownLinkOrFail($link);

        $owner = workspace_owner();

        // Normalise whatever is stored (and back-fill from the default kit if
        // an older link predates the seed) so the editor always has a complete
        // shape to bind to.
        $current = $link->settings['brand_kit'] ?? null;
        if (!is_array($current) || empty($current)) {
            $defaultKit = BrandKit::where('user_id', $owner->id)
                ->orderByDesc('is_default')
                ->orderByDesc('id')
                ->first();
            $current = BrandKitPageTemplates::prefillFromKit($defaultKit, $owner);
        } else {
            $current = BrandKitPageTemplates::normalize($current);
        }

        $kits = BrandKit::where('user_id', $owner->id)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get(['id', 'name', 'is_default']);

        return view('user.links.brand-kit-editor', [
            'link'      => $link,
            'config'    => $current,
            'templates' => BrandKitPageTemplates::all(),
            'sections'  => BrandKitPageTemplates::SECTION_DEFAULTS,
            'kits'      => $kits,
            'isPublic'  => ($link->visibility ?? 'public') === 'public',
            'publicUrl' => $link->getShortUrl(),
        ]);
    }

    public function update(Request $request, Link $link)
    {
        $this->ownLinkOrFail($link);

        $validated = $request->validate([
            'template'      => 'required|string|in:' . implode(',', BrandKitPageTemplates::ids()),
            'is_public'     => 'nullable|boolean',
            'brand_name'    => 'nullable|string|max:120',
            'tagline'       => 'nullable|string|max:200',
            'about'         => 'nullable|string|max:2000',
            'boilerplate'   => 'nullable|string|max:4000',
            'contact_email' => 'nullable|email|max:160',
            'contact_url'   => 'nullable|url:http,https|max:2048',
            'palette'       => 'nullable|array',
            'fonts'         => 'nullable|array',
            'voice'         => 'nullable|array',
            'taglines'      => 'nullable|array',
            'taglines.*'    => 'nullable|string|max:200',
            'logos'         => 'nullable|array',
            'logos.*.label' => 'nullable|string|max:80',
            'logos.*.url'   => 'nullable|string|max:2048',
            'socials'       => 'nullable|array',
            'socials.*.label' => 'nullable|string|max:80',
            'socials.*.url'   => 'nullable|string|max:2048',
            'sections'      => 'nullable|array',
        ]);

        // Re-seed colours/fonts/voice/descriptors that the editor preserves as
        // hidden state but does not expose as separate inputs, then merge the
        // editable fields on top, and let the support class sanitise the whole.
        $existing = is_array($link->settings['brand_kit'] ?? null)
            ? $link->settings['brand_kit']
            : [];

        $merged = array_merge($existing, [
            'template'      => $validated['template'],
            'brand_name'    => $validated['brand_name'] ?? ($existing['brand_name'] ?? ''),
            'tagline'       => $validated['tagline'] ?? '',
            'about'         => $validated['about'] ?? '',
            'boilerplate'   => $validated['boilerplate'] ?? '',
            'contact_email' => $validated['contact_email'] ?? '',
            'contact_url'   => $validated['contact_url'] ?? '',
            'palette'       => $request->input('palette', $existing['palette'] ?? []),
            'fonts'         => $request->input('fonts', $existing['fonts'] ?? []),
            'voice'         => $request->input('voice', $existing['voice'] ?? []),
            'taglines'      => $request->input('taglines', $existing['taglines'] ?? []),
            'logos'         => $request->input('logos', $existing['logos'] ?? []),
            'socials'       => $request->input('socials', $existing['socials'] ?? []),
            'sections'      => $request->input('sections', []),
        ]);

        $settings = $link->settings ?? [];
        $settings['brand_kit'] = BrandKitPageTemplates::normalize($merged);
        $link->settings = $settings;

        // Page-level gate reuses the platform-native visibility column: public
        // => anyone can view; gated => viewers must be signed in (enforced in
        // RedirectController, like paid_page).
        $link->visibility = $request->boolean('is_public') ? 'public' : 'registered';
        $link->save();

        return back()->with('success', 'Brand / Press Kit saved.');
    }
}
