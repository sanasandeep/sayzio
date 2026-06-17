<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\SubscriptionTier;
use App\Modules\User\Support\PaidPageTemplates;
use Illuminate\Http\Request;

/**
 * Creator-side management for the standalone Paid Page link type.
 *
 * A Paid Page repackages the creator's existing monetized feed (posts,
 * subscription tiers, PPV, tipping) as a shareable, themeable link. This
 * controller only owns the per-link presentation config — the chosen
 * design template and the page-level public/gated toggle. Posts and tiers
 * are managed through the existing dashboards (they are per-creator, shared
 * across every Paid Page that creator publishes), so the editor links out
 * to them rather than rebuilding them.
 */
class PaidPageController extends Controller
{
    private function ownLinkOrFail(Link $link): void
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_if($link->type !== Link::TYPE_PAID_PAGE, 404);
    }

    public function editor(Request $request, Link $link)
    {
        $this->ownLinkOrFail($link);

        $owner = workspace_owner();

        $current = $link->settings['paid_page'] ?? [];
        $templateId = $current['template'] ?? PaidPageTemplates::DEFAULT_ID;
        if (!in_array($templateId, PaidPageTemplates::ids(), true)) {
            $templateId = PaidPageTemplates::DEFAULT_ID;
        }

        $postCount = CreatorPost::query()
            ->withoutGlobalScope('workspace')
            ->where('user_id', $owner->id)
            ->count();

        $tierCount = SubscriptionTier::query()
            ->where('user_id', $owner->id)
            ->where('is_active', true)
            ->where('is_free', false)
            ->count();

        return view('user.links.paid-page-editor', [
            'link'        => $link,
            'templates'   => PaidPageTemplates::all(),
            'templateId'  => $templateId,
            'isPublic'    => ($link->visibility ?? 'public') === 'public',
            'postCount'   => $postCount,
            'tierCount'   => $tierCount,
            'publicUrl'   => $link->getShortUrl(),
        ]);
    }

    public function update(Request $request, Link $link)
    {
        $this->ownLinkOrFail($link);

        $validated = $request->validate([
            'template'  => 'required|string|in:' . implode(',', PaidPageTemplates::ids()),
            'is_public' => 'nullable|boolean',
        ]);

        $settings = $link->settings ?? [];
        $settings['paid_page'] = array_merge($settings['paid_page'] ?? [], [
            'template' => $validated['template'],
        ]);
        $link->settings = $settings;

        // Page-level gate reuses the platform-native visibility column:
        // public => anyone can view; gated => viewers must be signed in
        // (the standard "registered" tier, enforced in RedirectController).
        $link->visibility = $request->boolean('is_public') ? 'public' : 'registered';
        $link->save();

        return back()->with('success', 'Paid Page design saved.');
    }
}
