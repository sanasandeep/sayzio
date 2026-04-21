<?php

namespace Tests\Feature;

use App\Modules\User\Models\CloudConnection;
use App\Modules\User\Models\CloudFile;
use App\Modules\User\Models\CloudProviderApp;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CloudFilesTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $tag = 'u'): User
    {
        return User::create([
            'name'     => $tag . ' ' . Str::random(4),
            'email'    => $tag . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
    }

    private function bindWorkspace(User $user): Workspace
    {
        $ws = app(WorkspaceContext::class)->resolve($user);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $user);
        return $ws;
    }

    public function test_owner_can_save_oauth_app_and_secret_is_encrypted_at_rest(): void
    {
        $owner = $this->makeUser('o');
        $this->bindWorkspace($owner);

        $this->actingAs($owner)
            ->put('/user/cloud-files/settings/google_drive', [
                'client_id'     => 'gid-123',
                'client_secret' => 'top-secret-shh',
                'enabled'       => '1',
            ])
            ->assertRedirect();

        $row = CloudProviderApp::where('provider', 'google_drive')->firstOrFail();
        $this->assertSame('gid-123', $row->client_id);
        $this->assertSame('top-secret-shh', $row->client_secret_encrypted);

        // Raw column must be ciphertext, not plaintext.
        $raw = \DB::table('cloud_provider_apps')->where('id', $row->id)->value('client_secret_encrypted');
        $this->assertNotEquals('top-secret-shh', $raw);
        $this->assertNotEmpty($raw);
    }

    public function test_oauth_start_blocks_when_app_not_configured(): void
    {
        $owner = $this->makeUser('o');
        $this->bindWorkspace($owner);

        $this->actingAs($owner)
            ->get('/user/cloud-oauth/dropbox/start')
            ->assertRedirect(route('user.cloud-files.connections'));
    }

    public function test_oauth_callback_rejects_state_mismatch(): void
    {
        $owner = $this->makeUser('o');
        $this->bindWorkspace($owner);
        CloudProviderApp::create([
            'provider'                => 'google_drive',
            'client_id'               => 'gid',
            'client_secret_encrypted' => 'sec',
            'enabled'                 => true,
        ]);

        session(['cloud_oauth_state_google_drive' => 'expected-state']);

        $this->actingAs($owner)
            ->get('/user/cloud-oauth/google_drive/callback?state=wrong&code=abc')
            ->assertRedirect(route('user.cloud-files.connections'))
            ->assertSessionHas('error');
    }

    public function test_oauth_callback_stores_encrypted_tokens_on_success(): void
    {
        $owner = $this->makeUser('o');
        $ws = $this->bindWorkspace($owner);
        CloudProviderApp::create([
            'provider'                => 'google_drive',
            'client_id'               => 'gid',
            'client_secret_encrypted' => 'sec',
            'enabled'                 => true,
        ]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token'  => 'AT-123',
                'refresh_token' => 'RT-456',
                'expires_in'    => 3600,
                'scope'         => 'a b',
            ]),
            'googleapis.com/oauth2/v2/userinfo' => Http::response(['email' => 'me@example.com']),
        ]);

        session([
            'cloud_oauth_state_google_drive' => 'st',
            'cloud_oauth_ws_google_drive'    => $ws->id,
        ]);

        $this->actingAs($owner)
            ->get('/user/cloud-oauth/google_drive/callback?state=st&code=authcode')
            ->assertRedirect(route('user.cloud-files.connections'));

        $conn = CloudConnection::where('user_id', $owner->id)->firstOrFail();
        $this->assertSame('me@example.com', $conn->account_email);
        $this->assertSame('AT-123', $conn->access_token_encrypted);
        $this->assertSame('RT-456', $conn->refresh_token_encrypted);

        $rawAccess = \DB::table('cloud_connections')->where('id', $conn->id)->value('access_token_encrypted');
        $this->assertNotSame('AT-123', $rawAccess);
        $this->assertNotEmpty($rawAccess);
    }

    public function test_picker_browse_calls_provider_and_lists(): void
    {
        $owner = $this->makeUser('o');
        $this->bindWorkspace($owner);
        CloudProviderApp::create([
            'provider'                => 'google_drive',
            'client_id'               => 'gid',
            'client_secret_encrypted' => 'sec',
            'enabled'                 => true,
        ]);
        $conn = CloudConnection::create([
            'user_id'                => $owner->id,
            'provider'               => 'google_drive',
            'account_email'          => 'me@x.com',
            'access_token_encrypted' => 'AT',
            'expires_at'             => now()->addHour(),
        ]);

        Http::fake([
            'googleapis.com/drive/v3/files*' => Http::response([
                'files' => [
                    ['id' => 'd1', 'name' => 'Folder A', 'mimeType' => 'application/vnd.google-apps.folder'],
                    ['id' => 'f1', 'name' => 'Doc.pdf', 'mimeType' => 'application/pdf', 'size' => '100', 'webViewLink' => 'https://drive.google.com/x'],
                ],
            ]),
        ]);

        $resp = $this->actingAs($owner)->getJson('/user/cloud-files/picker/' . $conn->id);
        $resp->assertOk()
             ->assertJsonPath('folders.0.id', 'd1')
             ->assertJsonPath('files.0.name', 'Doc.pdf');
    }

    public function test_picker_search_calls_provider_search(): void
    {
        $owner = $this->makeUser('o');
        $this->bindWorkspace($owner);
        CloudProviderApp::create([
            'provider'                => 'google_drive',
            'client_id'               => 'gid', 'client_secret_encrypted' => 'sec', 'enabled' => true,
        ]);
        $conn = CloudConnection::create([
            'user_id' => $owner->id, 'provider' => 'google_drive',
            'access_token_encrypted' => 'AT', 'expires_at' => now()->addHour(),
        ]);

        Http::fake([
            'googleapis.com/drive/v3/files*' => Http::response([
                'files' => [
                    ['id' => 'f9', 'name' => 'budget.xlsx', 'mimeType' => 'application/vnd.ms-excel', 'size' => '50', 'webViewLink' => 'https://drive.google.com/x'],
                ],
            ]),
        ]);

        $this->actingAs($owner)
            ->getJson('/user/cloud-files/picker/' . $conn->id . '?search=' . urlencode('budget'))
            ->assertOk()
            ->assertJsonPath('files.0.name', 'budget.xlsx')
            ->assertJsonPath('folders', []);

        Http::assertSent(function ($req) {
            return str_contains($req->url(), 'name+contains') || str_contains(urldecode($req->url()), "name contains 'budget'");
        });
    }

    public function test_settings_test_endpoint_reports_bad_client_id(): void
    {
        $owner = $this->makeUser('o');
        $this->bindWorkspace($owner);
        CloudProviderApp::create([
            'provider' => 'google_drive', 'client_id' => 'wrong', 'client_secret_encrypted' => 'wrong', 'enabled' => true,
        ]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        $this->actingAs($owner)
            ->postJson('/user/cloud-files/settings/google_drive/test')
            ->assertOk()
            ->assertJson(['ok' => false]);
    }

    public function test_settings_test_endpoint_reports_credentials_ok(): void
    {
        $owner = $this->makeUser('o');
        $this->bindWorkspace($owner);
        CloudProviderApp::create([
            'provider' => 'google_drive', 'client_id' => 'good', 'client_secret_encrypted' => 'good', 'enabled' => true,
        ]);

        // invalid_grant → server got past credential check.
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $this->actingAs($owner)
            ->postJson('/user/cloud-files/settings/google_drive/test')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_owner_filter_narrows_library_to_one_member(): void
    {
        $owner = $this->makeUser('o');
        $member = $this->makeUser('m');
        $ws = $this->bindWorkspace($owner);
        \App\Modules\User\Models\WorkspaceMember::create([
            'workspace_id' => $ws->id, 'user_id' => $member->id, 'role' => 'editor', 'status' => 'active',
        ]);
        $conn = CloudConnection::create(['user_id' => $owner->id, 'provider' => 'dropbox', 'access_token_encrypted' => 'x']);
        CloudFile::create([
            'added_by_user_id' => $owner->id, 'connection_id' => $conn->id, 'provider' => 'dropbox',
            'remote_id' => 'r-owner', 'name' => 'OwnerFile', 'link' => 'https://x', 'added_at' => now(),
        ]);
        CloudFile::create([
            'added_by_user_id' => $member->id, 'connection_id' => $conn->id, 'provider' => 'dropbox',
            'remote_id' => 'r-member', 'name' => 'MemberFile', 'link' => 'https://x', 'added_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get('/user/cloud-files?owner=' . $member->id)
            ->assertOk()
            ->assertSee('MemberFile')
            ->assertDontSee('OwnerFile');
    }

    public function test_oauth_callback_aborts_when_user_switched_workspaces_mid_flow(): void
    {
        $owner = $this->makeUser('o');
        $ws1 = $this->bindWorkspace($owner);
        CloudProviderApp::create([
            'provider' => 'google_drive', 'client_id' => 'g', 'client_secret_encrypted' => 's', 'enabled' => true,
        ]);

        // The user switches active workspace between OAuth /start and /callback.
        $ws2 = \App\Modules\User\Models\Workspace::create([
            'owner_user_id' => $owner->id, 'name' => 'WS2', 'slug' => 'ws2-' . uniqid(),
        ]);

        // Simulate the session state set during /start in ws1, with the
        // user's currently-active workspace now ws2.
        session([
            'cloud_oauth_state_google_drive' => 'st',
            'cloud_oauth_ws_google_drive'    => $ws1->id,
            \App\Modules\User\Services\WorkspaceContext::SESSION_KEY => $ws2->id,
        ]);

        // Defense-in-depth: the callback must NOT silently land the
        // connection in whichever workspace happens to be active at
        // redirect time. The current behavior is to abort the flow
        // entirely (user is shown a "workspace changed" error and asked
        // to reconnect). Either way, no connection must appear in ws2,
        // and the token endpoint must not even be called.
        Http::fake();
        $this->actingAs($owner)
            ->get('/user/cloud-oauth/google_drive/callback?code=abc&state=st')
            ->assertRedirect(route('user.cloud-files.connections'))
            ->assertSessionHas('error');

        $this->assertSame(0, CloudConnection::where('workspace_id', $ws1->id)->count());
        $this->assertSame(0, CloudConnection::where('workspace_id', $ws2->id)->count());
        Http::assertNothingSent();
    }

    public function test_picker_blocks_other_users_connection(): void
    {
        $alice = $this->makeUser('a');
        $bob   = $this->makeUser('b');
        $this->bindWorkspace($alice);
        CloudProviderApp::create([
            'provider' => 'google_drive', 'client_id' => 'g', 'client_secret_encrypted' => 's', 'enabled' => true,
        ]);
        $conn = CloudConnection::create([
            'user_id'                => $bob->id,
            'provider'               => 'google_drive',
            'access_token_encrypted' => 'AT',
            'expires_at'             => now()->addHour(),
        ]);

        $this->actingAs($alice)
            ->getJson('/user/cloud-files/picker/' . $conn->id)
            ->assertForbidden();
    }

    public function test_store_adds_files_visible_to_workspace_and_dedupes(): void
    {
        $owner = $this->makeUser('o');
        $ws = $this->bindWorkspace($owner);
        $conn = CloudConnection::create([
            'user_id'                => $owner->id,
            'provider'               => 'dropbox',
            'access_token_encrypted' => 'AT',
        ]);

        $payload = [
            'connection_id' => $conn->id,
            'items' => [
                ['remote_id' => 'r1', 'name' => 'A.pdf', 'size' => 10, 'link' => 'https://example.com/a'],
                ['remote_id' => 'r2', 'name' => 'B.pdf', 'size' => 20, 'link' => 'https://example.com/b'],
            ],
        ];

        $this->actingAs($owner)
            ->postJson('/user/cloud-files', $payload)
            ->assertOk()
            ->assertJson(['added' => 2]);

        // Re-post same items: dedupe by (workspace, provider, remote_id).
        $this->actingAs($owner)
            ->postJson('/user/cloud-files', $payload)
            ->assertOk()
            ->assertJson(['added' => 0]);

        $this->assertSame(2, CloudFile::where('workspace_id', $ws->id)->count());
    }

    public function test_index_filters_to_active_workspace_only(): void
    {
        $alice = $this->makeUser('a');
        $bob   = $this->makeUser('b');

        $wsA = $this->bindWorkspace($alice);
        $connA = CloudConnection::create(['user_id' => $alice->id, 'provider' => 'dropbox', 'access_token_encrypted' => 'x']);
        CloudFile::create([
            'added_by_user_id' => $alice->id, 'connection_id' => $connA->id, 'provider' => 'dropbox',
            'remote_id' => 'a', 'name' => 'AliceFile', 'link' => 'https://x', 'added_at' => now(),
        ]);

        $wsB = $this->bindWorkspace($bob);
        $connB = CloudConnection::create(['user_id' => $bob->id, 'provider' => 'dropbox', 'access_token_encrypted' => 'x']);
        CloudFile::create([
            'added_by_user_id' => $bob->id, 'connection_id' => $connB->id, 'provider' => 'dropbox',
            'remote_id' => 'a', 'name' => 'BobFile', 'link' => 'https://x', 'added_at' => now(),
        ]);

        $this->bindWorkspace($alice);
        $this->actingAs($alice)
            ->get('/user/cloud-files')
            ->assertOk()
            ->assertSee('AliceFile')
            ->assertDontSee('BobFile');
    }

    public function test_onedrive_picker_rejects_untrusted_cursor_url(): void
    {
        $owner = $this->makeUser('o');
        $this->bindWorkspace($owner);
        CloudProviderApp::create([
            'provider'                => 'onedrive',
            'client_id'               => 'mid',
            'client_secret_encrypted' => 'sec',
            'enabled'                 => true,
        ]);
        $conn = CloudConnection::create([
            'user_id'                => $owner->id,
            'provider'               => 'onedrive',
            'access_token_encrypted' => 'AT',
            'expires_at'             => now()->addHour(),
        ]);

        // Block any HTTP that escapes the allowlist — if the SSRF guard fails
        // open, this fake would record an outbound to attacker.example.
        Http::fake();

        $resp = $this->actingAs($owner)->getJson(
            '/user/cloud-files/picker/' . $conn->id . '?cursor=' . urlencode('https://attacker.example/steal')
        );
        $resp->assertStatus(422);

        Http::assertNothingSent();

        // Sanity: a Graph cursor IS accepted. Clear the last_error the failed
        // attempt recorded so the broken-connection short-circuit doesn't fire.
        $conn->update(['last_error' => null]);
        Http::fake(['graph.microsoft.com/*' => Http::response(['value' => []])]);
        $this->actingAs($owner)
            ->getJson('/user/cloud-files/picker/' . $conn->id . '?cursor=' . urlencode('https://graph.microsoft.com/v1.0/me/drive/root/children?$skiptoken=abc'))
            ->assertOk();
    }

    public function test_settings_page_requires_owner(): void
    {
        $owner = $this->makeUser('o');
        $member = $this->makeUser('m');
        $ws = $this->bindWorkspace($owner);

        // Attach member with viewer role only.
        \App\Modules\User\Models\WorkspaceMember::create([
            'workspace_id' => $ws->id, 'user_id' => $member->id, 'role' => 'viewer', 'status' => 'active',
        ]);

        $this->bindWorkspace($member);
        app()->instance('workspace_owner', $owner);

        $this->actingAs($member)
            ->get('/user/cloud-files/settings')
            ->assertStatus(403);
    }
}
