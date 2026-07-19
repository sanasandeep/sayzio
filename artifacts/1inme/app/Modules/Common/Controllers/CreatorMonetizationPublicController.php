<?php

namespace App\Modules\Common\Controllers;

use App\Modules\Common\Services\ViewerSession;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\CreatorSubscription;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\SubscriptionPromoCode;
use App\Modules\User\Models\SubscriptionTier;
use App\Modules\User\Models\User;
use App\Services\Monetization\MonetizationCheckout;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Public-facing endpoints for the Creator Profile monetization
 * surface — these run for fans hitting /@handle. The viewer is
 * resolved from ViewerSession (NOT the workspace auth guard) so
 * subscribing on a creator's profile doesn't log the fan into
 * any dashboard.
 */
class CreatorMonetizationPublicController extends Controller
{
    public function subscribePage(Request $request, string $handle)
    {
        $creator = $this->creatorOr404($handle);
        $tiers   = SubscriptionTier::where('user_id', $creator->id)
            ->where('is_active', true)
            ->orderBy('sort_order')->get();
        $viewer  = ViewerSession::user();
        $existing = $viewer
            ? CreatorSubscription::where('fan_user_id', $viewer->id)->where('creator_user_id', $creator->id)->first()
            : null;
        return view('public.monetization.subscribe', compact('creator', 'tiers', 'viewer', 'existing'));
    }

    public function subscribe(Request $request, string $handle)
    {
        $creator = $this->creatorOr404($handle);
        $viewer  = $this->requireViewer($request);

        $data = $request->validate([
            'tier_id'    => 'required|integer',
            'cycle'      => 'nullable|in:monthly,yearly',
            'promo_code' => 'nullable|string|max:40',
            'return_url' => 'nullable|url',
        ]);
        $tier = SubscriptionTier::where('user_id', $creator->id)->whereKey($data['tier_id'])->where('is_active', true)->first();
        if (!$tier) return back()->with('error', 'Tier not found.');

        $promo = null;
        if (!empty($data['promo_code'])) {
            $promo = SubscriptionPromoCode::where('user_id', $creator->id)
                ->where('code', strtoupper(trim($data['promo_code'])))->first();
            if (!$promo) {
                return back()->withErrors(['promo_code' => 'We couldn\'t find that code.'])->withInput();
            }
            if ($reason = $promo->unusableReason($tier)) {
                return back()->withErrors(['promo_code' => $reason])->withInput();
            }
        }

        $r = app(MonetizationCheckout::class)->startSubscription(
            $viewer, $creator, $tier,
            $data['cycle'] ?? 'monthly',
            $promo,
            $data['return_url'] ?? null,
        );
        return redirect()->away($r['url']);
    }

    /**
     * Live promo-code check for the public subscribe page. Returns the
     * discounted price (or the explicit unusableReason()) as JSON so a
     * fan can validate a code before the full-page subscribe round-trip.
     * Mirrors the validation in subscribe() exactly.
     */
    public function previewPromo(Request $request, string $handle)
    {
        $creator = $this->creatorOr404($handle);
        $data = $request->validate([
            'tier_id'    => 'required|integer',
            'cycle'      => 'nullable|in:monthly,yearly',
            'promo_code' => 'required|string|max:40',
        ]);

        $tier = SubscriptionTier::where('user_id', $creator->id)
            ->whereKey($data['tier_id'])->where('is_active', true)->first();
        if (!$tier) {
            return response()->json(['ok' => false, 'reason' => 'Plan not found.'], 404);
        }
        if ($tier->is_free) {
            return response()->json(['ok' => false, 'reason' => 'This plan is free — no code needed.'], 422);
        }

        $promo = SubscriptionPromoCode::where('user_id', $creator->id)
            ->where('code', strtoupper(trim($data['promo_code'])))->first();
        if (!$promo) {
            return response()->json(['ok' => false, 'reason' => 'We couldn\'t find that code.']);
        }
        if ($reason = $promo->unusableReason($tier)) {
            return response()->json(['ok' => false, 'reason' => $reason]);
        }

        $cycle    = $data['cycle'] ?? 'monthly';
        $original = $tier->priceForCycle($cycle);
        $final    = $promo->applyTo($original);
        $currency = $tier->currency ?: 'USD';

        return response()->json([
            'ok'             => true,
            'code'           => $promo->code,
            'describe'       => $promo->describe(),
            'cycle'          => $cycle,
            'original_cents' => $original,
            'final_cents'    => $final,
            'currency'       => $currency,
            'original'       => $this->formatMoney($original, $currency),
            'final'          => $this->formatMoney($final, $currency),
            'savings'        => $this->formatMoney(max(0, $original - $final), $currency),
        ]);
    }

