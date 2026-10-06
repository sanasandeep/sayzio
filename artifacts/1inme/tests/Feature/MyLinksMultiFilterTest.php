<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyLinksMultiFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_applying_multiple_types_renders_results_and_filter_tags(): void
    {
        $user = User::factory()->create()->fresh();
        $workspace = $user->ownedWorkspaces()->first();
        foreach (['resume', 'calendar', 'reviews', 'url'] as $type) {
            $link = $user->links()->create([
                'user_id' => $user->id, 'type' => $type, 'title' => 'Filter fixture '.$type,
                'alias' => 'multi-'.$type, 'is_active' => true, 'long_url' => 'https://example.com',
            ]);
            if ($workspace) $link->forceFill(['workspace_id' => $workspace->id])->save();
        }
        $this->actingAs($user)->withSession($workspace ? [WorkspaceContext::SESSION_KEY => $workspace->id] : []);
        $query = http_build_query(['type' => ['resume', 'calendar', 'reviews'], 'sort' => 'newest']);
        $this->get('/user/links?'.$query)->assertOk()
            ->assertSee('Filter fixture resume')->assertSee('Filter fixture calendar')
            ->assertSee('Filter fixture reviews')->assertDontSee('Filter fixture url')
            ->assertSee('Remove Type:');
        $this->get('/user/links?type=resume')->assertOk()
            ->assertSee('Filter fixture resume')->assertDontSee('Filter fixture calendar');
    }

    public function test_array_status_and_folder_filters_work_for_list_and_export(): void
    {
        $user = User::factory()->create()->fresh();
        $workspace = $user->ownedWorkspaces()->first();
        foreach ([true, false] as $active) {
            $link = $user->links()->create([
                'user_id' => $user->id, 'type' => 'url',
                'title' => $active ? 'Active fixture' : 'Inactive fixture',
                'alias' => $active ? 'multi-active' : 'multi-inactive',
                'is_active' => $active, 'project_id' => null, 'long_url' => 'https://example.com',
            ]);
            if ($workspace) $link->forceFill(['workspace_id' => $workspace->id])->save();
        }
        $this->actingAs($user)->withSession($workspace ? [WorkspaceContext::SESSION_KEY => $workspace->id] : []);
        $query = http_build_query(['type' => ['url'], 'status' => ['inactive'], 'project_id' => ['none']]);
        $this->get('/user/links?'.$query)->assertOk()->assertSee('Inactive fixture')->assertDontSee('Active fixture');
        $export = $this->get(route('user.links.export').'?'.$query)->assertOk()->streamedContent();
        $this->assertStringContainsString('Inactive fixture', $export);
        $this->assertStringNotContainsString('Active fixture', $export);
    }
}
