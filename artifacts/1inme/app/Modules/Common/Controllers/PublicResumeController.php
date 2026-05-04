<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\GeoIpService;
use App\Modules\Common\Services\ViewerSession;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Resume;
use App\Modules\User\Models\ResumeView;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Public renderer for /{handle}/resume.
 *
 * Resolves the page from the User.handle, applies the same visibility
 * vocabulary used by Link (public / registered / followers / subscribers
 * / password), enforces optional share expiration, increments a
 * per-resume view counter for non-owners, and emits OG / Twitter /
 * JSON-LD Person tags so search engines and social platforms render
 * rich previews.
 */
class PublicResumeController extends Controller
{
    public function show(Request $request, string $handle)
    {
        $user = User::where('handle', $handle)->first();
        if (!$user) {
            // Fallback to "user{id}" when the row never claimed a handle —
            // matches User::publicHandle() so saved/shared URLs keep working.
            if (preg_match('/^user(\d+)$/', $handle, $m)) {
                $user = User::find((int) $m[1]);
            }
        }
        abort_unless($user, 404);

        $resume = $user->resume()->first();
        abort_unless($resume, 404);

        // Owner sees their resume regardless of publish state so they can
        // share their preview link before flipping it public.
        $viewerId = ViewerSession::id() ?: optional($request->user())->id;
        $isOwner  = $viewerId && (int) $viewerId === (int) $user->id;

        if (!$isOwner) {
            abort_unless($resume->is_public, 404);

            // Expired shares are gated regardless of visibility tier so a
            // password-protected link with a deadline can't be reused
            // forever once the deadline passes.
            if ($resume->isShareExpired()) {
                return $this->expired($resume, $user);
            }

            if ($gated = $this->enforceVisibility($request, $resume, $user, $viewerId)) {
                return $gated;
            }
        }

        // Per-IP-per-day uniqueness so refreshes don't inflate the count.
        if (!$isOwner) {
            $bot = $this->looksLikeBot($request->userAgent() ?? '');
            $key = sprintf('resume_view:%d:%s:%s', $resume->id, $request->ip(), now()->toDateString());
            if (!$bot && !cache()->has($key)) {
                $resume->increment('view_count');
                $this->logView($request, $resume, $viewerId);
                cache()->put($key, 1, now()->addHours(12));
            }
        }

        $resume->load('items', 'user');

        return response()->view('common.resume-public', [
            'user'   => $user,
            'resume' => $resume,
            'isOwner' => $isOwner,
        ]);
    }

    /**
     * POST handler for the password-unlock form. Stores the unlocked
     * flag in the session under a resume-scoped key so the visitor
     * doesn't have to re-enter the password on every refresh.
     *
     * Wrong attempts are rate-limited per (resume, IP) so guessing the
     * password against a leaked URL is not feasible.
     */
    public function unlock(Request $request, string $handle)
    {
        $user = User::where('handle', $handle)->first();
        abort_unless($user, 404);
        $resume = $user->resume()->first();
        abort_unless($resume && $resume->is_public, 404);

        if ($resume->isShareExpired()) {
            return $this->expired($resume, $user);
        }

        if ($resume->visibility !== 'password' || !filled($resume->password)) {
            return redirect()->to('/' . $user->publicHandle() . '/resume');
        }

        // 8 attempts / 5 minutes per IP+resume. After that the prompt
        // returns 429 with a Retry-After hint until the bucket drains.
        $rateKey = sprintf('resume_unlock:%d:%s', $resume->id, sha1((string) $request->ip()));
        if (RateLimiter::tooManyAttempts($rateKey, 8)) {
            $retryAfter = RateLimiter::availableIn($rateKey);
            return response()->view('common.resume-public', [
                'user'        => $user,
                'resume'      => $resume,
                'isOwner'     => false,
                'lockedError' => "Too many attempts. Try again in {$retryAfter}s.",
            ], 429, ['Retry-After' => (string) $retryAfter]);
        }

        $supplied = (string) $request->input('password', '');
        if (!Hash::check($supplied, $resume->password)) {
            RateLimiter::hit($rateKey, 300);
            return response()->view('common.resume-public', [
                'user'        => $user,
                'resume'      => $resume,
                'isOwner'     => false,
                'lockedError' => 'Incorrect password.',
            ], 401);
        }

        // Successful unlock — clear the throttle bucket and remember
        // the unlock under a key that includes the resume's revision
        // counter so a future "Revoke share" forces a re-prompt.
        RateLimiter::clear($rateKey);
        session([$resume->unlockSessionKey() => true]);
        return redirect()->to('/' . $user->publicHandle() . '/resume');
    }