    protected function formatMoney(int $cents, string $currency): string
    {
        $symbol = match (strtoupper($currency)) {
            'USD', 'CAD', 'AUD' => '$',
            'EUR' => '€', 'GBP' => '£', 'JPY' => '¥', 'INR' => '₹',
            default => '',
        };
        return $symbol . number_format($cents / 100, 2) . ($symbol ? '' : ' ' . strtoupper($currency));
    }

    public function unlock(Request $request, string $handle, int $post)
    {
        $creator = $this->creatorOr404($handle);
        $viewer  = $this->requireViewer($request);
        $p = CreatorPost::query()->withoutGlobalScope('workspace')
            ->where('user_id', $creator->id)->whereKey($post)->first();
        if (!$p) abort(404);

        $r = app(MonetizationCheckout::class)->startPostUnlock($viewer, $p,
            $request->input('return_url') ?: route('creator-profile.show', ['handle' => $creator->handle ?: $creator->id]) . '#post-' . $p->id,
        );
        return redirect()->away($r['url']);
    }

    public function tip(Request $request, string $handle, ?int $post = null)
    {
        $creator = $this->creatorOr404($handle);
        $viewer  = $this->requireViewer($request);
        $data = $request->validate([
            'amount'    => 'required|numeric|min:1|max:1000',
            'note'      => 'nullable|string|max:280',
            'anonymous' => 'nullable|boolean',
            'return_url' => 'nullable|url',
        ]);
        $p = null;
        if ($post) {
            $p = CreatorPost::query()->withoutGlobalScope('workspace')
                ->where('user_id', $creator->id)->whereKey($post)->first();
        }
        $r = app(MonetizationCheckout::class)->startTip(
            $viewer, $creator,
            (int) round(((float) $data['amount']) * 100),
            $creator->preferred_currency ?: 'USD',
            $p,
            $data['note'] ?? null,
            (bool) ($data['anonymous'] ?? false),
            $data['return_url'] ?? null,
        );
        return redirect()->away($r['url']);
    }

    public function manage(Request $request, string $handle)
    {
        $creator = $this->creatorOr404($handle);
        $viewer  = $this->requireViewer($request);
        $sub = CreatorSubscription::with(['tier'])
            ->where('fan_user_id', $viewer->id)
            ->where('creator_user_id', $creator->id)->first();
        if (!$sub) return redirect()->route('creator-profile.show', ['handle' => $handle]);

        // Brief confirmation when a fan returns here after their active tier
        // actually changed (e.g. a switch completed in the provider's hosted
        // checkout). Mirrors the native mobile toast (Task #3042): we remember
        // the tier id last shown to this fan for this creator in the session,
        // and confirm only when the now-current tier differs from it. Backing
        // out without changing anything leaves the snapshot untouched, so no
        // false confirmation is shown.
        $snapKey  = 'sub_manage_tier.' . $creator->id;
        $lastSeen = $request->session()->get($snapKey);
        $current  = $sub->tier_id;
        $tierSwitched = ($lastSeen !== null && (int) $lastSeen !== (int) $current)
            ? ($sub->tier->name ?? 'your new tier')
            : null;
        $request->session()->put($snapKey, $current);

        return view('public.monetization.manage', compact('creator', 'sub', 'tierSwitched'));
    }

    public function cancel(Request $request, string $handle)
    {
        $creator = $this->creatorOr404($handle);
        $viewer  = $this->requireViewer($request);
        $sub = CreatorSubscription::where('fan_user_id', $viewer->id)->where('creator_user_id', $creator->id)->first();
        if (!$sub) abort(404);
        app(MonetizationCheckout::class)->cancelSubscription($sub, immediate: false);
        return back()->with('success', 'Your subscription will end at the close of the current period.');
    }

