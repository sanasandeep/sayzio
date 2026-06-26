<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the AI builder discovery nudge on the Create Link page
 * (Step 1, LinkController::create + user/links/create.blade.php).
 *
 * The card renders in three mutually exclusive states:
 *   1. Engine ON  → a working AI builder submit form (POST user.links.store
 *      with start_mode=ai) so anyone can launch the builder.
 *   2. Engine OFF + admin who can manage settings → a teaser linking to
 *      admin.ai-engine.edit with an "Enable AI" CTA.
 *   3. Engine OFF + everyone else → a teaser linking to user.upgrade with
 *      an "Upgrade" CTA.
 *
 * A future change could silently regress which CTA each viewer sees, so we
 * lock the three states in here. We key assertions on the unique teaser
 * hrefs and phrases (the surrounding layout has its own generic "Upgrade"
 * sidebar link, so the bare word is not a reliable signal).
 */
class CreateLinkAiBuilderNudgeTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'User ' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
    }

    /**
     * Give the user a matching admin-guard account (bridged by email) with
     * the super-admin role, which grants every permission — including the
     * settings.manage the create() controller checks for the "Enable AI" CTA.
     */
    private function attachSettingsAdmin(User $user): void
    {
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );

        Admin::create([
            'name'     => 'Admin ' . Str::random(4),
            'email'    => $user->email,
            'password' => Hash::make('x'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    public function test_engine_on_renders_the_working_ai_builder_form(): void
    {
        AiEngineSettings::setEnabled(true);

        $resp = $this->actingAs($this->makeUser())
            ->get(route('user.links.create'))
            ->assertOk();

        // The live builder is a real submit form that launches the page in
        // AI start mode — not a teaser link.
        $resp->assertSee('action="' . route('user.links.store') . '"', false);
        $resp->assertSee('name="start_mode" value="ai"', false);
        $resp->assertSee('Build with AI');

        // Neither teaser variant should render in this state.
        $resp->assertDontSee('Enable AI');
        $resp->assertDontSee('Available on a higher plan.');
        $resp->assertDontSee(route('admin.ai-engine.edit'), false);
        $resp->assertDontSee('href="' . route('user.upgrade') . '"', false);
    }

    public function test_engine_off_admin_sees_enable_ai_teaser(): void
    {
        AiEngineSettings::setEnabled(false);

        $user = $this->makeUser();
        $this->attachSettingsAdmin($user);

        $resp = $this->actingAs($user)
            ->get(route('user.links.create'))
            ->assertOk();

        $resp->assertSee('href="' . route('admin.ai-engine.edit') . '"', false);
        $resp->assertSee('Enable AI');
        $resp->assertSee('Turn on the AI Engine to make this available.');

        // It is a teaser, not the working submit form, and not the upgrade CTA.
        $resp->assertDontSee('name="start_mode" value="ai"', false);
        $resp->assertDontSee('Available on a higher plan.');
        $resp->assertDontSee('href="' . route('user.upgrade') . '"', false);
    }

    public function test_engine_off_regular_user_sees_upgrade_teaser(): void
    {
        AiEngineSettings::setEnabled(false);

        $resp = $this->actingAs($this->makeUser())
            ->get(route('user.links.create'))
            ->assertOk();

        $resp->assertSee('href="' . route('user.upgrade') . '"', false);
        $resp->assertSee('Available on a higher plan.');

        // No admin "Enable AI" path and no working builder form for a plain user.
        $resp->assertDontSee('Enable AI');
        $resp->assertDontSee('Turn on the AI Engine to make this available.');
        $resp->assertDontSee('name="start_mode" value="ai"', false);
        $resp->assertDontSee(route('admin.ai-engine.edit'), false);
    }
}
