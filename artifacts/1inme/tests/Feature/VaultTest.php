<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Models\VaultAttachment;
use App\Modules\User\Models\VaultAudit;
use App\Modules\User\Models\VaultClient;
use App\Modules\User\Models\VaultCredential;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Services\WorkspaceEncryption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VaultTest extends TestCase
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

    public function test_round_trip_encryption_per_workspace(): void
    {
        $alice = $this->makeUser('alice');
        $bob = $this->makeUser('bob');
        $wsA = $this->bindWorkspace($alice);
        $cred = new VaultCredential();
        $cred->fill(['label' => 'GitHub', 'username' => 'a@b']);
        $cred->setEncrypted('password', 's3cret-pw');
        $cred->setEncrypted('notes', 'hello world');
        $cred->save();

        $wsB = $this->bindWorkspace($bob);
        $svc = app(WorkspaceEncryption::class);

        // Ciphertext should not be readable with the other workspace's key.
        $cipher = $cred->fresh()->password_encrypted;
        $this->assertNotEmpty($cipher);
        $thrown = false;
        try { $svc->decrypt($wsB->id, $cipher); } catch (\Throwable $e) { $thrown = true; }
        $this->assertTrue($thrown, 'cross-workspace decrypt must fail');

        // Same workspace decrypts fine.
        app()->instance('current_workspace', $wsA);
        app()->instance('workspace_owner', $alice);
        $reload = VaultCredential::find($cred->id);
        $this->assertSame('s3cret-pw', $reload->getEncrypted('password'));
        $this->assertSame('hello world', $reload->getEncrypted('notes'));
    }

    public function test_workspace_isolation(): void
    {
        $alice = $this->makeUser('alice');
        $bob = $this->makeUser('bob');
        $wsA = $this->bindWorkspace($alice);
        VaultCredential::create(['label' => 'AWS', 'username' => 'root']);

        // Switch to bob's workspace — alice's credential must not appear.
        $wsB = $this->bindWorkspace($bob);
        $this->assertSame(0, VaultCredential::count());

        // Withoutscope view confirms it exists in DB.
        $this->assertSame(1, VaultCredential::query()->withoutGlobalScope('workspace')->count());
    }

    public function test_viewer_cannot_create_credential(): void
    {
        $owner = $this->makeUser('owner');
        $viewer = $this->makeUser('viewer');
        $ws = $this->bindWorkspace($owner);
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $viewer->id, 'role' => 'viewer']);

        $this->actingAs($viewer)
            ->post('/user/vault/credentials', ['label' => 'X', 'password' => 'p'])
            ->assertStatus(403);
    }

    public function test_admin_member_can_create_and_owner_sees(): void
    {
        $owner = $this->makeUser('owner');
        $admin = $this->makeUser('admin');
        $ws = $this->bindWorkspace($owner);
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $admin->id, 'role' => 'admin']);

        $this->actingAs($admin)
            ->post('/user/vault/credentials', ['label' => 'API key', 'password' => 'topsecret'])
            ->assertRedirect();

        $this->bindWorkspace($owner);
        $this->assertSame(1, VaultCredential::where('label', 'API key')->count());
    }

    public function test_private_credential_hidden_from_other_member(): void
    {
        $owner = $this->makeUser('owner');
        $admin = $this->makeUser('admin');
        $ws = $this->bindWorkspace($owner);
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $admin->id, 'role' => 'admin']);

        // Admin creates a *private* credential — only admin + owner should see it.
        $this->actingAs($admin)
            ->post('/user/vault/credentials', ['label' => 'My private', 'password' => 'p', 'visibility' => 'private'])
            ->assertRedirect();

        $cred = VaultCredential::where('label', 'My private')->first();
        $this->assertNotNull($cred);

        // Another admin (different user) should NOT see it.
        $stranger = $this->makeUser('strange');
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $stranger->id, 'role' => 'admin']);
        $this->bindWorkspace($owner); // ensure ws is the same
        $this->actingAs($stranger)->get('/user/vault/credentials/' . $cred->id)->assertStatus(404);

        // The creator (admin who posted it) can see their own private record.
        $this->actingAs($admin)->get('/user/vault/credentials/' . $cred->id)->assertStatus(200);

        // The workspace owner can see it.
        $this->actingAs($owner)->get('/user/vault/credentials/' . $cred->id)->assertStatus(200);
    }

    public function test_reveal_writes_audit_entry(): void
    {
        $owner = $this->makeUser('owner');
        $ws = $this->bindWorkspace($owner);
        $cred = VaultCredential::create(['label' => 'Stripe']);
        $cred->setEncrypted('password', 'sk_live_xxx');
        $cred->save();

        $this->actingAs($owner)
            ->postJson('/user/vault/credentials/' . $cred->id . '/reveal')
            ->assertOk()
            ->assertJson(['password' => 'sk_live_xxx']);

        $this->bindWorkspace($owner);
        $this->assertSame(1, VaultAudit::where('action', 'reveal')->where('target_id', $cred->id)->count());
    }

    public function test_owner_only_can_export(): void
    {
        $owner = $this->makeUser('owner');
        $admin = $this->makeUser('admin');
        $ws = $this->bindWorkspace($owner);
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $admin->id, 'role' => 'admin']);

        // Admin (non-owner) is blocked.
        $this->actingAs($admin)->get('/user/vault/export')->assertStatus(403);

        // Owner can open the page and download.
        $this->actingAs($owner)->get('/user/vault/export')->assertOk();
        $resp = $this->actingAs($owner)->post('/user/vault/export', ['passphrase' => 'corr3cthorse']);
        $resp->assertOk();
        $body = $resp->streamedContent();
        $env = json_decode($body, true);
        $this->assertIsArray($env);
        $this->assertArrayHasKey('ct', $env);
        $this->assertArrayHasKey('iv', $env);
        $this->assertArrayHasKey('tag', $env);
        $this->bindWorkspace($owner);
        $this->assertTrue(VaultAudit::where('action', 'export')->exists());
    }

    public function test_search_excludes_encrypted_fields(): void
    {
        $owner = $this->makeUser('owner');
        $this->bindWorkspace($owner);

        $a = VaultCredential::create(['label' => 'Production DB', 'username' => 'admin']);
        $a->setEncrypted('password', 'NEEDLE_ENC'); $a->save();
        $a->setEncrypted('notes', 'NEEDLE_ENC'); $a->save();

        VaultCredential::create(['label' => 'NEEDLE_PLAIN', 'username' => 'x']);

        // Searching for the encrypted-only string returns no rows.
        $this->actingAs($owner)
            ->get('/user/vault/credentials?q=NEEDLE_ENC')
            ->assertOk()
            ->assertDontSee('Production DB');

        // Searching the plaintext label finds it.
        $this->actingAs($owner)
            ->get('/user/vault/credentials?q=NEEDLE_PLAIN')
            ->assertOk()
            ->assertSee('NEEDLE_PLAIN');
    }

    public function test_audit_log_is_append_only_no_destroy_route(): void
    {
        $owner = $this->makeUser('owner');
        $this->bindWorkspace($owner);
        $cred = VaultCredential::create(['label' => 'X']);
        $cred->setEncrypted('password', 'p'); $cred->save();
        $this->actingAs($owner)->postJson('/user/vault/credentials/' . $cred->id . '/reveal')->assertOk();

        $audit = VaultAudit::first();
        $this->assertNotNull($audit);

        // No registered route accepts DELETE on /user/vault/audit/* — confirm 404.
        $this->actingAs($owner)->delete('/user/vault/audit/' . $audit->id)->assertStatus(404);
        $this->actingAs($owner)->put('/user/vault/audit/' . $audit->id)->assertStatus(404);
    }

    public function test_client_with_emails_phones_addresses_and_notes_round_trip(): void
    {
        $owner = $this->makeUser('owner');
        $this->bindWorkspace($owner);

        $this->actingAs($owner)->post('/user/vault/clients', [
            'name'      => 'Acme Co',
            'company'   => 'Acme',
            'website'   => 'https://acme.test',
            'notes'     => 'NDA signed.',
            'tags'      => 'enterprise,priority',
            'emails'    => [['email' => 'ops@acme.test', 'label' => 'work']],
            'phones'    => [['phone' => '+1-555-0100', 'label' => 'main']],
            'addresses' => [['line1' => '1 Acme Way', 'city' => 'Springfield']],
            'fields'    => [['key' => 'Account #', 'value' => 'A-12345']],
        ])->assertRedirect();

        $client = VaultClient::where('name', 'Acme Co')->first();
        $this->assertNotNull($client);
        $this->assertSame('ops@acme.test', $client->primary_email);
        $this->assertSame('+1-555-0100', $client->primary_phone);
        $this->assertSame(1, $client->emails()->count());
        $this->assertSame(1, $client->addresses()->count());
        $this->assertSame('NDA signed.', $client->getEncrypted('notes'));
        $this->assertSame([['key' => 'Account #', 'value' => 'A-12345']], $client->getEncrypted('fields', true));
    }

    public function test_tag_search_does_not_cross_workspaces(): void
    {
        $alice = $this->makeUser('alice');
        $bob = $this->makeUser('bob');

        $this->bindWorkspace($alice);
        VaultCredential::create(['label' => 'alice creds', 'tags' => ['shared-tag']]);

        $this->bindWorkspace($bob);
        VaultCredential::create(['label' => 'bob creds', 'tags' => ['shared-tag']]);

        // Bob searching for the shared tag must only see his own row, not alice's.
        $resp = $this->actingAs($bob)->get('/user/vault/credentials?q=shared-tag')->assertOk();
        $resp->assertSee('bob creds');
        $resp->assertDontSee('alice creds');

        // Same for clients.
        $this->bindWorkspace($alice);
        VaultClient::create(['name' => 'alice client', 'tags' => ['leakcheck']]);
        $this->bindWorkspace($bob);
        VaultClient::create(['name' => 'bob client', 'tags' => ['leakcheck']]);
        $resp2 = $this->actingAs($bob)->get('/user/vault/clients?q=leakcheck')->assertOk();
        $resp2->assertSee('bob client');
        $resp2->assertDontSee('alice client');
    }

    public function test_attachment_download_returns_generic_error_when_ciphertext_corrupt(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);
        $owner = $this->makeUser('owner');
        $ws = $this->bindWorkspace($owner);
        $client = VaultClient::create(['name' => 'C']);

        // Write a corrupt blob directly and create the row pointing at it.
        $path = 'vault/' . $ws->id . '/clients/' . $client->id . '/broken.enc';
        Storage::disk('local')->put($path, 'not-real-cipher');
        $att = VaultAttachment::create([
            'workspace_id'        => $ws->id,
            'uploaded_by_user_id' => $owner->id,
            'parent_type'         => 'client',
            'parent_id'           => $client->id,
            'filename'            => 'broken.bin',
            'disk'                => 'local',
            'path'                => $path,
            'size'                => 15,
            'encrypted'           => true,
        ]);

        $this->actingAs($owner)
            ->get('/user/vault/attachments/' . $att->id . '/download')
            ->assertStatus(422);

        $this->bindWorkspace($owner);
        $this->assertTrue(VaultAudit::where('action', 'reveal')->where('target_id', $client->id)->exists());
    }

    public function test_attachment_bytes_are_encrypted_at_rest_and_decrypt_on_download(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);
        $owner = $this->makeUser('owner');
        $this->bindWorkspace($owner);
        $client = VaultClient::create(['name' => 'Acme']);

        $payload = "PLAINTEXT-MARKER-" . str_repeat('x', 100);
        $upload = \Illuminate\Http\UploadedFile::fake()->createWithContent('contract.txt', $payload);

        $this->actingAs($owner)
            ->post('/user/vault/clients/' . $client->id . '/attachments', ['file' => $upload])
            ->assertRedirect();

        $att = VaultAttachment::first();
        $this->assertNotNull($att);
        $this->assertTrue((bool) $att->encrypted);

        // Bytes on disk must NOT contain the plaintext marker.
        $onDisk = Storage::disk('local')->get($att->path);
        $this->assertStringNotContainsString('PLAINTEXT-MARKER-', $onDisk);

        // Download decrypts and returns the original bytes.
        $resp = $this->actingAs($owner)->get('/user/vault/attachments/' . $att->id . '/download');
        $resp->assertOk();
        $this->assertSame($payload, $resp->getContent());
    }

    public function test_export_includes_attachments_and_social_handles(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);
        $owner = $this->makeUser('owner');
        $ws = $this->bindWorkspace($owner);

        $client = VaultClient::create(['name' => 'Acme']);
        $client->setEncrypted('social_handles', [['network' => 'twitter', 'handle' => '@acme', 'url' => 'https://t/acme']]);
        $client->save();

        $this->actingAs($owner)->post('/user/vault/clients/' . $client->id . '/attachments', [
            'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('doc.txt', 'EXPORT-CONTENT'),
        ])->assertRedirect();

        $resp = $this->actingAs($owner)->post('/user/vault/export', ['passphrase' => 'corr3cthorse']);
        $resp->assertOk();
        $env = json_decode($resp->streamedContent(), true);

        // Decrypt the envelope and confirm both attachments and social handles round-trip.
        $salt = base64_decode($env['kdf']['salt']);
        $iv   = base64_decode($env['iv']);
        $ct   = base64_decode($env['ct']);
        $tag  = base64_decode($env['tag']);
        $key  = hash_pbkdf2('sha256', 'corr3cthorse', $salt, $env['kdf']['iters'], 32, true);
        $plain = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        $this->assertNotFalse($plain);

        $payload = json_decode($plain, true);
        $this->assertArrayHasKey('attachments', $payload);
        $this->assertCount(1, $payload['attachments']);
        $this->assertSame('doc.txt', $payload['attachments'][0]['filename']);
        $this->assertSame('EXPORT-CONTENT', base64_decode($payload['attachments'][0]['content_b64']));

        $this->assertSame('@acme', $payload['clients'][0]['social_handles'][0]['handle']);
    }

    public function test_client_attachments_load_via_relation_and_cascade_on_delete(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);
        $owner = $this->makeUser('owner');
        $this->bindWorkspace($owner);
        $client = VaultClient::create(['name' => 'Acme']);

        $this->actingAs($owner)->post('/user/vault/clients/' . $client->id . '/attachments', [
            'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('a.txt', 'hello'),
        ])->assertRedirect();

        // Relation must surface the row (regression: morphMany previously hid it).
        $this->assertCount(1, $client->fresh()->attachments);

        $att = VaultAttachment::first();
        $this->assertTrue(Storage::disk('local')->exists($att->path));

        $this->actingAs($owner)
            ->delete('/user/vault/clients/' . $client->id)
            ->assertRedirect();

        $this->assertFalse(Storage::disk('local')->exists($att->path));
        $this->assertSame(0, VaultAttachment::count());
    }

    public function test_credential_delete_cascades_attachments_on_disk(): void
    {
        Storage::fake('local');
        $owner = $this->makeUser('owner');
        $ws = $this->bindWorkspace($owner);

        $cred = VaultCredential::create(['label' => 'with files']);
        Storage::disk('local')->put('vault/' . $ws->id . '/credentials/' . $cred->id . '/secret.txt', 'data');
        $att = VaultAttachment::create([
            'workspace_id'        => $ws->id,
            'uploaded_by_user_id' => $owner->id,
            'parent_type'         => 'credential',
            'parent_id'           => $cred->id,
            'filename'            => 'secret.txt',
            'disk'                => 'local',
            'path'                => 'vault/' . $ws->id . '/credentials/' . $cred->id . '/secret.txt',
            'size'                => 4,
            'mime'                => 'text/plain',
        ]);

        $this->actingAs($owner)->delete('/user/vault/credentials/' . $cred->id)->assertRedirect();

        Storage::disk('local')->assertMissing($att->path);
        $this->bindWorkspace($owner);
        $this->assertSame(0, VaultAttachment::where('id', $att->id)->count());
    }

    public function test_decrypt_failure_returns_null_not_exception(): void
    {
        $owner = $this->makeUser('owner');
        $ws = $this->bindWorkspace($owner);
        $cred = VaultCredential::create(['label' => 'broken']);
        // Inject corrupt ciphertext directly to simulate APP_KEY rotation / bad backup.
        $cred->password_encrypted = 'not-a-valid-cipher';
        $cred->saveQuietly();
        $this->assertNull($cred->fresh()->getEncrypted('password'));
    }
}
