<?php

namespace App\Modules\Common\Controllers;

use App\Modules\Common\Services\ViewerSession;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\CreatorSubscription;
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
            if (!$promo || !$promo->isUsable($tier)) {
                return back()->withErrors(['promo_code' => 'That code isn\'t valid for this tier.'])->withInput();
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
        return view('public.monetization.manage', compact('creator', 'sub'));
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
