<?php

namespace Tests\Feature\AI;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\CompetitorTeardown;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A teardown can actually be saved, and lands in the right workspace.
 *
 * This is the test that was missing. CompetitorTeardown uses
 * BelongsToWorkspace, whose creating() hook sets workspace_id whenever a
 * current_workspace is bound, but `competitor_teardowns` never had that
 * column -- so every "Run teardown" from a signed-in session died on
 * SQLSTATE[42703] undefined column "workspace_id". The feature had never
 * once worked in production, and nothing in the suite noticed, because
 * nothing in the suite ever created one of these rows with a workspace
 * bound. CompetitorTeardownEstimateTest priced the button; it never
 * pressed it.
 *
 * So the first case here is deliberately plain: create the model the way
 * the service does, with a workspace bound, and see that it persists.
 * Against the schema as it was, it fails on the insert.
 */
class CompetitorTeardownIsWorkspaceScopedTest extends TestCase
{
    use RefreshDatabase;

    private function plan(): Plan
    {
        $slug = 'p' . Str::lower(Str::random(8));

        return Plan::create([
            'name'          => $slug,
            'slug'          => $slug,
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'features'      => ['competitor_teardown' => true],
        ]);
    }

    /** A user with their workspace bound as the active one. */
    private function userInOwnWorkspace(): array
    {
        $user = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'plan_id'  => $this->plan()->id,
        ]);

        $ws = app(WorkspaceContext::class)->resolve($user);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $user);

        return [$user, $ws];
    }

    public function test_a_teardown_saves_and_carries_the_active_workspace(): void
    {
        [$user, $ws] = $this->userInOwnWorkspace();

        $teardown = CompetitorTeardown::create([
            'user_id'        => $user->id,
            'actor_user_id'  => $user->id,
            'competitor_url' => 'https://example.com/@someone',
            'status'         => 'processing',
        ]);

        $this->assertTrue($teardown->exists, 'the insert did not persist');
        $this->assertSame(
            (int) $ws->id,
            (int) $teardown->fresh()->workspace_id,
            'BelongsToWorkspace filled workspace_id, so the column has to exist and hold the active workspace'
        );
    }

    public function test_a_teardown_from_another_workspace_is_not_visible(): void
    {
        [$mine, $myWs] = $this->userInOwnWorkspace();

        $own = CompetitorTeardown::create([
            'user_id'        => $mine->id,
            'competitor_url' => 'https://example.com/@mine',
            'status'         => 'completed',
        ]);

        // Switch the bound workspace to someone else's and write one there.
        [$theirs, $theirWs] = $this->userInOwnWorkspace();

        $other = CompetitorTeardown::create([
            'user_id'        => $theirs->id,
            'competitor_url' => 'https://example.com/@theirs',
            'status'         => 'completed',
        ]);

        $this->assertNotSame((int) $myWs->id, (int) $theirWs->id, 'the two users must not share a workspace');

        // Still bound to the second workspace: only its row is in scope.
        $visible = CompetitorTeardown::query()->pluck('id')->all();

        $this->assertContains($other->id, $visible);
        $this->assertNotContains(
            $own->id,
            $visible,
            'the workspace global scope should hide another workspace\'s teardown'
        );
    }
}
