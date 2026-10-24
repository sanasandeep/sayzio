<?php

namespace Tests\Feature;

use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\ProjectItem;
use App\Modules\User\Models\TaskBoard;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FolderExplorerTest extends TestCase
{
    use RefreshDatabase;

    private function desk(): array
    {
        $this->withoutVite();
        $user = User::factory()->create(['onboarded_at' => now()->subMonth()]);
        $ws = $user->ownedWorkspaces()->first();
        $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $user);
        $a = $user->projects()->create(['name' => 'Marketing', 'color' => '#8b5cf6']);
        $b = $user->projects()->create(['name' => 'Launch', 'color' => '#16a34a']);
        $link = $user->links()->create(['type' => 'url', 'alias' => 'explorer-link', 'title' => 'Launch website', 'project_id' => $a->id, 'is_active' => true]);

        return [$user, $ws, $a, $b, $link];
    }

    public function test_contents_filters_types_and_sorts_before_pagination(): void
    {
        [$user, $ws, $a, $b, $link] = $this->desk();
        $user->links()->create(['type' => 'biolink', 'alias' => 'explorer-bio', 'title' => 'Alpha profile', 'project_id' => $a->id, 'is_active' => true]);
        $base = '/user/folders/contents?folder='.$a->id;
        $this->getJson($base)->assertOk()->assertJsonPath('total', 2);
        $this->getJson($base.'&kind=link&link_type=url')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('items.0.key', 'link:'.$link->id);
        $this->getJson($base.'&kind=file')->assertOk()->assertJsonPath('total', 0);
        $this->getJson($base.'&sort=name_desc')->assertOk()->assertJsonPath('items.0.key', 'link:'.$link->id);
        $this->getJson($base.'&q=Alpha&link_type=url')->assertOk()->assertJsonPath('total', 0);
        $this->getJson($base.'&kind=invalid')->assertUnprocessable();
        $this->get('/user/folders')->assertOk()->assertSee('All item types')->assertSee('All link types')->assertSee('Clear filters');
    }

    public function test_all_folders_route_renders_and_native_links_move_in_out_and_back(): void
    {
        [$user,$ws,$a,$b,$link] = $this->desk();
        $page = $this->get('/user/folders')->assertOk()->assertSee('All folders')->assertSee('New folder')->assertSee('All folders</span>', false);
        $this->get('/user/projects')->assertOk();
        $this->getJson('/user/folders/contents?folder='.$a->id)->assertOk()->assertJsonPath('items.0.key', 'link:'.$link->id);
        $payload = ['items' => ['link:'.$link->id], 'source' => $a->id, 'destination' => $b->id];
        $this->postJson('/user/folders/items/move', $payload)->assertOk();
        $this->assertSame($b->id, $link->fresh()->project_id);
        $this->postJson('/user/folders/items/move', array_replace($payload, ['source' => $b->id, 'destination' => null]))->assertOk();
        $this->assertNull($link->fresh()->project_id);
        $this->assertDatabaseMissing('project_items', ['kind' => 'link', 'resource_id' => $link->id]);
        $this->postJson('/user/folders/items/move', array_replace($payload, ['source' => null, 'destination' => $a->id]))->assertOk();
        $this->assertSame($a->id, $link->fresh()->project_id);
        $this->postJson('/user/projects', ['name' => 'Created at desk', 'color' => '#d946ef'])->assertOk();
        if (getenv('FOLDER_EXPLORER_UI')) {
            file_put_contents('/tmp/sayzio-folder-explorer.html', $page->getContent());
            file_put_contents('/tmp/sayzio-folder-contents.json', $this->getJson('/user/folders/contents?folder='.$a->id)->json() ? json_encode($this->getJson('/user/folders/contents?folder='.$a->id)->json()) : '{}');
        }
    }

    public function test_reference_items_move_and_stale_or_foreign_moves_are_atomic(): void
    {
        [$user,$ws,$a,$b,$link] = $this->desk();
        $mind = AiMind::create(['user_id' => $user->id, 'name' => 'Launch knowledge', 'is_disabled' => false]);
        ProjectItem::create(['workspace_id' => $ws->id, 'project_id' => $a->id, 'user_id' => $user->id, 'kind' => 'mind', 'resource_id' => $mind->id]);
        $this->postJson('/user/folders/items/move', ['items' => ['mind:'.$mind->id], 'source' => $a->id, 'destination' => $b->id])->assertOk();
        $this->assertDatabaseHas('project_items', ['kind' => 'mind', 'resource_id' => $mind->id, 'project_id' => $b->id]);
        $this->postJson('/user/folders/items/move', ['items' => ['link:'.$link->id, 'mind:'.$mind->id], 'source' => $a->id, 'destination' => null])->assertConflict();
        $this->assertSame($a->id, $link->fresh()->project_id);
        $other = User::factory()->create();
        $foreign = $other->projects()->create(['name' => 'Private folder']);
        $this->getJson('/user/folders/contents?folder='.$foreign->id)->assertNotFound();
        $this->postJson('/user/folders/items/move', ['items' => ['link:'.$link->id], 'source' => $a->id, 'destination' => $foreign->id])->assertNotFound();
    }

    public function test_read_only_members_cannot_move_and_unfiled_is_scoped(): void
    {
        [$user,$ws,$a,$b,$link] = $this->desk();
        $loose = $user->links()->create(['type' => 'url', 'alias' => 'loose-explorer', 'title' => 'Unfiled own link']);
        $other = User::factory()->create();
        $other->links()->create(['type' => 'url', 'alias' => 'foreign-explorer', 'title' => 'Private foreign link']);
        $response = $this->getJson('/user/folders/contents')->assertOk();
        $this->assertStringContainsString('Unfiled own link', $response->getContent());
        $this->assertStringNotContainsString('Private foreign link', $response->getContent());
        $viewer = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $viewer->id, 'role' => 'viewer']);
        $this->actingAs($viewer)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $this->postJson('/user/folders/items/move', ['items' => ['link:'.$link->id], 'source' => $a->id, 'destination' => $b->id])->assertForbidden();
    }

    public function test_inherited_tasks_move_out_with_their_board_in_one_transaction(): void
    {
        [$user,$ws,$a,$b,$link] = $this->desk();
        $board = TaskBoard::create(['workspace_id' => $ws->id, 'owner_user_id' => $user->id, 'scope' => 'team', 'name' => 'Launch board']);
        $column = $board->columns()->create(['workspace_id' => $ws->id, 'name' => 'Todo', 'position' => 0]);
        $card = $board->cards()->create(['workspace_id' => $ws->id, 'column_id' => $column->id, 'title' => 'Launch task', 'position' => 0]);
        ProjectItem::create(['workspace_id' => $ws->id, 'project_id' => $a->id, 'user_id' => $user->id, 'kind' => 'board', 'resource_id' => $board->id]);
        $this->postJson('/user/folders/items/move', ['items' => ['task:'.$card->id], 'source' => $a->id, 'destination' => null])->assertUnprocessable();
        $this->postJson('/user/folders/items/move', ['items' => ['board:'.$board->id, 'task:'.$card->id], 'source' => $a->id, 'destination' => null])->assertOk();
        $this->assertDatabaseMissing('project_items',['kind' => 'board', 'resource_id' => $board->id]);
    }
}
