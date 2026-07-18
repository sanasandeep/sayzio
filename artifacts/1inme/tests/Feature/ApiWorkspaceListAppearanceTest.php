<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The mobile workspace switcher needs the chosen icon + colour (and whether
 * the caller owns the workspace) so a rename/restyle done on the web shows up
 * on mobile. GET /api/v1/workspaces must surface those appearance fields.
 */
class ApiWorkspaceListAppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_appearance_icon_color_and_is_owner(): void
    {
        $user = User::factory()->create()->fresh();
        $personal = $user->ownedWorkspaces()->first();

        // A restyled team workspace the user owns.
        $team = Workspace::create([
            'owner_user_id' => $user->id,
            'name'          => 'Design Guild',
            'is_personal'   => false,
            'settings'      => ['appearance' => ['icon' => 'rocket', 'color' => '#8b5cf6']],
        ]);

        $this->withToken($user->createToken('test')->plainTextToken);
        $resp = $this->getJson('/api/v1/workspaces');
        $resp->assertStatus(200);

        $items = collect($resp->json('data.items'));

        $teamItem = $items->firstWhere('id', $team->id);
        $this->assertNotNull($teamItem);
        $this->assertSame('rocket', $teamItem['icon']);
        $this->assertSame('#8b5cf6', $teamItem['color']);
        $this->assertTrue($teamItem['is_owner']);

        // The personal workspace with no explicit appearance falls back to
        // the automatic personal icon + colour.
        $personalItem = $items->firstWhere('id', $personal->id);
        $this->assertNotNull($personalItem);
        $this->assertSame('user', $personalItem['icon']);
        $this->assertSame('#3d6bff', $personalItem['color']);
        $this->assertTrue($personalItem['is_owner']);
    }

    public function test_index_marks_member_workspaces_as_not_owned(): void
    {
        $owner  = User::factory()->create()->fresh();
        $member = User::factory()->create()->fresh();

        $team = Workspace::create([
            'owner_user_id' => $owner->id,
            'name'          => 'Shared Team',
            'is_personal'   => false,
            'settings'      => ['appearance' => ['icon' => 'briefcase', 'color' => '#10b981']],
        ]);
        $team->members()->create(['user_id' => $member->id, 'role' => 'editor']);

        $this->withToken($member->createToken('test')->plainTextToken);
        $resp = $this->getJson('/api/v1/workspaces');
        $resp->assertStatus(200);

        $teamItem = collect($resp->json('data.items'))->firstWhere('id', $team->id);
        $this->assertNotNull($teamItem);
        $this->assertSame('briefcase', $teamItem['icon']);
        $this->assertSame('#10b981', $teamItem['color']);
        $this->assertFalse($teamItem['is_owner']);
    }
}
