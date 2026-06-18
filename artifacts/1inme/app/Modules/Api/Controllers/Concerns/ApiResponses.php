<?php

namespace App\Modules\Api\Controllers\Concerns;

use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;

trait ApiResponses
{
    /**
     * Resolve the signed-in user's active workspace id for a Sanctum API
     * write, WITHOUT binding `current_workspace` in the container.
     *
     * The Sanctum API path never runs the SetActiveWorkspace middleware, so
     * any model using the BelongsToWorkspace trait would otherwise be created
     * with workspace_id = null and be hidden from the workspace-scoped web
     * lists — so things created on mobile appear "missing" on the website.
     *
     * We deliberately do NOT bind `current_workspace`: binding also activates
     * the read-side BelongsToWorkspace global scope for the rest of the
     * request, which can change query semantics (e.g. global slug/alias
     * uniqueness). The stateless Sanctum request has no session, so resolving
     * "first accessible → lazily-created personal workspace" matches
     * WorkspaceContext's own fallback.
     */
    protected function activeWorkspaceId(?User $user): ?int
    {
        if (!$user) return null;
        $ws = $user->accessibleWorkspaces()->first() ?? $user->ensureDefaultWorkspace();
        return $ws?->id;
    }

    /**
     * Resolve a workspace id for a write, honouring a caller-supplied
     * workspace id only when the user can actually access it (so a request
     * can't assign rows into someone else's workspace), otherwise falling
     * back to the user's active workspace.
     *
     * Note: `workspace_id` is intentionally NOT mass-assignable on the
     * BelongsToWorkspace models, so the resolved id must be applied via a
     * direct property assignment (`$model->workspace_id = ...`) before save,
     * not through `create([...])` / `fill([...])`.
     */
    protected function resolveWorkspaceId(?User $user, int|string|null $requested = null): ?int
    {
        if (!$user) return null;
        if ($requested !== null && $requested !== '') {
            $ws = $user->accessibleWorkspaces()->firstWhere('id', (int) $requested);
            if ($ws) return $ws->id;
        }
        return $this->activeWorkspaceId($user);
    }

    protected function ok(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }

    protected function created(mixed $data = null): JsonResponse
    {
        return $this->ok($data, 201);
    }

    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    protected function fail(string $message, int $status = 400, ?string $code = null, mixed $details = null): JsonResponse
    {
        $error = ['message' => $message];
        if ($code !== null)    $error['code']    = $code;
        if ($details !== null) $error['details'] = $details;
        return response()->json(['error' => $error], $status);
    }

    /**
     * Plan-gated rejection that also tells the client *which* plan unlocks
     * the blocked feature. Computes the cheapest active plan that raises /
     * unlocks $feature via {@see User::planThatUnlocks()} and stamps it into
     * the error `details` as `recommended_plan` (slug) + `recommended_plan_name`,
     * alongside the raw `feature` key. The mobile app reads these to pre-select
     * and scroll to the recommended plan on its /upgrade screen instead of
     * showing a generic list. Falls back to a plain {@see fail()} (no hint)
     * when no qualifying plan exists, so older clients keep working.
     *
     * @param int|null $current Optional current usage count for numeric caps,
     *                          forwarded to planThatUnlocks so the suggested
     *                          plan actually raises the limit the user blew past.
     */
    protected function planGate(
        string $message,
        string $feature,
        ?User $user,
        int $status = 402,
        string $code = 'plan_upgrade_required',
        int|null $current = null
    ): JsonResponse {
        $details = ['feature' => $feature];
        if ($user && method_exists($user, 'planThatUnlocks')) {
            $target = $user->planThatUnlocks($feature, $current);
            if ($target) {
                $details['recommended_plan'] = $target->slug;
                $details['recommended_plan_name'] = $target->name;
            }
        }
        return $this->fail($message, $status, $code, $details);
    }

    protected function notFound(string $message = 'Not found'): JsonResponse
    {
        return $this->fail($message, 404, 'not_found');
    }

    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->fail($message, 403, 'forbidden');
    }

    protected function unauthorized(string $message = 'Unauthorized', ?string $code = null): JsonResponse
    {
        return $this->fail($message, 401, $code ?? 'unauthorized');
    }
}
