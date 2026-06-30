<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AccountBadge;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\AiResourceShare;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiResourceShareService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\AiResourceShareScenarioMatrix;
use Tests\TestCase;

/**
 * Owner/audience guard for AI resource sharing on the WEB surface
 * (web AiResourceShareController).
 *
 * The shared owner/audience scenario matrix (non-owner create, EDIT-editor
 * reshare, foreign team, badge-not-held, non-owner delete, suspended member
 * loses access, platform mind not manageable) lives in
 * {@see AiResourceShareScenarioMatrix} and is asserted IDENTICALLY here and
 * on the API surface ({@see MobileAiResourceShareApiTest}) so the two can't
 * drift apart (Task #2935). This class only supplies the web transport: a
 * form POST/DELETE that 403s on an ownership failure and redirects back with
 * a flash `error` on an audience failure (never a 500).
 */
class AiResourceShareOwnershipTest extends TestCase
{
    use RefreshDatabase;
    use AiResourceShareScenarioMatrix;

    private AiResourceShareService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(AiResourceShareService::class);
        // The mind/persona web controllers 404 unless the engine is on.
        AiEngineSettings::setEnabled(true);
    }

    // ===================================================================
    // Shared-matrix transport hooks (web surface)
    // ===================================================================

    protected function shareService(): AiResourceShareService
    {
        return $this->svc;
    }

    protected function newUser(): User
    {
        $u = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
        $u->ensureDefaultWorkspace();
        return $u->fresh();
    }

    protected function newMind(User $owner): AiMind
    {
        return AiMind::create(['user_id' => $owner->id, 'name' => 'Mind ' . Str::random(4)]);
    }

    protected function newPersona(User $owner): AiPersonaAgent
    {
        return AiPersonaAgent::create([
            'user_id'       => $owner->id,
            'name'          => 'Persona ' . Str::random(4),
            'system_prompt' => 'You are helpful.',
            'model'         => 'gpt-4o-mini',
        ]);
    }

    protected function newTeam(User $owner, ?User $member = null, ?string $suspendedAt = null): Workspace
    {
        $team = Workspace::create([
            'owner_user_id' => $owner->id,
            'name'          => 'Team ' . Str::random(4),
            'slug'          => 'team-' . Str::random(6),
            'is_personal'   => false,
        ]);
        if ($member) {
            WorkspaceMember::create([
                'workspace_id' => $team->id,
                'user_id'      => $member->id,
                'role'         => 'editor',
                'suspended_at' => $suspendedAt,
            ]);
        }
        return $team;
    }

    protected function newBadge(): AccountBadge
    {
        return AccountBadge::create(['name' => 'b' . Str::random(5), 'color' => '#3b82f6']);
    }

    /** Map a scenario kind to its web share route base. */
    private function routeBase(string $kind, Model $resource): string
    {
        return $kind === 'mind'
            ? "/user/minds/{$resource->id}"
            : "/user/ai-personas/{$resource->id}";
    }

    protected function shareForbidden(User $actor, string $kind, Model $resource, string $audience): void
    {
        $base = $this->routeBase($kind, $resource);
        $this->actingAs($actor)
            ->from($base)
            ->post("{$base}/shares", ['audience' => $audience, 'access' => 'use'])
            ->assertForbidden();
    }

    protected function shareAudienceRejected(User $actor, string $kind, Model $resource, string $audience): void
    {
        $base = $this->routeBase($kind, $resource);
        $this->actingAs($actor)
            ->from($base)
            ->post("{$base}/shares", ['audience' => $audience, 'access' => 'use'])
            ->assertRedirect($base)
            ->assertSessionHas('error');
    }

    protected function deleteForbidden(User $actor, string $kind, Model $resource, AiResourceShare $share): void
    {
        $base = $this->routeBase($kind, $resource);
        $this->actingAs($actor)
            ->from($base)
            ->delete("{$base}/shares/{$share->id}")
            ->assertForbidden();
    }

    protected function deleteSucceeds(User $actor, string $kind, Model $resource, AiResourceShare $share): void
    {
        $base = $this->routeBase($kind, $resource);
        $this->actingAs($actor)
            ->from($base)
            ->delete("{$base}/shares/{$share->id}")
            ->assertRedirect();
    }
}
