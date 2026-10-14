<?php

namespace Tests\Feature;

use App\Modules\User\Models\DeliveryProject;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Project;
use App\Modules\User\Models\Receipt;
use App\Modules\User\Models\TaskBoard;
use App\Modules\User\Models\User;
use App\Modules\User\Models\VaultAttachment;
use App\Modules\User\Models\VaultAudit;
use App\Modules\User\Models\VaultClient;
use App\Modules\User\Models\VaultCredential;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Services\WorkspaceRoleMatrix;
use App\Services\Clients\ClientTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientTrackingVaultTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): array
    {
        $u = User::factory()->create();
        $ws = $u->ownedWorkspaces()->first();
        $this->actingAs($u)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);

        if (getenv('CLIENT_CREATE_FIXTURE')) {
            file_put_contents('/tmp/client-workspace-create.html', $this->get('/user/delivery-projects/create')->assertOk()->getContent());
            file_put_contents('/tmp/client-profile-create.html', $this->get('/user/vault/clients/create')->assertOk()->getContent());
        }

        return [$u, $ws];
    }

    public function test_client_has_multiple_projects_and_scannable_private_qr_storage(): void
    {
        [$owner,$ws] = $this->owner();
        config(['filesystems.default' => 'local']);
        Storage::fake('local');
        $client = VaultClient::create(['name' => 'Single identity', 'visibility' => 'private']);
        $folder = Project::create(['user_id' => $owner->id, 'name' => 'Delivery Folder', 'color' => '#3155ef']);
        foreach (['Website delivery', 'Monthly support'] as $title) {
            $this->post('/user/delivery-projects', ['title' => $title, 'vault_client_id' => $client->id, 'folder_id' => $folder->id])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertSame(1, VaultClient::count());
        $this->get('/user/projects/'.$folder->id)->assertOk()->assertSee('Client projects in this Folder')->assertSee('Monthly support');
        $page = $this->get('/user/vault/clients/'.$client->id)->assertOk()->assertSee('Website delivery')->assertSee('Monthly support')->assertSee('Add project');
        $this->get('/user/delivery-projects/create?client='.$client->id)->assertOk()->assertSee('New client project');
        $file = UploadedFile::fake()->image('scan.png', 200, 200);
        $this->post('/user/vault/clients/'.$client->id.'/attachments', ['file' => $file, 'purpose' => 'qr'])->assertRedirect()->assertSessionHasNoErrors();
        $attachment = VaultAttachment::firstOrFail();
        $this->assertTrue($attachment->encrypted);
        $this->assertSame('qr', $attachment->purpose);
        $page = $this->get('/user/vault/clients/'.$client->id)->assertOk();
        $preview = $this->get('/user/vault/attachments/'.$attachment->id.'/preview')->assertOk();
        $this->assertSame('inline', $preview->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $preview->headers->get('Cache-Control'));
        $this->post('/user/vault/clients/'.$client->id.'/attachments', ['file' => UploadedFile::fake()->createWithContent('bad.svg', '<svg/>'), 'purpose' => 'qr'])->assertSessionHasErrors('file');
        $member = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $member->id, 'role' => 'editor']);
        $this->actingAs($member)->withSession([WorkspaceContext::SESSION_KEY => $ws->id])->get('/user/vault/attachments/'.$attachment->id.'/preview')->assertNotFound();
        if (getenv('CLIENT_CREATE_FIXTURE')) {
            file_put_contents('/tmp/unified-client.html', $page->getContent());
        }
    }

    public function test_read_only_member_can_record_access_without_edit_permission(): void
    {
        [$owner, $ws] = $this->owner();
        $credential = VaultCredential::create(['label' => 'Shared account', 'visibility' => 'shared']);
        $matrix = WorkspaceRoleMatrix::forWorkspace($ws);
        $matrix['editor']['vault.edit'] = false;
        $matrix['editor']['vault.create'] = false;
        $this->put('/user/team/roles', ['matrix_json' => json_encode($matrix), 'selected_role' => 'editor'])->assertRedirect();
        $member = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $member->id, 'role' => 'editor']);
        $this->actingAs($member)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $this->postJson('/user/vault/events/credential/'.$credential->id, ['action' => 'copy', 'field' => 'username'])->assertNoContent();
        $this->assertDatabaseHas('vault_audit', ['actor_user_id' => $member->id, 'action' => 'copy', 'target_id' => $credential->id]);
        $this->put('/user/vault/credentials/'.$credential->id, ['label' => 'Forbidden edit'])->assertForbidden();
    }

    public function test_authenticator_is_encrypted_and_only_revealed_explicitly_with_audit(): void
    {
        [$u,$ws] = $this->owner();
        $key = 'JBSWY3DPEHPK3PXP';
        $this->post('/user/vault/credentials', ['label' => 'Test account', 'password' => 'example-secret', 'authenticator' => $key, 'visibility' => 'private'])->assertRedirect()->assertSessionHasNoErrors();
        $credential = VaultCredential::firstOrFail();
        $this->assertStringNotContainsString($key, $credential->getRawOriginal('authenticator_encrypted'));
        $page = $this->get('/user/vault/credentials/'.$credential->id)->assertOk()->assertDontSee($key)->assertDontSee('example-secret');
        $reveal = $this->postJson('/user/vault/credentials/'.$credential->id.'/reveal')->assertOk();
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $reveal->json('authenticator_qr'));
        $this->assertStringContainsString('no-store', $reveal->headers->get('Cache-Control'));
        $this->postJson('/user/vault/events/credential/'.$credential->id, ['action' => 'copy', 'field' => 'authenticator', 'value' => $key])->assertNoContent();
        $audit = VaultAudit::where('action', 'copy')->firstOrFail();
        $this->assertStringNotContainsString($key, json_encode($audit->toArray()));
        $member = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $member->id, 'role' => 'editor']);
        $this->actingAs($member)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $this->postJson('/user/vault/credentials/'.$credential->id.'/reveal')->assertNotFound();
        $this->postJson('/user/vault/events/credential/'.$credential->id, ['action' => 'copy', 'field' => 'password'])->assertNotFound();
        if (getenv('FORMS_UI_FIXTURE')) {
            file_put_contents('/tmp/secure-vault-credential.html', $page->getContent());
            file_put_contents('/tmp/vault-test-reveal.json', $reveal->getContent());
        }
    }

    public function test_client_records_link_without_importing_vault_secrets_and_open_is_logged(): void
    {
        [$u,$ws] = $this->owner();
        $profile = VaultClient::create(['name' => 'Example client', 'company' => 'Example Ltd', 'primary_email' => 'client@example.com', 'visibility' => 'private']);
        $profile->setEncrypted('notes', 'PRIVATE VAULT NOTE');
        $profile->save();
        $this->post('/user/delivery-projects', ['title' => 'Client launch', 'vault_client_id' => $profile->id])->assertRedirect()->assertSessionHasNoErrors();
        $project = DeliveryProject::firstOrFail();
        $this->assertSame($profile->id, $project->vault_client_id);
        $this->assertNull($project->client_email);
        $board = TaskBoard::create(['name' => 'Private work', 'scope' => 'personal', 'owner_user_id' => $u->id]);
        $column = $board->columns()->create(['name' => 'Todo', 'position' => 1]);
        $task = $board->cards()->create(['column_id' => $column->id, 'title' => 'Client task']);
        $this->post('/user/delivery-projects/'.$project->id.'/tracking', ['items' => ['task:'.$task->id]])->assertRedirect()->assertSessionHasNoErrors();
        $page = $this->get('/user/delivery-projects/'.$project->id)->assertOk()->assertSee('Example client')->assertSee('Client task')->assertDontSee('PRIVATE VAULT NOTE');
        $this->get('/user/delivery-projects/'.$project->id.'/tracking/task/'.$task->id)->assertRedirect(route('user.tasks.show', $board));
        $this->assertDatabaseHas('workspace_activity_events', ['action' => 'client.open', 'object_id' => $project->id]);
        $member = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $member->id, 'role' => 'editor']);
        $this->actingAs($member)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $this->get('/user/delivery-projects/'.$project->id)->assertOk()->assertDontSee('Example client')->assertDontSee('Client task')->assertSee('profile access is restricted');
        $this->get('/user/delivery-projects/'.$project->id.'/tracking/task/'.$task->id)->assertNotFound();
        if (getenv('FORMS_UI_FIXTURE')) {
            file_put_contents('/tmp/client-tracking.html', $page->getContent());
        }
    }

    public function test_billing_tracker_is_workspace_scoped_and_uses_recorded_receipts(): void
    {
        [$u,$ws] = $this->owner();
        $profile = VaultClient::create(['name' => 'Billing client', 'visibility' => 'shared']);
        $project = DeliveryProject::create(['title' => 'Billing workspace', 'vault_client_id' => $profile->id]);
        $invoice = Invoice::create(['user_id' => $u->id, 'workspace_id' => $ws->id, 'kind' => 'client', 'vault_client_id' => $profile->id, 'financial_year' => '2026-27', 'seq' => 1, 'subtotal_minor' => 25000, 'tax_total_minor' => 0, 'billing_address_snapshot' => [], 'merchant_snapshot' => [], 'line_items' => [], 'tax_breakdown' => [], 'number' => 'CLIENT-TEST-1', 'currency' => 'USD', 'grand_total_minor' => 25000, 'status' => 'paid']);
        Receipt::create(['user_id' => $u->id, 'invoice_id' => $invoice->id, 'financial_year' => '2026-27', 'seq' => 1, 'snapshot' => [], 'number' => 'RECEIPT-TEST-1', 'currency' => 'USD', 'amount_minor' => 10000, 'paid_at' => now()]);
        $tracker = app(ClientTracker::class);
        $data = $tracker->data($project);
        $this->assertSame(1, $data['counts']['invoice']);
        $this->assertSame(10000, (int) $data['ledger']->first()['paid_minor']);
        $this->post('/user/delivery-projects/'.$project->id.'/tracking', ['items' => ['invoice:'.$invoice->id]])->assertRedirect();
        $this->assertCount(1, $tracker->data($project)['items']);
        $other = User::factory()->create();
        $otherWs = $other->ownedWorkspaces()->first();
        $foreign = Invoice::create(['user_id' => $other->id, 'workspace_id' => $otherWs->id, 'kind' => 'client', 'financial_year' => '2026-27', 'seq' => 2, 'subtotal_minor' => 100, 'tax_total_minor' => 0, 'billing_address_snapshot' => [], 'merchant_snapshot' => [], 'line_items' => [], 'tax_breakdown' => [], 'number' => 'FOREIGN-TEST', 'currency' => 'USD', 'grand_total_minor' => 100, 'status' => 'draft']);
        $this->post('/user/delivery-projects/'.$project->id.'/tracking', ['items' => ['invoice:'.$foreign->id]])->assertNotFound();
    }
}
