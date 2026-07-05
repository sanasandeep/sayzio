<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\SocialAccountConnection;
use App\Modules\User\Models\SocialProof;
use App\Modules\User\Services\SocialFollowers\FollowerFetcherRegistry;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class SocialAccountController extends Controller
{
    use ApiResponses;

    public function connections(Request $request)
    {
        $items = SocialAccountConnection::where('user_id', $request->user()->id)
            ->orderBy('platform')
            ->get();
        return $this->ok([
            'items'     => $items->map(fn ($c) => $this->transformConnection($c))->all(),
            'platforms' => collect(SocialAccountConnection::PLATFORM_META)
                ->map(fn ($meta, $key) => ['platform' => $key, 'label' => $meta['label'] ?? ucfirst($key)])
                ->values()->all(),
        ]);
    }

    public function connect(Request $request)
    {
        $data = $request->validate([
            'platform'     => ['required', 'string', Rule::in(array_keys(SocialAccountConnection::PLATFORM_META))],
            'handle'       => ['required', 'string', 'max:191'],
            'access_token' => ['nullable', 'string', 'max:4096'],
        ]);
        $handle = ltrim(trim($data['handle']), '@');

        $c = SocialAccountConnection::updateOrCreate(
            [
                'user_id'  => $request->user()->id,
                'platform' => $data['platform'],
                'handle'   => $handle,
            ],
            [
                'access_token'             => $data['access_token'] ?? null,
                'last_refresh_status'      => 'pending',
                'last_refresh_error'       => null,
                'consecutive_failures'     => 0,
                'last_failure_notified_at' => null,
            ]
        );

        try {
            app(FollowerFetcherRegistry::class)->refresh($c);
        } catch (\Throwable $e) {}

        return $this->created(['connection' => $this->transformConnection($c->fresh())]);
    }

    public function refresh(Request $request, int $id)
    {
        $c = SocialAccountConnection::where('user_id', $request->user()->id)->find($id);
        if (!$c) return $this->notFound('Connection not found');
        try {
            app(FollowerFetcherRegistry::class)->refresh($c);
        } catch (\Throwable $e) {}
        return $this->ok(['connection' => $this->transformConnection($c->fresh())]);
    }

    public function disconnect(Request $request, int $id)
    {
        $c = SocialAccountConnection::where('user_id', $request->user()->id)->find($id);
        if (!$c) return $this->notFound('Connection not found');
        $c->delete();
        return $this->noContent();
    }

    /**
     * Task #3588: mobile parity for the "Searchable in public" toggle on
     * the Connected Accounts page — surfaces the connection in caller-ID
     * enrichment, the Dialer universal finder, and public search.
     */
    public function updateSearchable(Request $request, int $id)
    {
        $c = SocialAccountConnection::where('user_id', $request->user()->id)->find($id);
        if (!$c) return $this->notFound('Connection not found');
        $data = $request->validate(['is_searchable' => ['required', 'boolean']]);
        $c->forceFill(['is_searchable' => $data['is_searchable']])->save();
        return $this->ok(['connection' => $this->transformConnection($c->fresh())]);
    }

    public function socialProofs(Request $request)
    {
        $items = SocialProof::where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();
        return $this->ok([
            'items' => $items->map(fn ($p) => $this->transformProof($p))->all(),
            'types' => collect(SocialProof::TYPES)->map(fn ($label, $type) => [
                'type'  => $type,
                'label' => $label,
            ])->values()->all(),
        ]);
    }

    public function storeProof(Request $request)
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:120'],
            'type'      => ['required', 'string', Rule::in(array_keys(SocialProof::TYPES))],
            'is_active' => ['nullable', 'boolean'],
        ]);
        // The sanctum API path doesn't run SetActiveWorkspace, so without an
        // explicit assignment the new proof lands with workspace_id = null and
        // is hidden from the workspace-scoped web Buzz list. Derive the user's
        // workspace WITHOUT binding `current_workspace` (binding would also
        // activate the BelongsToWorkspace read-side global scope for the rest
        // of the request). The stateless sanctum request has no session, so
        // this matches WorkspaceContext's own fallback.
        $ws = $request->user()->accessibleWorkspaces()->first()
            ?? $request->user()->ensureDefaultWorkspace();

        $p = new SocialProof([
            'user_id'   => $request->user()->id,
            'name'      => $data['name'],
            'type'      => $data['type'],
            'is_active' => $data['is_active'] ?? true,
            'design'    => SocialProof::defaultDesign(),
            'targeting' => SocialProof::defaultTargeting(),
            'settings'  => [],
            'notifications' => [SocialProof::newNotification($data['type'], $data['name'])],
        ]);
        if ($ws) {
            $p->workspace_id = $ws->id;
        }
        $p->save();
        return $this->created(['proof' => $this->transformProof($p)]);
    }

    public function updateProof(Request $request, int $id)
    {
        $p = SocialProof::where('user_id', $request->user()->id)->find($id);
        if (!$p) return $this->notFound('Social proof not found');
        $data = $request->validate([
            'name'      => ['sometimes', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $p->fill($data)->save();
        return $this->ok(['proof' => $this->transformProof($p->fresh())]);
    }

    public function destroyProof(Request $request, int $id)
    {
        $p = SocialProof::where('user_id', $request->user()->id)->find($id);
        if (!$p) return $this->notFound('Social proof not found');
        $p->delete();
        return $this->noContent();
    }

    protected function transformConnection(SocialAccountConnection $c): array
    {
        return [
            'id'                  => $c->id,
            'platform'            => $c->platform,
            'platform_label'      => SocialAccountConnection::platformLabel($c->platform),
            'handle'              => $c->handle,
            'display_name'        => $c->display_name,
            'profile_url'         => $c->profile_url,
            'avatar_url'          => $c->avatar_url,
            'follower_count'      => (int) ($c->follower_count ?? 0),
            'last_refreshed_at'   => optional($c->last_refreshed_at)->toIso8601String(),
            'last_refresh_status' => $c->last_refresh_status,
            'last_refresh_error'  => $c->last_refresh_error,
            'is_searchable'       => (bool) $c->is_searchable,
            'sync_summary'        => $c->syncSummary(),
        ];
    }

    protected function transformProof(SocialProof $p): array
    {
        return [
            'id'          => $p->id,
            'uuid'        => $p->uuid,
            'name'        => $p->name,
            'type'        => $p->type,
            'type_label'  => $p->typeLabel(),
            'is_active'   => (bool) $p->is_active,
            'impressions' => (int) $p->impressions,
            'clicks'      => (int) $p->clicks,
            'conversions' => (int) $p->conversions,
            'created_at'  => optional($p->created_at)->toIso8601String(),
        ];
    }
}
