<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkWizardDraft;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\LinkTypeCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for carrying a chosen "Create Link" goal into the guided wizard.
 *
 * The create page's intent prompt offers a one-tap path into the wizard for
 * goals the wizard can build, pre-seeding the matching persona group via a
 * `?group=<persona group>` query param on the wizard index. These tests lock
 * in that the prefill seeds a fresh draft, never clobbers an in-progress one,
 * and that the goal→group map only references valid persona groups.
 */
class BiolinkWizardGoalPrefillTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $user = User::create([
            'name'     => 'Wiz ' . Str::random(4),
            'email'    => 'wiz-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
        $user->ensureDefaultWorkspace();
        return $user->fresh();
    }

    private function activeWorkspaceId(User $user): ?int
    {
        return app(WorkspaceContext::class)->resolve($user)?->id;
    }

    /**
     * Every goal entry must reference a real persona group (or null), and any
     * optional persona must belong to that group — otherwise the prefill would
     * silently fall back to the group-only step.
     */
    public function test_wizard_groups_map_to_valid_persona_groups(): void
    {
        $valid = \App\Modules\User\Services\PersonaCatalog::groupKeys();

        foreach (LinkTypeCategories::wizardGroups() as $type => $cfg) {
            $group   = $cfg['group'] ?? null;
            $persona = $cfg['persona'] ?? null;

            if ($group === null) {
                $this->assertNull($persona,
                    "goal '{$type}' has a persona but no group to anchor it");
                continue; // generic wizard entry — no group to validate
            }

            $this->assertContains($group, $valid,
                "goal '{$type}' maps to unknown persona group '{$group}'");

            if ($persona !== null) {
                $this->assertSame($group,
                    \App\Modules\User\Services\PersonaCatalog::groupOf($persona),
                    "goal '{$type}' persona '{$persona}' is not in group '{$group}'");
            }
        }
    }

    /** A `?group=&persona=` prefill seeds the persona and jumps to step 2. */
    public function test_persona_prefill_seeds_starting_design_step(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get('/user/links/wizard?group=Food&persona=chef')
            ->assertOk();

        $draft = BiolinkWizardDraft::where('actor_user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($draft, 'a draft should be seeded from the prefill');
        $this->assertSame('Food', $draft->persona_group);
        $this->assertSame('chef', $draft->persona);
        // Resolved legacy combo so the question set + recipe engine work.
        $this->assertSame('restaurant', $draft->category);
        $this->assertSame('restaurant', $draft->page_type);
        // Step 2 = the starting-design step.
        $this->assertSame(2, (int) $draft->step);
    }

    /** A persona that doesn't belong to the group is ignored (group-only). */
    public function test_foreign_persona_prefill_falls_back_to_group_step(): void
    {
        $user = $this->makeUser();

        // `creator` is a Creators persona, not Food — it must be rejected.
        $this->actingAs($user)
            ->get('/user/links/wizard?group=Food&persona=creator')
            ->assertOk();

        $draft = BiolinkWizardDraft::where('actor_user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($draft);
        $this->assertSame('Food', $draft->persona_group);
        $this->assertNull($draft->persona, 'a foreign persona must not be seeded');
        $this->assertSame(1, (int) $draft->step, 'should stop on the persona step');
    }

    /** Every mapped goal must be a real create-screen link type. */
    public function test_wizard_goals_are_real_link_types(): void
    {
        $types = LinkTypeCategories::types();

        foreach (array_keys(LinkTypeCategories::wizardGroups()) as $goal) {
            $this->assertArrayHasKey($goal, $types,
                "wizard goal '{$goal}' is not a catalog link type");
        }
    }

    /** The guided-wizard goal coverage stays in lockstep with its persona groups. */
    public function test_wizard_groups_cover_expected_goals(): void
    {
        $this->assertSame([
            'biolink'         => ['group' => null,       'persona' => null],
            'restaurant_menu' => ['group' => 'Food',     'persona' => 'chef'],
            'paid_page'       => ['group' => 'Creators', 'persona' => null],
            'reviews'         => ['group' => 'Business', 'persona' => null],
            'resume'          => ['group' => 'Services', 'persona' => null],
        ], LinkTypeCategories::wizardGroups());
    }

    /** A `?group=` prefill seeds a fresh draft on the persona step. */
    public function test_group_prefill_seeds_a_fresh_draft(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get('/user/links/wizard?group=Food')
            ->assertOk();

        $draft = BiolinkWizardDraft::where('actor_user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($draft, 'a draft should be seeded from the prefill');
        $this->assertSame('Food', $draft->persona_group);
        $this->assertSame(1, (int) $draft->step);
        // The persona itself is still the user's choice on step 1.
        $this->assertNull($draft->persona);
    }

    /** An in-progress draft is never clobbered by a prefill param. */
    public function test_group_prefill_does_not_clobber_in_progress_draft(): void
    {
        $user = $this->makeUser();

        $draft = BiolinkWizardDraft::create([
            'user_id'       => $user->id,
            'actor_user_id' => $user->id,
            'workspace_id'  => $this->activeWorkspaceId($user),
            'persona'       => 'creator',
            'persona_group' => 'Creators',
            'category'      => 'creator',
            'page_type'     => 'influencer',
            'industry'      => null,
            'step'          => 3,
            'answers'       => ['display_name' => 'Existing'],
        ]);

        $this->actingAs($user)
            ->get('/user/links/wizard?group=Food')
            ->assertOk();

        $draft->refresh();
        $this->assertSame('Creators', $draft->persona_group, 'existing group must survive');
        $this->assertSame('creator', $draft->persona, 'existing persona must survive');
        $this->assertSame(3, (int) $draft->step, 'existing step must survive');
    }

    /** An invalid group param is ignored and seeds nothing. */
    public function test_invalid_group_prefill_is_ignored(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get('/user/links/wizard?group=NotARealGroup')
            ->assertOk();

        $draft = BiolinkWizardDraft::where('actor_user_id', $user->id)->latest('id')->first();
        // Either no draft was created, or a created one carries no seeded group.
        if ($draft !== null) {
            $this->assertNull($draft->persona_group);
        }
        $this->assertTrue(true);
    }
}
