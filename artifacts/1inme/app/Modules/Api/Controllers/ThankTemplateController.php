<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * Thank-you templates the browser extension uses for the Backlink radar's
 * "Thank composer" feature. Persisted under `workspaces.settings`
 * (`thank_templates` key) so the templates follow the creator across
 * browsers and survive an extension reinstall.
 *
 * Up to 3 templates per workspace; the extension treats the server copy
 * as authoritative on first sync, then uses last-write-wins via the
 * stored `updated_at` timestamp so offline edits reconcile cleanly on
 * the next push.
 */
class ThankTemplateController extends Controller
{
    use ApiResponses;

    protected const MAX_TEMPLATES = 3;
    protected const CHANNELS      = ['email', 'x', 'linkedin'];

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
            'templates'                 => ['present', 'array', 'max:' . self::MAX_TEMPLATES],
            'templates.*.id'            => ['required', 'string', 'max:64'],
            'templates.*.name'          => ['required', 'string', 'max:80'],
            'templates.*.channel'       => ['required', Rule::in(self::CHANNELS)],
            'templates.*.subject'       => ['nullable', 'string', 'max:200'],
            'templates.*.body'          => ['required', 'string', 'max:4000'],
            // Optional client-supplied timestamp (ms epoch). The extension
            // uses it to win over an older server copy. We accept it but
            // always re-stamp with server time on save so all clients see
            // a consistent, monotonic value.
            'updated_at_ms'             => ['nullable', 'integer', 'min:0'],
            // Optimistic-concurrency token: the server timestamp the client
            // last observed. If the stored copy has moved on since, another
            // browser saved in the meantime and we 409 instead of clobbering
            // the newer copy. Sentinel `0` means "client expected no server
            // copy yet" (first sync). Omit to bypass the check entirely
            // (used by conflict resolution after the user picked a winner).
            'expected_updated_at_ms'    => ['nullable', 'integer', 'min:0'],
        ]);

        // Optimistic concurrency check before we mutate anything.
        if ($request->has('expected_updated_at_ms')) {
            $expected = (int) $request->input('expected_updated_at_ms');
            $current  = (int) (data_get($ws->settings, 'thank_templates.updated_at_ms') ?? 0);
            if ($current !== $expected) {
                return $this->fail(
                    'Server has a newer copy of these templates.',
                    409,
                    'thank_templates_conflict',
                    $this->extract($ws),
                );
            }
        }

        // Dedupe by id, keeping the *first* occurrence (clients shouldn't
        // submit dupes but we'd rather be defensive than 422).
        $seen      = [];
        $templates = [];
        foreach ($data['templates'] as $t) {
            $id = (string) $t['id'];
            if (isset($seen[$id])) continue;
            $seen[$id] = true;
            $templates[] = [
                'id'      => $id,
                'name'    => trim((string) $t['name']),
                'channel' => (string) $t['channel'],
                'subject' => trim((string) ($t['subject'] ?? '')),
                'body'    => (string) $t['body'],
            ];
        }
        $templates = array_slice($templates, 0, self::MAX_TEMPLATES);

        $settings = (array) ($ws->settings ?? []);
        $settings['thank_templates'] = [
            'templates'     => $templates,
            'updated_at_ms' => (int) (now()->getPreciseTimestamp(3)),
        ];
        $ws->settings = $settings;
        $ws->save();

        return $this->ok($this->extract($ws->fresh()));
    }

    protected function extract(Workspace $ws): array
    {
        $blob = (array) (data_get($ws->settings, 'thank_templates', []) ?? []);
        $templates = array_values(array_filter(
            (array) ($blob['templates'] ?? []),
            fn ($t) => is_array($t) && !empty($t['id']) && !empty($t['body']),
        ));
        return [
            'workspace_id'  => $ws->id,
            'templates'     => $templates,
            'updated_at_ms' => isset($blob['updated_at_ms']) ? (int) $blob['updated_at_ms'] : null,
            'max'           => self::MAX_TEMPLATES,
        ];
    }

    /** Same resolution rules as WorkspacePixelsController. */
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
