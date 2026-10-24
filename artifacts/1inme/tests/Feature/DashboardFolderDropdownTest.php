<?php

namespace Tests\Feature;

use App\Modules\User\Models\Project;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardFolderDropdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_dropdown_lists_all_owner_folders_and_excludes_other_users(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create(['onboarded_at'=>now()->subMonth()]);
        $ws = $owner->ownedWorkspaces()->first();
        $this->actingAs($owner)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $owner);
        foreach (range(1, 12) as $i) Project::create(['user_id'=>$owner->id,'name'=>sprintf('Folder %02d', $i),'color'=>'#8b5cf6']);
        $other = User::factory()->create();
        Project::create(['user_id'=>$other->id,'name'=>'Private unrelated folder']);
        $page = $this->get('/user/dashboard')->assertOk()->assertSee('Choose a folder')->assertSee('Folder 12')->assertDontSee('Private unrelated folder');
        if (getenv('NAV_UI_FIXTURE')) file_put_contents('/tmp/sayzio-dashboard-folders.html', $page->getContent());
        $html = view('user.partials.dashboard-folder-dropdown', ['__can'=>['links_view'=>false]])->render();
        $this->assertStringContainsString('Dashboard', $html);
        $this->assertStringNotContainsString('Choose a folder', $html);
        $this->assertStringNotContainsString('Folder 01', $html);
    }
}
