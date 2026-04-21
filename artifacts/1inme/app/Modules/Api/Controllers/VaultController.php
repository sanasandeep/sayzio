<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\VaultClient;
use App\Modules\User\Models\VaultCredential;
use App\Modules\User\Models\WorkspaceMember;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Vault: read-only API for the mobile app. Vault items live inside a
 * workspace and have row-level visibility (private / workspace).
 * Mutations stay on the web for now where the encryption-key flow is
 * fully wired up.
 */
class VaultController extends Controller
{
    use ApiResponses;

    public function clients(Request $request)
    {
        $userId = $request->user()->id;
        $workspaceIds = WorkspaceMember::where('user_id', $userId)->pluck('workspace_id');

        $items = VaultClient::whereIn('workspace_id', $workspaceIds)
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->filter(fn ($c) => $this->canSee($c, $userId));

        return $this->ok(['items' => $items->values()->map(fn ($c) => [
            'id'            => $c->id,
            'workspace_id'  => $c->workspace_id,
            'name'          => $c->name,
            'company'       => $c->company,
            'website'       => $c->website,
            'primary_email' => $c->primary_email,
            'primary_phone' => $c->primary_phone,
            'visibility'    => $c->visibility,
            'tags'          => $c->tags ?? [],
        ])->all()]);
    }

    public function credentials(Request $request)
    {
        $userId = $request->user()->id;
        $workspaceIds = WorkspaceMember::where('user_id', $userId)->pluck('workspace_id');

        $items = VaultCredential::whereIn('workspace_id', $workspaceIds)
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->filter(fn ($c) => $this->canSee($c, $userId));

        // NB: never return decrypted secrets here — the mobile client
        // requests them on demand through the existing web flow.
        return $this->ok(['items' => $items->values()->map(fn ($c) => [
            'id'            => $c->id,
            'workspace_id'  => $c->workspace_id,
            'label'         => $c->label,
            'url'           => $c->url,
            'username'      => $c->username,
            'visibility'    => $c->visibility,
            'tags'          => $c->tags ?? [],
        ])->all()]);
    }

    private function canSee($row, int $userId): bool
    {
        $vis = $row->visibility ?? 'workspace';
        if ($vis === 'workspace') return true;
        return ((int) $row->created_by_user_id) === $userId;
    }
}