    /**
     * Apply visibility tier (mirrors RedirectController::enforceBiolinkVisibility).
     * Returns a response (gated / password screen) or null when allowed.
     */
    protected function enforceVisibility(Request $request, Resume $resume, User $user, ?int $viewerId)
    {
        $vis = $resume->visibility ?: 'public';
        if ($vis === 'public') return null;

        if ($vis === 'registered' && !$viewerId) {
            return $this->gated($resume, $user, 'registered');
        }

        if ($vis === 'followers') {
            $following = $viewerId && Follow::where('follower_id', $viewerId)
                ->where('creator_id', $user->id)->exists();
            if (!$following) return $this->gated($resume, $user, 'followers');
        }

        if ($vis === 'subscribers') {
            $subscribed = $viewerId && Subscriber::where('user_id', $user->id)
                ->where('status', 'active')
                ->whereIn('email', function ($q) use ($viewerId) {
                    $q->select('email')->from('users')->where('id', $viewerId);
                })->exists();
            if (!$subscribed) return $this->gated($resume, $user, 'subscribers');
        }

        if ($vis === 'password') {
            if (!filled($resume->password)) return null; // mis-config: treat as public
            if (session($resume->unlockSessionKey())) return null;
            return response()->view('common.resume-public', [
                'user'        => $user,
                'resume'      => $resume,
                'isOwner'     => false,
                'lockedError' => null,
            ], 401);
        }

        return null;
    }

    /**
     * Render the shared `gated` template by faking a Link-shaped object so
     * the existing UI works without a parallel template.
     */
    protected function gated(Resume $resume, User $user, string $reason)
    {
        $fakeLink = (object) [
            'title' => trim($resume->sections['header']['name'] ?? '') ?: ($user->name . "'s resume"),
            'user'  => $user,
        ];
        return response()->view('common.gated', ['link' => $fakeLink, 'reason' => $reason], 401);
    }

    /**
     * Render an "expired share" page. Re-uses the locked-card template
     * with a friendlier copy so the visitor knows the link is dead.
     */
    protected function expired(Resume $resume, User $user)
    {
        return response()->view('common.resume-public', [
            'user'         => $user,
            'resume'       => $resume,
            'isOwner'      => false,
            'lockedError'  => null,
            'shareExpired' => true,
        ], 410);
    }

    protected function looksLikeBot(string $ua): bool
    {
        if ($ua === '') return true;
        return (bool) preg_match('/bot|crawler|spider|crawling|preview|facebookexternalhit|slackbot|discordbot|whatsapp|twitterbot|linkedinbot/i', $ua);
    }

    /**
     * Append a row to resume_views for the management audit log.
     * Best-effort: failure to look up GeoIP or a database hiccup must
     * never break the actual page render.
     */
    protected function logView(Request $request, Resume $resume, ?int $viewerId): void
    {
        try {
            $country = null;
            try {
                $country = app(GeoIpService::class)->detectCountry((string) $request->ip());
            } catch (\Throwable $e) {
                // Geo lookups depend on a third-party service — silent fallback.
            }

            $handle = null;
            if ($viewerId) {
                $viewer = User::find($viewerId);
                $handle = $viewer ? $viewer->publicHandle() : null;
            }

            $referrer = (string) ($request->headers->get('referer') ?? '');
            // Strip own-site referrers so the log surfaces the actual
            // origin (recruiter inbox, LinkedIn message, etc) rather
            // than a meaningless internal hop.
            if ($referrer !== '') {
                $host = parse_url($referrer, PHP_URL_HOST);
                if ($host && str_ends_with($host, parse_url(config('app.url'), PHP_URL_HOST) ?? '')) {
                    $referrer = '';
                }
            }

            ResumeView::create([
                'resume_id'      => $resume->id,
                'viewer_user_id' => $viewerId,
                'viewer_handle'  => $handle,
                'country_code'   => $country,
                'referrer'       => $referrer !== '' ? mb_substr($referrer, 0, 512) : null,
                'user_agent'     => mb_substr((string) $request->userAgent(), 0, 255),
                'ip_hash'        => sha1((string) $request->ip()),
                'viewed_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            // best-effort — never let logging break the page render.
        }
    }
}
