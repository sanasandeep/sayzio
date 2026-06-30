<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Admin\Models\AccountBadge;
use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\AiResourceShare;
use App\Modules\User\Models\Workspace;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiResourceShareService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Mobile parity for AI resource sharing (Task #2909 was web-only; this
 * mirrors it on /api/v1, Task #2923).
 *
 *   GET    /api/v1/ai/shared                          minds + personas shared WITH the acting user
 *   GET    /api/v1/ai/minds/{mind}/shares             owner: current shares + shareable audiences
 *   POST   /api/v1/ai/minds/{mind}/shares             owner: create / update a share
 *   DELETE /api/v1/ai/minds/{mind}/shares/{share}     owner: remove a share
 *   GET    /api/v1/ai/personas/{persona}/shares       owner: current shares + shareable audiences
 *   POST   /api/v1/ai/personas/{persona}/shares       owner: create / update a share
 *   DELETE /api/v1/ai/personas/{persona}/shares/{share}  owner: remove a share
 *
 * Access is resolved LIVE against the acting user's current memberships /
 * badges by {@see AiResourceShareService}, exactly like the web. AI / coin
 * costs are charged to the acting user by the runtime services, never the
 * owner — this controller only mints / removes allow-list rows and reads
 * the resolved shares, so that rule is unchanged.
 */
class AiResourceShareController extends Controller
{
    use ApiResponses;

    public function __construct(protected AiResourceShareService $shares) {}

    /** Minds + personas shared with the acting user, each with its access level. */
    public function shared(Request $request)
    {
        if (!AiEngineSettings::isEnabled()) return $this->fail('AI Engine is disabled.', 404);
        $user = $request->user();

        $minds = $this->shares->sharedMindsForUser($user)->loadCount(['sources', 'chunks'])
            ->map(fn (AiMind $m) => [
                'id'           => (int) $m->id,
                'name'         => (string) $m->name,
                'description'  => $m->description,
                'access'       => (string) $m->getAttribute('share_access'),
                'can_edit'     => $m->getAttribute('share_access') === AiResourceShare::ACCESS_EDIT,
                'sources_count'=> (int) $m->getAttribute('sources_count'),
                'chunks_count' => (int) $m->getAttribute('chunks_count'),
            ])->values()->all();

        $personas = $this->shares->sharedPersonasForUser($user)->loadCount('minds')
            ->map(fn (AiPersonaAgent $p) => [
                'id'          => (int) $p->id,
                'name'        => (string) $p->name,
                'description' => $p->description,
                'avatar_url'  => $p->avatar_url,
                'access'      => (string) $p->getAttribute('share_access'),
                'can_edit'    => $p->getAttribute('share_access') === AiResourceShare::ACCESS_EDIT,
                'minds_count' => (int) $p->getAttribute('minds_count'),
                'is_disabled' => (bool) $p->is_disabled,
            ])->values()->all();

        return $this->ok(['minds' => $minds, 'personas' => $personas]);
    }

    public function indexMind(Request $request, int $mind)
    {
        if (!AiEngineSettings::isEnabled()) return $this->fail('AI Engine is disabled.', 404);
        $model = AiMind::find($mind);
        if (!$model) return $this->notFound('Mind not found');
        if (($err = $this->ownsMind($model, $request->user())) !== null) return $err;

        return $this->ok($this->manageData(
            $request->user(),
            AiResourceShare::RESOURCE_MIND,
            (int) $model->id,
        ));
    }

    public function storeMind(Request $request, int $mind)
    {
        if (!AiEngineSettings::isEnabled()) return $this->fail('AI Engine is disabled.', 404);
        $model = AiMind::find($mind);
        if (!$model) return $this->notFound('Mind not found');
        if (($err = $this->ownsMind($model, $request->user())) !== null) return $err;

        return $this->create($request, AiResourceShare::RESOURCE_MIND, (int) $model->id);
    }

    public function destroyMind(Request $request, int $mind, int $share)
    {
        if (!AiEngineSettings::isEnabled()) return $this->fail('AI Engine is disabled.', 404);
        $model = AiMind::find($mind);
        if (!$model) return $this->notFound('Mind not found');
        if (($err = $this->ownsMind($model, $request->user())) !== null) return $err;

        return $this->remove($share, AiResourceShare::RESOURCE_MIND, (int) $model->id);
    }

    public function indexPersona(Request $request, int $persona)
    {
        if (!AiEngineSettings::isEnabled()) return $this->fail('AI Engine is disabled.', 404);
        $model = AiPersonaAgent::find($persona);
        if (!$model) return $this->notFound('Persona not found');
        if (($err = $this->ownsPersona($model, $request->user())) !== null) return $err;

        return $this->ok($this->manageData(
            $request->user(),
            AiResourceShare::RESOURCE_PERSONA,
            (int) $model->id,
        ));
    }

    public function storePersona(Request $request, int $persona)
    {
        if (!AiEngineSettings::isEnabled()) return $this->fail('AI Engine is disabled.', 404);
        $model = AiPersonaAgent::find($persona);
        if (!$model) return $this->notFound('Persona not found');
        if (($err = $this->ownsPersona($model, $request->user())) !== null) return $err;

        return $this->create($request, AiResourceShare::RESOURCE_PERSONA, (int) $model->id);
    }

    public function destroyPersona(Request $request, int $persona, int $share)
    {
        if (!AiEngineSettings::isEnabled()) return $this->fail('AI Engine is disabled.', 404);
        $model = AiPersonaAgent::find($persona);
        if (!$model) return $this->notFound('Persona not found');
        if (($err = $this->ownsPersona($model, $request->user())) !== null) return $err;

        return $this->remove($share, AiResourceShare::RESOURCE_PERSONA, (int) $model->id);
    }

    /**
     * Owner manage payload: the audiences the owner may share into plus
     * the resource's existing shares (mirrors the web edit screen).
     */
    protected function manageData($user, string $resourceType, int $resourceId): array
    {
        return [
            'workspaces' => $this->shares->shareableWorkspacesFor($user)
                ->map(fn (Workspace $w) => ['id' => (int) $w->id, 'name' => (string) $w->name])
                ->values()->all(),
            'badges' => $this->shares->shareableBadgesFor($user)
                ->map(fn (AccountBadge $b) => ['id' => (int) $b->id, 'name' => (string) $b->name])
                ->values()->all(),
            'shares' => $this->shares->sharesForResource($resourceType, $resourceId)
                ->map(fn (AiResourceShare $s) => [
                    'id'            => (int) $s->id,
                    'audience_type' => (string) $s->audience_type,
                    'audience_id'   => (int) $s->audience_id,
                    'audience_label'=> (string) $s->getAttribute('audience_label'),
                    'access'        => (string) $s->access,
                ])->values()->all(),
        ];
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
            return $this->fail('Pick a valid team or badge group to share with.', 422, 'invalid_audience');
        }

        try {
            $share = $this->shares->share(
                $request->user(),
                $resourceType,
                $resourceId,
                $audienceType,
                (int) $audienceId,
                $data['access'],
            );
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422, 'invalid_audience');
        }

        return $this->created([
            'share' => [
                'id'            => (int) $share->id,
                'audience_type' => (string) $share->audience_type,
                'audience_id'   => (int) $share->audience_id,
                'access'        => (string) $share->access,
            ],
        ]);
    }

    protected function remove(int $shareId, string $resourceType, int $resourceId)
    {
        $share = AiResourceShare::find($shareId);
        if (!$share || $share->resource_type !== $resourceType || (int) $share->resource_id !== $resourceId) {
            return $this->notFound('Share not found');
        }
        $share->delete();
        return $this->noContent();
    }

    /** @return \Illuminate\Http\JsonResponse|null null when the user owns the mind. */
    protected function ownsMind(AiMind $mind, $user)
    {
        if ($mind->isPlatform() || (int) $mind->user_id !== (int) $user->id) {
            return $this->forbidden('You can only manage sharing for your own AI Mind.');
        }
        return null;
    }

    /** @return \Illuminate\Http\JsonResponse|null null when the user owns the persona. */
    protected function ownsPersona(AiPersonaAgent $persona, $user)
    {
        if ((int) $persona->user_id !== (int) $user->id) {
            return $this->forbidden('You can only manage sharing for your own Persona.');
        }
        return null;
    }
}
