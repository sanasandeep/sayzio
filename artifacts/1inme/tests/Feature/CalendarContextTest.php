<?php

namespace Tests\Feature;

use App\Modules\User\Models\DeliveryProject;
use App\Modules\User\Models\DeliveryProjectTask;
use App\Modules\User\Models\Project;
use App\Modules\User\Models\User;
use App\Modules\User\Models\VaultClient;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class CalendarContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_folder_project_filters_match_csv_and_ics(): void
    {
        $user = User::factory()->create();
        $ws = $user->ownedWorkspaces()->first();
        $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $user);
        $client = VaultClient::create(['name' => 'Calendar client', 'visibility' => 'shared']);
        $folder = Project::create(['user_id' => $user->id, 'name' => 'Calendar Folder', 'color' => '#3155ef']);
        $project = DeliveryProject::create(['title' => 'Scheduled project', 'vault_client_id' => $client->id, 'folder_id' => $folder->id]);
        DeliveryProjectTask::create(['project_id' => $project->id, 'title' => 'Visible client task', 'assignee_user_id' => $user->id, 'start_date' => now()->addDay()->toDateString(), 'due_date' => now()->addDays(2)->toDateString(), 'status' => 'todo']);
        $other = DeliveryProject::create(['title' => 'Other project']);
        DeliveryProjectTask::create(['project_id' => $other->id, 'title' => 'Unrelated task', 'due_date' => now()->addDay()->toDateString(), 'status' => 'todo']);
        foreach (['client' => $client->id, 'folder' => $folder->id, 'project' => $project->id, 'assignee' => $user->id] as $kind => $id) {
            $query = http_build_query([$kind => [$id]]);
            $page = $this->get('/user/my-calendar?'.$query)->assertOk()->assertSee('Visible client task')->assertDontSee('Unrelated task')->assertSee('Event Pages')->assertSee('Connections');
            foreach (['csv', 'ics'] as $format) {
                $export = $this->get('/user/my-calendar/export?'.$query.'&format='.$format)->assertOk();
                $body = $export->baseResponse instanceof StreamedResponse ? $export->streamedContent() : $export->getContent();
                $this->assertStringContainsString('Visible client task', $body);
                $this->assertStringNotContainsString('Unrelated task', $body);
            }
        }
        $this->get('/user/calendar')->assertOk()->assertSee('Schedule')->assertSee('Connections');
        $this->get('/user/events')->assertOk()->assertSee('Event Pages')->assertSee('Connections');
        $this->get('/user/my-calendar/export?client=999999&format=csv')->assertOk()->assertDontSee('Visible client task');
        if (getenv('CALENDAR_UI_FIXTURE')) {
            file_put_contents('/tmp/unified-calendar.html', $page->getContent());
        }
    }
}
