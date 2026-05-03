<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * Queued thank-yous (the "Pending thanks" panel in the browser
 * extension). Persisted under `workspaces.settings` (`pending_thanks`
 * key) so the queue follows the creator across browsers and survives
 * an extension reinstall — matching how `thank_templates` are synced.
 *
 * Last-write-wins reconciliation by `updated_at_ms`. The server caps
 * the queue size and TTL-prunes on read so a misbehaving client can't
 * blow up the workspace settings blob.
 */
class PendingThankController extends Controller
{
    use ApiResponses;

    protected const MAX_ITEMS    = 50;
    protected const TTL_MS       = 30 * 24 * 60 * 60 * 1000; // 30 days
    protected const CHANNELS     = ['email', 'x', 'linkedin'];

    public function show(Request $request)
    {
        $ws = $this->resolveWorkspace($request);
        if (!$ws) return $this->forbidden('No accessible workspace');

        return $this->ok($this->extract($ws));
    }

    public function update(Request $request)
    {
        $ws = $this->resolveWorkspace($request);
        if (!$ws) return $this->forbidden('No accessible workspace');

        $data = $request->validate([
            // Intentionally NO `max:` rule on `items` — the queue is
            // capped server-side (drop oldest first) after dedupe and
            // TTL pruning so a syncing client that briefly exceeds the
            // cap converges instead of hard-failing with 422. We do
            // bound the upper end loosely to reject obviously abusive
            // payloads (10x the cap) before they hit the dedupe loop.
            'items'                  => ['present', 'array', 'max:' . (self::MAX_ITEMS * 10)],
            'items.*.id'             => ['required', 'string', 'max:64'],
            'items.*.templateId'     => ['required', 'string', 'max:64'],
            'items.*.channel'        => ['required', Rule::in(self::CHANNELS)],
            'items.*.subject'        => ['nullable', 'string', 'max:200'],
            'items.*.body'           => ['required', 'string', 'max:4000'],
            'items.*.recipient'      => ['nullable', 'string', 'max:320'],
            'items.*.pageUrl'        => ['required', 'string', 'max:2048'],
            'items.*.matchedUrl'     => ['required', 'string', 'max:2048'],
            'items.*.anchor'         => ['nullable', 'string', 'max:500'],
            'items.*.createdAt'      => ['required', 'integer', 'min:0'],
            'updated_at_ms'          => ['nullable', 'integer', 'min:0'],
        ]);

        // Dedupe by id (first wins) and TTL-prune so we never store
        // anything older than 30 days even if the client missed it.
        $cutoff = (int) (now()->getPreciseTimestamp(3)) - self::TTL_MS;
        $seen   = [];
        $items  = [];
        foreach ($data['items'] as $it) {
            $id = (string) $it['id'];
            if (isset($seen[$id])) continue;
            if ((int) $it['createdAt'] < $cutoff) continue;
            $seen[$id] = true;
            $items[] = [
                'id'         => $id,
                'templateId' => (string) $it['templateId'],
                'channel'    => (string) $it['channel'],
                'subject'    => (string) ($it['subject'] ?? ''),
                'body'       => (string) $it['body'],
                'recipient'  => isset($it['recipient']) && $it['recipient'] !== '' ? (string) $it['recipient'] : null,
                'pageUrl'    => (string) $it['pageUrl'],
                'matchedUrl' => (string) $it['matchedUrl'],
                'anchor'     => (string) ($it['anchor'] ?? ''),
                'createdAt'  => (int) $it['createdAt'],
            ];
        }
        // Cap the queue. Drop oldest first to mirror the client cap.
        if (count($items) > self::MAX_ITEMS) {
            usort($items, fn ($a, $b) => $a['createdAt'] <=> $b['createdAt']);
            $items = array_slice($items, count($items) - self::MAX_ITEMS);
        }

        $settings = (array) ($ws->settings ?? []);
        $settings['pending_thanks'] = [
            'items'         => $items,
            'updated_at_ms' => (int) (now()->getPreciseTimestamp(3)),
        ];
        $ws->settings = $settings;
        $ws->save();

        return $this->ok($this->extract($ws->fresh()));
    }

    protected function extract(Workspace $ws): array
    {
        $blob   = (array) (data_get($ws->settings, 'pending_thanks', []) ?? []);
        $cutoff = (int) (now()->getPreciseTimestamp(3)) - self::TTL_MS;
        $items  = array_values(array_filter(
            (array) ($blob['items'] ?? []),
            fn ($it) => is_array($it)
                && !empty($it['id'])
                && !empty($it['body'])
                && (int) ($it['createdAt'] ?? 0) >= $cutoff,
        ));
        return [
            'workspace_id'  => $ws->id,
            'items'         => $items,
            'updated_at_ms' => isset($blob['updated_at_ms']) ? (int) $blob['updated_at_ms'] : null,
            'max'           => self::MAX_ITEMS,
        ];
    }

    /** Same resolution rules as ThankTemplateController. */
    protected function resolveWorkspace(Request $request): ?Workspace
    {
        $userId   = $request->user()->id;
        $explicit = $request->integer('workspace_id') ?: null;
        if ($explicit) {
            $ws = Workspace::find($explicit);
            if (!$ws) return null;
            $isOwner  = (int) $ws->owner_user_id === (int) $userId;
            $isMember = WorkspaceMember::where('workspace_id', $ws->id)
                ->where('user_id', $userId)->exists();
            return ($isOwner || $isMember) ? $ws : null;
        }
        $memberIds = WorkspaceMember::where('user_id', $userId)->pluck('workspace_id');
        return Workspace::whereIn('id', $memberIds)
            ->orWhere('owner_user_id', $userId)
            ->orderBy('id')
            ->first();
    }
}
