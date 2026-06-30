<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\AiResourceShare;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiResourceShareService;
use Illuminate\Http\Request;

/**
 * Owner-only management of AI resource shares (Task #2909). Lets the
 * owner of a Mind / Persona grant a team workspace or account-badge
 * group USE / EDIT access, and revoke it. Recipients reach the shared
 * resource through the existing Mind / Persona controllers — this
 * controller only mints and removes the allow-list rows.
 */
class AiResourceShareController extends Controller
{
    public function __construct(protected AiResourceShareService $shares) {}

    public function storeMind(Request $request, AiMind $mind)
    {
        $this->ensureEnabled();
        $this->ownsMind($mind, $request->user());
        return $this->create($request, AiResourceShare::RESOURCE_MIND, (int) $mind->id);
    }

    public function destroyMind(Request $request, AiMind $mind, AiResourceShare $share)
    {
        $this->ensureEnabled();
        $this->ownsMind($mind, $request->user());
        $this->remove($share, AiResourceShare::RESOURCE_MIND, (int) $mind->id);
        return back()->with('status', 'Share removed.');
    }

    public function storePersona(Request $request, AiPersonaAgent $persona)
    {
        $this->ensureEnabled();
        $this->ownsPersona($persona, $request->user());
        return $this->create($request, AiResourceShare::RESOURCE_PERSONA, (int) $persona->id);
    }

    public function destroyPersona(Request $request, AiPersonaAgent $persona, AiResourceShare $share)
    {
        $this->ensureEnabled();
        $this->ownsPersona($persona, $request->user());
        $this->remove($share, AiResourceShare::RESOURCE_PERSONA, (int) $persona->id);
        return back()->with('status', 'Share removed.');
    }

    protected function create(Request $request, string $resourceType, int $resourceId)
    {
        $data = $request->validate([
            // Combined "type:id", e.g. "workspace:5" or "badge:3".
            'audience' => 'required|string|max:40',
            'access'   => 'required|in:use,edit',
        ]);

        [$audienceType, $audienceId] = array_pad(explode(':', $data['audience'], 2), 2, null);
        if (!in_array($audienceType, AiResourceShare::AUDIENCE_TYPES, true) || !ctype_digit((string) $audienceId)) {
            return back()->with('error', 'Pick a valid team or badge group to share with.');
        }

        try {
            $this->shares->share(
                $request->user(),
                $resourceType,
                $resourceId,
                $audienceType,
                (int) $audienceId,
                $data['access'],
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Sharing updated.');
    }

    protected function remove(AiResourceShare $share, string $resourceType, int $resourceId): void
    {
        if ($share->resource_type !== $resourceType || (int) $share->resource_id !== $resourceId) {
            abort(404);
        }
        $share->delete();
    }

    protected function ownsMind(AiMind $mind, $user): void
    {
        if ($mind->isPlatform() || (int) $mind->user_id !== (int) $user->id) {
            abort(403);
        }
    }

    protected function ownsPersona(AiPersonaAgent $persona, $user): void
    {
        if ((int) $persona->user_id !== (int) $user->id) {
            abort(403);
        }
    }

    protected function ensureEnabled(): void
    {
        if (!AiEngineSettings::isEnabled()) {
            abort(404);
        }
    }
}
