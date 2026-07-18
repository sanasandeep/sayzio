<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Services\LinkTrackingService;
use App\Modules\Common\Support\SitePagesContent;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkSlideDeck;
use App\Modules\User\Models\LinkSlideViewEvent;
use App\Modules\User\Models\PollVote;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Services\BiolinkExperimentService;
use App\Modules\User\Services\InboxForwarder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BiolinkController extends Controller
{
    use ApiResponses;

    public function __construct(protected LinkTrackingService $trackingService)
    {
    }

    public function show(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if ($link && !in_array($link->type, \App\Modules\User\Models\Link::BIOLINK_FAMILY, true)) $link = null;
        if (!$link || !$link->is_active) return $this->notFound('Link in Bio not found');

        $owner = $link->user;
        $viewer = $request->user();

        $gate = $this->checkVisibility($link, $viewer, $owner);
        if ($gate !== null) {
            return $this->fail($gate['message'], $gate['status'], $gate['code'], [
                'visibility' => $link->visibility,
                'owner'      => ['handle' => $owner?->handle, 'name' => $owner?->name],
            ]);
        }

        // Overlay any active scheduled theme so mobile viewers see the
        // same look as web during the activation window. Cron flips
        // `status=active` and writes settings to the row; this call is
        // the read-time safety net for the up-to-1-minute gap. Done
        // before A/B variant selection so any theme-driven settings
        // are visible to downstream rendering logic.
        app(\App\Modules\User\Services\BiolinkThemeResolver::class)->applyActiveTheme($link);

        // Honour any active layout A/B test: assign the visitor a sticky
        // variant and serve that variant's snapshot blocks. The mobile
        // client should send a stable `X-1INME-Visitor-Id` header so the
        // assignment persists across requests without cookies.
        $abService = app(BiolinkExperimentService::class);
        $abExp = $abService->activeFor($link);
        $abInfo = null;
        if ($abExp) {
            $assigned = $abService->assignVariant($request, $abExp);
            // renderableBlocks() branches internally on experiment mode:
            // for a manual A/B test it reads the frozen variant snapshot;
            // for adaptive (Task #3531) it reads the LIVE blocks and
            // reorders them per the visitor's assigned bandit arm. Either
            // way we get a flat top-level Collection<BiolinkBlock> back,
            // so the mobile payload shape stays identical for both modes.
            $blocks = $abService->renderableBlocks($abExp, $assigned)
                ->filter(fn ($b) => $b->is_active)
                ->flatMap(function ($b) {
                    $node = $this->decorateFormBlock([
                        'id'         => (int) $b->id,
                        'type'       => (string) $b->type,
                        'sort_order' => (int) $b->sort_order,
                        'parent_id'  => $b->parent_id,
                        'settings'   => $b->settings ?? [],
                    ]);
                    $children = collect($b->children ?? [])
                        ->filter(fn ($c) => $c->is_active)
                        ->map(fn ($c) => $this->decorateFormBlock([
                            'id'         => (int) $c->id,
                            'type'       => (string) $c->type,
                            'sort_order' => (int) $c->sort_order,
                            'parent_id'  => $c->parent_id,
                            'settings'   => $c->settings ?? [],
                        ]));
                    return collect([$node])->concat($children);
                })
                ->values()
                ->all();
            $abInfo = [
                'experiment_id' => $abExp->id,
                'variant'       => $assigned,
            ];
        } else {
            $blocks = BiolinkBlock::where('link_id', $link->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($b) => $this->decorateFormBlock([
                    'id'         => $b->id,
                    'type'       => $b->type,
                    'sort_order' => $b->sort_order,
                    'parent_id'  => $b->parent_id,
                    'settings'   => $b->settings,
                ]))->all();
        }

        $mode = (string) (data_get($link->settings, 'biolink.mode', 'list'));
        $slidesPayload = null;
        if ($mode === 'slides') {
            $deck = LinkSlideDeck::withoutGlobalScope('workspace')
                ->where('link_id', $link->id)
                ->where('is_published', true)
                ->first();
            if ($deck && is_array($deck->published_snapshot)) {
                $snap = $deck->published_snapshot;
                // Strip server-rendered HTML for the mobile client; the
                // mobile viewer renders blocks natively from `blocks[]`.
                $slides = collect($snap['slides'] ?? [])->map(function ($s) {
                    $blocks = collect($s['blocks'] ?? [])->map(fn ($b) => $this->decorateFormBlock([
                        'id'       => (int) ($b['id'] ?? 0),
                        'type'     => (string) ($b['type'] ?? ''),
                        'settings' => $b['settings'] ?? [],
                    ]))->values()->all();
                    return [
                        'id'         => $s['id'] ?? null,
                        'sort_order' => (int) ($s['sort_order'] ?? 0),
                        'title'      => $s['title'] ?? null,
                        'background' => $s['background'] ?? ['type' => 'color', 'color' => '#0f172a'],
                        'animation'  => $s['animation'] ?? ['enter' => 'fade', 'duration_ms' => 400],
                        'transition' => $s['transition'] ?? 'slide',
                        'blocks'     => $blocks,
                    ];
                })->values()->all();

                $slidesPayload = [
                    'deck_id'  => (int) $deck->id,
                    'version'  => (int) ($snap['version'] ?? $deck->version),
                    'settings' => $snap['settings'] ?? [],
                    'slides'   => $slides,
                ];
            }
        }

        return $this->ok([
            'biolink' => [
                'id'         => $link->id,
                'alias'      => $link->alias,
                'title'      => $link->title,
                'type'       => $link->type,
                'visibility' => $link->visibility,
                'seo_title'  => $link->seo_title,
                'seo_description' => $link->seo_description,
                'seo_image'  => $link->seo_image,
                'mode'       => $mode,
            ],
            'owner' => [
                'id'              => $owner?->id,
                'name'            => $owner?->name,
                'handle'          => $owner?->handle,
                'avatar'          => \App\Support\PublicStorageUrl::resolve($owner?->avatar),
                'bio'             => $owner?->bio,
                'followers_count' => (int) ($owner?->followers_count ?? 0),
            ],
            'blocks' => $blocks,
            'slides' => $slidesPayload,
            'ab_test' => $abInfo,
            'pairings' => SitePagesContent::linkTypePairingsFor('biolink'),
        ]);
    }

    /**
     * Enrich a `form` block with the resolved public form URL and pricing
     * metadata so the mobile client can open the priced web form directly
     * (in an in-app WebView) instead of bouncing to the whole biolink page,
     * and can hint that the form is paid before the visitor opens it.
     *
     * Non-form blocks pass through untouched. Forms are looked up without the
     * workspace global scope (public, stateless request) and memoised per
     * request to avoid duplicate queries when a page repeats a form block.
     *
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    protected function decorateFormBlock(array $block): array
    {
        if (($block['type'] ?? '') !== 'form') {
            return $block;
        }

        $formId = (int) data_get($block['settings'] ?? [], 'form_id', 0);
        if ($formId <= 0) {
            return $block;
        }

        if (!array_key_exists($formId, $this->formCache)) {
            $this->formCache[$formId] = \App\Modules\User\Models\Form::withoutGlobalScope('workspace')->find($formId);
        }
        $form = $this->formCache[$formId];
        if (!$form) {
            return $block;
        }

        $isPaid = $form->isPaid();
        $cfg    = $form->paymentConfig();
        $block['form'] = [
            'id'         => (int) $form->id,
            'public_url' => $form->getPublicUrl(),
            'is_paid'    => $isPaid,
            'payment'    => $isPaid ? [
                'mode'         => (string) ($cfg['mode'] ?? 'fixed'),
                'amount_cents' => (int) ($cfg['amount_cents'] ?? 0),
                'currency'     => strtoupper((string) ($cfg['currency'] ?? 'USD')),
                'label'        => $cfg['label'] ?? null,
            ] : null,
        ];

        return $block;
    }

    /** @var array<int, \App\Modules\User\Models\Form|null> */
    protected array $formCache = [];

    /**
     * Mobile slide-view event ping. Mirrors the web's /sl/{alias}/view
     * endpoint so a creator's slide-by-slide view counts include taps
     * from the mobile viewer.
     */
    public function slideView(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if ($link && !in_array($link->type, \App\Modules\User\Models\Link::BIOLINK_FAMILY, true)) $link = null;
        if (!$link || !$link->is_active) return $this->notFound('Link in Bio not found');
        if (!$link->isAccessible())     return $this->notFound('Link in Bio not available');

        $owner  = $link->user;
        $viewer = $request->user();
        $gate   = $this->checkVisibility($link, $viewer, $owner);
        if ($gate !== null) return $this->fail($gate['message'], $gate['status'], $gate['code']);

        $data = $request->validate([
            'slide_index'     => ['required', 'integer', 'min:0', 'max:200'],
            'page_session_id' => ['nullable', 'string', 'max:60'],
            'completed'       => ['nullable', 'boolean'],
            // Optional dwell-time ping fired when the mobile viewer leaves
            // the slide. Capped server-side at 10 minutes — see web
            // SlideEventController::view for the same rationale.
            'dwell_ms'        => ['nullable', 'integer', 'min:0', 'max:600000'],
        ]);

        $deck = LinkSlideDeck::withoutGlobalScope('workspace')
            ->where('link_id', $link->id)->where('is_published', true)->first();
        if (!$deck) return $this->ok(['tracked' => false]);

        try {
            LinkSlideViewEvent::create([
                'deck_id'         => $deck->id,
                'link_id'         => $link->id,
                'slide_index'     => (int) $data['slide_index'],
                'completed'       => (bool) ($data['completed'] ?? false),
                'dwell_ms'        => isset($data['dwell_ms']) ? (int) $data['dwell_ms'] : null,
                'page_session_id' => $data['page_session_id'] ?? null,
                'source'          => 'mobile_app',
            ]);
        } catch (\Throwable $e) {
            logger()->warning('slideView mobile track failed: ' . $e->getMessage());
        }

        return $this->ok(['tracked' => true]);
    }

    /**
     * Returns null if access allowed; otherwise an error descriptor.
     */
    protected function checkVisibility(Link $link, $viewer, $owner): ?array
    {
        $vis = $link->visibility ?? 'public';
        if ($vis === 'public') return null;
        if ($viewer && $owner && (int) $viewer->id === (int) $owner->id) return null;

        if (!$viewer) {
            return ['status' => 401, 'code' => 'auth_required', 'message' => 'Sign in required to view this Link in Bio'];
        }
        if ($vis === 'registered') return null;

        if ($vis === 'followers') {
            $follows = Follow::where('follower_id', $viewer->id)->where('creator_id', $owner->id)->exists();
            if ($follows) return null;
            return ['status' => 403, 'code' => 'follow_required', 'message' => 'Follow this creator to view'];
        }

        if ($vis === 'subscribers') {
            $isSub = Subscriber::where('user_id', $owner->id)
                ->where('email', $viewer->email)
                ->where('status', 'active')
                ->exists();
            if ($isSub) return null;
            return ['status' => 403, 'code' => 'subscribe_required', 'message' => 'Subscribe to this creator to view'];
        }

        return ['status' => 403, 'code' => 'forbidden', 'message' => 'Not allowed'];
    }

    public function visit(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if ($link && !in_array($link->type, \App\Modules\User\Models\Link::BIOLINK_FAMILY, true)) $link = null;
        if (!$link || !$link->is_active) return $this->notFound('Link in Bio not found');

        if (!$link->isAccessible()) {
            return $this->notFound('Link in Bio not available');
        }

        $owner  = $link->user;
        $viewer = $request->user();
        $gate   = $this->checkVisibility($link, $viewer, $owner);
        if ($gate !== null) {
            return $this->fail($gate['message'], $gate['status'], $gate['code']);
        }

        $this->trackingService->track($link, $request, $alias, 'mobile_app');

        // Mirror the per-variant visit count when an A/B test is running.
        $abService = app(BiolinkExperimentService::class);
        if ($abExp = $abService->activeFor($link)) {
            $variant = $abService->assignVariant($request, $abExp);
            $abService->recordVisit($abExp, $variant);
        }

        return $this->ok(['tracked' => true]);
    }

    public function tap(Request $request, string $alias, int $blockId)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if ($link && !in_array($link->type, \App\Modules\User\Models\Link::BIOLINK_FAMILY, true)) $link = null;
        if (!$link || !$link->is_active) return $this->notFound('Link in Bio not found');

        if (!$link->isAccessible()) {
            return $this->notFound('Link in Bio not available');
        }

        $owner  = $link->user;
        $viewer = $request->user();
        $gate   = $this->checkVisibility($link, $viewer, $owner);
        if ($gate !== null) {
            return $this->fail($gate['message'], $gate['status'], $gate['code']);
        }

        // When an A/B test is running we may need to look up the block
        // from the variant snapshot if the live row is gone (Variant A
        // blocks survive only in the snapshot once the creator starts
        // editing the live page).
        $abService = app(BiolinkExperimentService::class);
        $abExp = $abService->activeFor($link);
        $abVariant = $abExp ? $abService->assignVariant($request, $abExp) : null;

        $block = BiolinkBlock::where('id', $blockId)
            ->where('link_id', $link->id)
            ->first();
        if (!$block && $abExp && $abVariant) {
            $block = $abService->findSnapshotBlock($abExp, $blockId, $abVariant);
        }
        if (!$block) return $this->notFound('Block not found');

        // Task #1094 — enforce the cap server-side. The mobile UI hides
        // expired blocks before users can tap them, but a stale viewer
        // could still send a tap; without this gate it would consume
        // and offload a click past the cap.
        if ($block->isExpired()) {
            return $this->fail('This block is no longer available.', 410, 'block_expired');
        }

        $data = $request->validate([
            'destination_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $destination = $data['destination_url'] ?? '';
        $parsed = $destination !== '' ? parse_url($destination) : null;
        $isSafe = $parsed
            && isset($parsed['scheme'])
            && in_array(strtolower($parsed['scheme']), ['http', 'https', 'tel', 'mailto', 'sms'], true);
        if (!$isSafe) {
            $s = $block->settings ?? [];
            $linkData = $s['_link'] ?? [];
            $destination = (string) ($linkData['url'] ?? $s['link'] ?? $s['url'] ?? '');
        }

        // Race-safe gate: trackBlockClick returns null when the
        // atomic cap-reservation UPDATE didn't fire (cap reached) or
        // the schedule expired between our pre-check and the call.
        $tracked = $this->trackingService->trackBlockClick($link, $block, $destination, $request, $alias, 'mobile_app');
        if ($tracked === null) {
            return $this->fail('This block is no longer available.', 410, 'block_expired');
        }

        if ($abExp && $abVariant) {
            $abService->recordClick($abExp, $abVariant);
        }

        return $this->ok(['tracked' => true]);
    }

    public function subscribe(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if ($link && !in_array($link->type, \App\Modules\User\Models\Link::BIOLINK_FAMILY, true)) $link = null;
        if (!$link) return $this->notFound('Link in Bio not found');

        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'name'  => ['nullable', 'string', 'max:120'],
        ]);

        $sub = Subscriber::firstOrCreate(
            [
                'user_id' => $link->user_id,
                'email'   => strtolower($data['email']),
                'type'    => 'email',
            ],
            [
                'name'         => $data['name'] ?? null,
                'status'       => 'active',
                'source'       => 'api',
                'subscribed_at'=> now(),
            ]
        );

        if ($sub->status !== 'active') {
            $sub->forceFill(['status' => 'active', 'subscribed_at' => now(), 'unsubscribed_at' => null])->save();
        }

        // A/B conversion attribution for the mobile subscribe path.
        [$abExp, $abVariant] = app(BiolinkExperimentService::class)->resolveAssignment($request, $link);
        if ($abExp && $abVariant) {
            app(BiolinkExperimentService::class)->recordConversion($abExp, $abVariant);
        }

        return $this->created(['subscribed' => true, 'creator_id' => $link->user_id]);
    }

    /**
     * Record a poll vote from the mobile app for a poll-type biolink block.
     * Persists the choice server-side so native viewers no longer need to
     * bounce out to the web form. A second vote from the same viewer (same
     * auth user, or same ip+ua hash for anonymous viewers) on the same block
     * updates the existing row instead of creating a duplicate.
     */
    public function pollVote(Request $request, string $alias, int $blockId)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if ($link && !in_array($link->type, \App\Modules\User\Models\Link::BIOLINK_FAMILY, true)) $link = null;
        if (!$link || !$link->is_active) return $this->notFound('Link in Bio not found');
        if (!$link->isAccessible()) return $this->notFound('Link in Bio not available');

        $owner  = $link->user;
        $viewer = $request->user();
        $gate   = $this->checkVisibility($link, $viewer, $owner);
        if ($gate !== null) {
            return $this->fail($gate['message'], $gate['status'], $gate['code']);
        }

        $block = BiolinkBlock::where('id', $blockId)
            ->where('link_id', $link->id)
            ->where('type', 'poll')
            ->first();
        if (!$block) return $this->notFound('Poll not found');

        $data = $request->validate([
            'option_index' => ['required', 'integer', 'min:0', 'max:50'],
            'option_label' => ['nullable', 'string', 'max:191'],
        ]);

        $settings = $block->settings ?? [];
        $rawOptions = $settings['options'] ?? $settings['choices'] ?? $settings['items'] ?? [];
        $options = [];
        foreach ((array) $rawOptions as $opt) {
            if (is_string($opt)) {
                $options[] = $opt;
            } elseif (is_array($opt)) {
                $options[] = (string) ($opt['label'] ?? $opt['text'] ?? $opt['title'] ?? $opt['name'] ?? '');
            }
        }
        if ($data['option_index'] >= count($options)) {
            return $this->fail('Invalid option', 422, 'invalid_option');
        }

        $label = $data['option_label'] ?? $options[$data['option_index']] ?? null;

        $fingerprint = $viewer
            ? 'u:' . $viewer->id
            : substr(hash('sha256', $request->ip() . '|' . ($request->userAgent() ?? '')), 0, 32);

        $vote = PollVote::updateOrCreate(
            [
                'block_id'          => $block->id,
                'voter_fingerprint' => $fingerprint,
            ],
            [
                'link_id'      => $link->id,
                'option_index' => (int) $data['option_index'],
                'option_label' => $label,
                'user_id'      => $viewer?->id,
                'source'       => 'mobile_app',
                'ip_address'   => $request->ip(),
                'user_agent'   => substr((string) $request->userAgent(), 0, 500),
            ]
        );

        // Mirror the in-page click counter so creator analytics still
        // reflect the engagement, just like the web overlay would.
        $this->trackingService->trackBlockClick(
            $link, $block, '', $request, $alias, 'mobile_app'
        );

        return $this->ok([
            'recorded'     => true,
            'vote_id'      => $vote->id,
            'option_index' => $vote->option_index,
            'option_label' => $vote->option_label,
        ]);
    }

    /**
     * Return aggregated tallies for a poll block so viewers can see how
     * their pick compares to everyone else's. Mirrors the visibility gate
     * used by the page itself: a poll on a follower-gated biolink is only
     * readable by the owner, registered followers, etc. Results include
     * every configured option (even ones with zero votes); votes whose
     * option_index no longer maps to a current option (e.g. the creator
     * removed it later) are dropped so percentages still add up to 100.
     */
    public function pollResults(Request $request, string $alias, int $blockId)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if ($link && !in_array($link->type, \App\Modules\User\Models\Link::BIOLINK_FAMILY, true)) $link = null;
        if (!$link || !$link->is_active) return $this->notFound('Link in Bio not found');
        if (!$link->isAccessible()) return $this->notFound('Link in Bio not available');

        $owner  = $link->user;
        $viewer = $request->user();
        $gate   = $this->checkVisibility($link, $viewer, $owner);
        if ($gate !== null) {
            return $this->fail($gate['message'], $gate['status'], $gate['code']);
        }

        $block = BiolinkBlock::where('id', $blockId)
            ->where('link_id', $link->id)
            ->where('type', 'poll')
            ->first();
        if (!$block) return $this->notFound('Poll not found');

        $settings = $block->settings ?? [];

        $isOwner = $viewer && $owner && (int) $viewer->id === (int) $owner->id;

        // Per-poll deadline gate: when "reveal results at" is set, refuse
        // tallies for EVERYONE (including the owner) until that timestamp
        // passes. Takes precedence over hide_results_until_voted because
        // it's the stricter mode.
        $revealAtRaw = $settings['reveal_results_at'] ?? null;
        $revealAt = null;
        if (is_string($revealAtRaw) && trim($revealAtRaw) !== '') {
            try {
                $revealAt = \Carbon\Carbon::parse($revealAtRaw);
            } catch (\Throwable $e) {
                $revealAt = null;
            }
        }
        if ($revealAt && $revealAt->isFuture()) {
            return $this->fail(
                'Results visible after ' . $revealAt->toIso8601String(),
                403,
                'results_locked',
                [
                    'hidden'    => true,
                    'reveal_at' => $revealAt->toIso8601String(),
                ]
            );
        }

        // Per-poll privacy gate: when "hide results until voted" is on,
        // refuse tallies until the requester has voted. Owner is exempt.
        // Vote identity matches pollVote's dedupe key.
        $hideUntilVoted = filter_var(
            $settings['hide_results_until_voted'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
        if ($hideUntilVoted && !$isOwner) {
            $fingerprint = $viewer
                ? 'u:' . $viewer->id
                : substr(hash('sha256', $request->ip() . '|' . ($request->userAgent() ?? '')), 0, 32);
            $hasVoted = PollVote::where('block_id', $block->id)
                ->where('voter_fingerprint', $fingerprint)
                ->exists();
            if (!$hasVoted) {
                return $this->fail(
                    'Vote to see results',
                    403,
                    'vote_required',
                    ['hidden' => true]
                );
            }
        }

        $rawOptions = $settings['options'] ?? $settings['choices'] ?? $settings['items'] ?? [];
        $labels = [];
        foreach ((array) $rawOptions as $opt) {
            if (is_string($opt)) {
                $labels[] = $opt;
            } elseif (is_array($opt)) {
                $labels[] = (string) ($opt['label'] ?? $opt['text'] ?? $opt['title'] ?? $opt['name'] ?? '');
            }
        }

        $counts = PollVote::where('block_id', $block->id)
            ->selectRaw('option_index, COUNT(*) as c')
            ->groupBy('option_index')
            ->pluck('c', 'option_index')
            ->all();

        // Only sum votes that still map to a current option — otherwise
        // a creator who shrunk the option list would see percentages that
        // don't add up to 100 with no row to explain the missing share.
        $options = [];
        $total = 0;
        foreach ($labels as $i => $label) {
            $count = (int) ($counts[$i] ?? 0);
            $total += $count;
            $options[] = [
                'index' => $i,
                'label' => (string) $label,
                'count' => $count,
            ];
        }
        foreach ($options as &$row) {
            $row['percent'] = $total > 0 ? (int) round(($row['count'] / $total) * 100) : 0;
        }
        unset($row);

        return $this->ok([
            'block_id'    => $block->id,
            'total_votes' => $total,
            'options'     => $options,
        ]);
    }

    /**
     * Submit an RSVP from the mobile biolink viewer. The mobile screen
     * always operates on a biolink alias; the actual event lives on a
     * separate ICS-type link referenced by the RSVP block's
     * `event_link_id` setting (matching the web's biolink-block-render
     * partial). We resolve the event link server-side from the block so
     * the mobile client doesn't need to know its alias up front.
     */
    public function rsvpSubmit(Request $request, string $alias, int $blockId)
    {
        $biolink = Link::resolveByAlias($alias, $request->getHost());
        if ($biolink && !in_array($biolink->type, \App\Modules\User\Models\Link::BIOLINK_FAMILY, true)) $biolink = null;
        if (!$biolink || !$biolink->is_active) return $this->notFound('Link in Bio not found');
        if (!$biolink->isAccessible()) return $this->notFound('Link in Bio not available');

        $owner  = $biolink->user;
        $viewer = $request->user();
        $gate   = $this->checkVisibility($biolink, $viewer, $owner);
        if ($gate !== null) {
            return $this->fail($gate['message'], $gate['status'], $gate['code']);
        }

        $block = BiolinkBlock::where('id', $blockId)
            ->where('link_id', $biolink->id)
            ->where('type', 'rsvp')
            ->first();
        if (!$block) return $this->notFound('RSVP block not found');

        $blockSettings = $block->settings ?? [];
        $eventLinkId   = $blockSettings['event_link_id'] ?? null;
        if (!$eventLinkId) {
            return $this->fail('RSVP block is not configured for an event', 422, 'event_not_configured');
        }

        // Mirror the web partial: event link must belong to the same user
        // and be an active ICS link with RSVP available, otherwise refuse so
        // creators can't be DM-spammed via cross-account block edits.
        $link = Link::where('id', $eventLinkId)
            ->where('user_id', $biolink->user_id)
            ->where('type', 'ics')
            ->first();
        if (!$link || !$link->is_active) return $this->notFound('Event not found');
        if (!$link->isAccessible()) return $this->notFound('Event not available');

        // Task #3674: RSVP is available by default for any free event unless
        // the organizer explicitly opted out (mirrors RedirectController).
        if (!\App\Modules\Common\Controllers\RedirectController::isRsvpAvailable($link)) {
            return $this->fail('RSVPs are disabled for this event', 404, 'rsvp_disabled');
        }

        $settings = $link->settings ?? [];

        $allowPlusOnes = !empty($settings['rsvp_allow_plus_ones']);
        $collectPhone  = !empty($settings['rsvp_collect_phone']);

        $rules = [
            'name'      => ['required', 'string', 'max:120'],
            'email'     => ['nullable', 'email', 'max:160'],
            'response'  => ['required', 'in:yes,no,maybe'],
            'plus_ones' => ['nullable', 'integer', 'min:0', 'max:20'],
            'message'   => ['nullable', 'string', 'max:1000'],
        ];
        if ($collectPhone) $rules['phone'] = ['nullable', 'string', 'max:40'];
        $data = $request->validate($rules);

        $rsvp = Rsvp::create([
            'link_id'         => $link->id,
            'source_block_id' => $block->id,
            'name'            => $data['name'],
            'email'           => $data['email'] ?? null,
            'phone'           => $data['phone'] ?? null,
            'response'        => $data['response'],
            'plus_ones'       => $allowPlusOnes ? (int) ($data['plus_ones'] ?? 0) : 0,
            'message'         => $data['message'] ?? null,
            'source'          => 'mobile_app',
            'ip_address'      => $request->ip(),
            'user_agent'      => substr((string) $request->userAgent(), 0, 500),
        ]);

        // Account-level forwarding rules — same hook the web form uses so
        // mobile RSVPs reach the creator's inbox/email/webhook destinations.
        try {
            $rsvp->setRelation('link', $link);
            app(InboxForwarder::class)->dispatchForRsvp($link->user_id, $rsvp);
        } catch (\Throwable $e) {
            logger()->warning('Inbox forwarder (rsvp api) failed: ' . $e->getMessage());
        }

        // A/B conversion attribution for the mobile RSVP path.
        [$abExp, $abVariant] = app(BiolinkExperimentService::class)->resolveAssignment($request, $link);
        if ($abExp && $abVariant) {
            app(BiolinkExperimentService::class)->recordConversion($abExp, $abVariant);
        }

        return $this->created([
            'recorded' => true,
            'rsvp_id'  => $rsvp->id,
            'response' => $rsvp->response,
        ]);
    }
}
