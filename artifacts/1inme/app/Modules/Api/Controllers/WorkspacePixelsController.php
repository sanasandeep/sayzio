<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * Workspace-level Tracking Pixel settings (Meta / TikTok / Google Ads).
 *
 * Backed by the existing `workspaces.settings` JSON column under the
 * `pixels` key — no new table needed. The browser extension reads/writes
 * these via GET/PUT /api/v1/workspace/pixels so the IDs follow the user
 * across devices and browsers (rather than living in browser.storage only).
 */
class WorkspacePixelsController extends Controller
{
    use ApiResponses;

    public function show(Request $request)
    {
        $ws = $this->resolveWorkspace($request);
        if (!$ws) return $this->forbidden('No accessible workspace');

        return $this->ok(['pixels' => $this->extract($ws)]);
    }

    public function update(Request $request)
    {
        $ws = $this->resolveWorkspace($request);
        if (!$ws) return $this->forbidden('No accessible workspace');

        $data = $request->validate([
            // Meta (Facebook) Pixel ID — 15-16 digit numeric string.
            'meta_id'         => ['nullable', 'string', 'regex:/^[0-9]{15,16}$/'],
            // TikTok Pixel ID — alphanumeric, e.g. "C7XXXXXXXXXXXXXXXX".
            'tiktok_id'       => ['nullable', 'string', 'regex:/^[A-Z0-9]{10,40}$/i'],
            // Google Ads Conversion ID, e.g. "AW-1234567890".
            'google_id'       => ['nullable', 'string', 'regex:/^AW-[0-9]{6,15}$/i'],
            // Google Ads Conversion Label — short alphanumeric token.
            'google_label'    => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9_\-]+$/'],
        ]);

        $settings = (array) ($ws->settings ?? []);
        $pixels   = (array) ($settings['pixels'] ?? []);

        foreach (['meta_id', 'tiktok_id', 'google_id', 'google_label'] as $k) {
            if (array_key_exists($k, $data)) {
                $v = $data[$k];
                if ($v === null || $v === '') {
                    unset($pixels[$k]);
                } else {
                    $pixels[$k] = is_string($v) ? trim($v) : $v;
                }
            }
        }
        $settings['pixels'] = $pixels;
        $ws->settings = $settings;
        $ws->save();

        return $this->ok(['pixels' => $this->extract($ws->fresh())]);
    }

    /** Shape settings JSON into a clean response payload. */
    protected function extract(Workspace $ws): array
    {
        $p = (array) (data_get($ws->settings, 'pixels', []) ?? []);
        $providers = [];
        if (!empty($p['meta_id']))   $providers[] = 'meta';
        if (!empty($p['tiktok_id'])) $providers[] = 'tiktok';
        if (!empty($p['google_id'])) $providers[] = 'google';
        return [
            'workspace_id' => $ws->id,
            'meta_id'      => $p['meta_id']      ?? null,
            'tiktok_id'    => $p['tiktok_id']    ?? null,
            'google_id'    => $p['google_id']    ?? null,
            'google_label' => $p['google_label'] ?? null,
            'configured'   => $providers,
            'has_any'      => !empty($providers),
        ];
    }

    /**
     * Resolve the workspace the caller intends to manage. Honours an
     * explicit `?workspace_id=` query string when the caller has access
     * to it; otherwise picks the user's first owned/membership workspace
     * (matching how WorkspaceController::index orders them).
     */
    protected function resolveWorkspace(Request $request): ?Workspace
    {
        $userId = $request->user()->id;
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
