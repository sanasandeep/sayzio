<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiResourceShareService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression guard: the AI Agents page (/user/ai-personas) and the
 * shared-AI API endpoint (GET /api/v1/ai/shared) once 500'd with
 * "Collection::loadCount does not exist" for any user with no shared
 * personas, because AiResourceShareService::sharedResources() returned a
 * base Support collection on its two empty paths (no audiences at all,
 * and audiences but no shares). Both callers chain ->loadCount(), which
 * only exists on the Eloquent collection.
 *
 * Auth on the API path uses a real Bearer token — Sanctum::actingAs
 * breaks the TouchSessionToken middleware.
 */
class AiSharedResourcesEmptyStateTest extends TestCase
{
    use RefreshDatabase;

    private AiResourceShareService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(AiResourceShareService::class);
        AiEngineSettings::setEnabled(true);
    }

    private function makeUser(): User
    {
        return User::factory()->create()->fresh();
    }

    /** A user with NO workspaces, memberships or badges (first empty path). */
    private function userWithoutAudiences(): User
    {
        $user = $this->makeUser();
        WorkspaceMember::where('user_id', $user->id)->delete();
        Workspace::where('owner_user_id', $user->id)->delete();
        return $user->fresh();
    }

    /** A user WITH a team audience but zero shares reaching them (second empty path). */
    private function userWithAudienceButNoShares(): User
    {
        $user = $this->makeUser();
        $owner = $this->makeUser();
        $team = Workspace::create([
            'owner_user_id' => $owner->id,
            'name'          => 'Team ' . Str::random(4),
            'slug'          => 'team-' . Str::random(6),
            'is_personal'   => false,
        ]);
        WorkspaceMember::create([
            'workspace_id' => $team->id,
            'user_id'      => $user->id,
            'role'         => 'editor',
        ]);
        return $user->fresh();
    }

    public function test_ai_personas_page_renders_for_user_without_share_audiences(): void
    {
        $user = $this->userWithoutAudiences();

        $this->actingAs($user)
            ->get('/user/ai-personas')
            ->assertOk();
    }

    public function test_ai_personas_page_renders_for_user_with_audiences_but_no_shares(): void
    {
        $user = $this->userWithAudienceButNoShares();

        $this->actingAs($user)
            ->get('/user/ai-personas')
            ->assertOk();
    }

    public function test_api_shared_endpoint_returns_empty_lists_for_user_without_share_audiences(): void
    {
        $user = $this->userWithoutAudiences();

        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/ai/shared')
            ->assertOk()
            ->assertJsonPath('data.minds', [])
            ->assertJsonPath('data.personas', []);
    }

    public function test_api_shared_endpoint_returns_empty_lists_for_user_with_audiences_but_no_shares(): void
    {
        $user = $this->userWithAudienceButNoShares();

        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/ai/shared')
            ->assertOk()
            ->assertJsonPath('data.minds', [])
            ->assertJsonPath('data.personas', []);
    }

    public function test_shared_resource_helpers_return_eloquent_collections_on_both_empty_paths(): void
    {
        $noAudiences = $this->userWithoutAudiences();
        $noShares = $this->userWithAudienceButNoShares();

        foreach ([$noAudiences, $noShares] as $user) {
            $personas = $this->svc->sharedPersonasForUser($user);
            $minds = $this->svc->sharedMindsForUser($user);

            $this->assertInstanceOf(EloquentCollection::class, $personas);
            $this->assertInstanceOf(EloquentCollection::class, $minds);
            $this->assertTrue($personas->isEmpty());
            $this->assertTrue($minds->isEmpty());

            // The exact chain both controllers use must not throw.
            $personas->loadCount('minds');
            $minds->loadCount(['sources', 'chunks']);
        }
    }
}