    public function resume(Request $request, string $handle)
    {
        $creator = $this->creatorOr404($handle);
        $viewer  = $this->requireViewer($request);
        $sub = CreatorSubscription::where('fan_user_id', $viewer->id)->where('creator_user_id', $creator->id)->first();
        if (!$sub) abort(404);
        $sub->cancel_at_period_end = false;
        $sub->canceled_at = null;
        $sub->status = CreatorSubscription::STATUS_ACTIVE;
        $sub->save();
        return back()->with('success', 'Subscription resumed.');
    }

    /**
     * Tip-Jar block endpoint. A fan hits this from a biolink block of
     * type `tip_jar`. We look up the link by alias, confirm the block
     * belongs to it and is a tip_jar, then delegate to startTip exactly
     * like the /@handle/tip route does.
     *
     * The fan must hold a ViewerSession (or be authenticated). If not,
     * we redirect them back to the biolink page with a flash so the
     * viewer OTP modal can open.
     */
    public function biolinkTip(Request $request, string $alias)
    {
        $link = Link::where('alias', $alias)->first();
        if (!$link) abort(404);

        $creator = $link->user;
        if (!$creator) abort(404);

        $viewer = $this->requireViewerForBiolink($request, $alias);

        $data = $request->validate([
            'block_id'  => 'required|integer',
            'amount'    => 'required|numeric|min:1|max:1000',
            'note'      => 'nullable|string|max:280',
            'anonymous' => 'nullable|boolean',
            'return_url' => 'nullable|url',
        ]);

        $block = BiolinkBlock::withoutGlobalScope('workspace')
            ->where('link_id', $link->id)
            ->where('type', 'tip_jar')
            ->whereKey((int) $data['block_id'])
            ->first();
        if (!$block) abort(404);

        $connection = $creator->defaultPaymentConnection();
        if (!$connection || !$connection->charges_enabled) {
            return redirect(url('/' . $alias))->with('error', 'Tips are not available for this creator right now.');
        }

        $returnUrl = $data['return_url'] ?? url('/' . $alias . '?tipped=1');

        $r = app(MonetizationCheckout::class)->startTip(
            $viewer, $creator,
            (int) round(((float) $data['amount']) * 100),
            $creator->preferred_currency ?: 'USD',
            null,
            $data['note'] ?? null,
            (bool) ($data['anonymous'] ?? false),
            $returnUrl,
            \App\Modules\User\Models\CreatorPaymentEvent::SOURCE_TIP_JAR,
        );
        return redirect()->away($r['url']);
    }

    protected function requireViewerForBiolink(Request $request, string $alias): User
    {
        $viewer = ViewerSession::user() ?: $request->user();
        if (!$viewer) {
            throw new HttpResponseException(
                redirect(url('/' . $alias))
                    ->with('viewer_login_required', true)
                    ->with('error', 'Please sign in with your email to send a tip.')
            );
        }
        return $viewer;
    }

    protected function creatorOr404(string $handle): User
    {
        $h = ltrim($handle, '@');
        $u = User::query()->whereRaw('LOWER(handle) = ?', [strtolower($h)])->first();
        if (!$u) abort(404);
        return $u;
    }

    protected function requireViewer(Request $request): User
    {
        $viewer = ViewerSession::user() ?: $request->user();
        if (!$viewer) {
            // Send the fan back to the creator profile with a flash;
            // the profile renders an inline viewer-OTP modal that the
            // existing react/comment flow already uses. We throw an
            // HttpResponseException directly (rather than abort()) so
            // Laravel returns the redirect verbatim without any
            // exception-handler interpretation.
            $handle = $request->route('handle');
            throw new HttpResponseException(
                redirect()
                    ->route('creator-profile.show', ['handle' => $handle])
                    ->with('viewer_login_required', true)
                    ->with('error', 'Please sign in with your email to continue.')
            );
        }
        return $viewer;
    }
}
