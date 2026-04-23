<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Services\LinkTrackingService;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\PollVote;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\Subscriber;
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
        $link = Link::where('alias', $alias)->where('type', 'biolink')->first();
        if (!$link || !$link->is_active) return $this->notFound('Biolink not found');

        $owner = $link->user;
        $viewer = $request->user();

        $gate = $this->checkVisibility($link, $viewer, $owner);
        if ($gate !== null) {
            return $this->fail($gate['message'], $gate['status'], $gate['code'], [
                'visibility' => $link->visibility,
                'owner'      => ['handle' => $owner?->handle, 'name' => $owner?->name],
            ]);
        }

        $blocks = BiolinkBlock::where('link_id', $link->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($b) => [
                'id'         => $b->id,
                'type'       => $b->type,
                'sort_order' => $b->sort_order,
                'parent_id'  => $b->parent_id,
                'settings'   => $b->settings,
            ])->all();

        return $this->ok([
            'biolink' => [
                'id'         => $link->id,
                'alias'      => $link->alias,
                'title'      => $link->title,
                'visibility' => $link->visibility,
                'seo_title'  => $link->seo_title,
                'seo_description' => $link->seo_description,
                'seo_image'  => $link->seo_image,
            ],
            'owner' => [
                'id'              => $owner?->id,
                'name'            => $owner?->name,
                'handle'          => $owner?->handle,
                'avatar'          => $owner?->avatar,
                'bio'             => $owner?->bio,
                'followers_count' => (int) ($owner?->followers_count ?? 0),
            ],
            'blocks' => $blocks,
        ]);
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
            return ['status' => 401, 'code' => 'auth_required', 'message' => 'Sign in required to view this biolink'];
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
        $link = Link::where('alias', $alias)->where('type', 'biolink')->first();
        if (!$link || !$link->is_active) return $this->notFound('Biolink not found');

        if (!$link->isAccessible()) {
            return $this->notFound('Biolink not available');
        }

        $owner  = $link->user;
        $viewer = $request->user();
        $gate   = $this->checkVisibility($link, $viewer, $owner);
        if ($gate !== null) {
            return $this->fail($gate['message'], $gate['status'], $gate['code']);
        }

        $this->trackingService->track($link, $request, $alias, 'mobile_app');

        return $this->ok(['tracked' => true]);
    }

    public function tap(Request $request, string $alias, int $blockId)
    {
        $link = Link::where('alias', $alias)->where('type', 'biolink')->first();
        if (!$link || !$link->is_active) return $this->notFound('Biolink not found');

        if (!$link->isAccessible()) {
            return $this->notFound('Biolink not available');
        }

        $owner  = $link->user;
        $viewer = $request->user();
        $gate   = $this->checkVisibility($link, $viewer, $owner);
        if ($gate !== null) {
            return $this->fail($gate['message'], $gate['status'], $gate['code']);
        }

        $block = BiolinkBlock::where('id', $blockId)
            ->where('link_id', $link->id)
            ->first();
        if (!$block) return $this->notFound('Block not found');

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

        $this->trackingService->trackBlockClick($link, $block, $destination, $request, $alias, 'mobile_app');

        return $this->ok(['tracked' => true]);
    }

    public function subscribe(Request $request, string $alias)
    {
        $link = Link::where('alias', $alias)->where('type', 'biolink')->first();
        if (!$link) return $this->notFound('Biolink not found');

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
        $link = Link::where('alias', $alias)->where('type', 'biolink')->first();
        if (!$link || !$link->is_active) return $this->notFound('Biolink not found');
        if (!$link->isAccessible()) return $this->notFound('Biolink not available');

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
        $link = Link::where('alias', $alias)->where('type', 'biolink')->first();
        if (!$link || !$link->is_active) return $this->notFound('Biolink not found');
        if (!$link->isAccessible()) return $this->notFound('Biolink not available');

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

        // Per-poll privacy gate: when "hide results until voted" is on,
        // refuse tallies until the requester has voted. Owner is exempt.
        // Vote identity matches pollVote's dedupe key.
        $hideUntilVoted = filter_var(
            $settings['hide_results_until_voted'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
        $isOwner = $viewer && $owner && (int) $viewer->id === (int) $owner->id;
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
        $biolink = Link::where('alias', $alias)->where('type', 'biolink')->first();
        if (!$biolink || !$biolink->is_active) return $this->notFound('Biolink not found');
        if (!$biolink->isAccessible()) return $this->notFound('Biolink not available');

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
        // and be an active ICS link with rsvp_enabled, otherwise refuse so
        // creators can't be DM-spammed via cross-account block edits.
        $link = Link::where('id', $eventLinkId)
            ->where('user_id', $biolink->user_id)
            ->where('type', 'ics')
            ->first();
        if (!$link || !$link->is_active) return $this->notFound('Event not found');
        if (!$link->isAccessible()) return $this->notFound('Event not available');

        $settings = $link->settings ?? [];
        if (empty($settings['rsvp_enabled'])) {
            return $this->fail('RSVPs are disabled for this event', 404, 'rsvp_disabled');
        }

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

        return $this->created([
            'recorded' => true,
            'rsvp_id'  => $rsvp->id,
            'response' => $rsvp->response,
        ]);
    }
}
