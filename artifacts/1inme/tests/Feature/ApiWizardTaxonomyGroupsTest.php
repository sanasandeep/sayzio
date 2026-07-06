<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Support\LinkTypeCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mobile parity for the web "Create Link" one-tap guided path.
 *
 * The mobile Create screen needs the same goal→persona-group map the web page
 * reads from LinkTypeCategories::wizardGroups() so it can offer the guided
 * wizard for wizard-supported goals (pre-seeding the matching persona group)
 * and keep the manual flow for the rest. The map is surfaced on the wizard
 * taxonomy endpoint as `wizard_groups`.
 *
 * Authenticated with a REAL Sanctum bearer token (NOT Sanctum::actingAs, which
 * 500s under the TouchSessionToken middleware).
 */
class ApiWizardTaxonomyGroupsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create()->fresh();
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    /** The taxonomy endpoint exposes the goal→group map verbatim. */
    public function test_taxonomy_exposes_wizard_groups_map(): void
    {
        $user = $this->makeUser();

        $res = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($user)])
            ->getJson('/api/v1/links/wizard/taxonomy')
            ->assertOk();

        $res->assertJsonPath('data.wizard_groups', LinkTypeCategories::wizardGroups());
    }
}
