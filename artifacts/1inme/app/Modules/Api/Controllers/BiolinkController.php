<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Services\LinkTrackingService;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Subscriber;
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
}
