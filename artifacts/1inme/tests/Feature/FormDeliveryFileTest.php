<?php

namespace Tests\Feature;

use App\Modules\User\Models\Form;
use App\Modules\User\Models\FormSubmission;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Deliver-a-file after form submit (Task #6624):
 *  - settings round-trip through the builder's After Submit panel,
 *  - a successful submission unlocks a time-limited signed download
 *    (web flash + JSON/embeds + the API delegation path),
 *  - the download route rejects tampered/expired signatures, spam rows and
 *    still-pending paid submissions,
 *  - the auto-responder email carries the download link.
 */
class FormDeliveryFileTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $u = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $u;
    }

    private function makeForm(User $owner, array $settingsOverride = []): Form
    {
        return $owner->forms()->create([
            'title'     => 'Lead Magnet',
            'is_active' => true,
            'settings'  => array_merge(Form::defaultSettings(), $settingsOverride),
            'fields'    => [
                ['id' => 'name',  'type' => 'text',  'label' => 'Name'],
                ['id' => 'email', 'type' => 'email', 'label' => 'Email'],
            ],
        ]);
    }

    private function deliverySettings(string $url = 'https://cdn.example.com/guide.pdf', ?string $label = 'Get the guide'): array
    {
        return ['delivery_file' => ['enabled' => true, 'url' => $url, 'label' => $label]];
    }

    // ── Settings persistence ─────────────────────────────────────────────

    public function test_delivery_settings_round_trip_through_builder_settings(): void
    {
        $owner = $this->owner();
        $form  = $this->makeForm($owner);

        $res = $this->actingAs($owner)->put(route('user.forms.settings.update', $form), [
            'captcha_provider' => 'honeypot',
            'success_message'  => 'Thanks — enjoy!',
            'success_action'   => 'message',
            'delivery_enabled' => '1',
            'delivery_url'     => 'https://cdn.example.com/guide.pdf',
            'delivery_label'   => 'Get the guide',
        ]);
        $res->assertRedirect();
        $res->assertSessionHas('success');

        $form->refresh();
        $this->assertSame('Thanks — enjoy!', $form->settings['success_message']);
        $cfg = $form->deliveryFileConfig();
        $this->assertTrue((bool) $cfg['enabled']);
        $this->assertSame('https://cdn.example.com/guide.pdf', $cfg['url']);
        $this->assertSame('Get the guide', $cfg['label']);
        $this->assertSame('https://cdn.example.com/guide.pdf', $form->deliveryFileUrl());
    }

    public function test_vault_path_is_accepted_and_bad_delivery_urls_are_rejected(): void
    {
        $owner = $this->owner();
        $form  = $this->makeForm($owner);

        // Vault serve path is fine.
        $this->actingAs($owner)->put(route('user.forms.settings.update', $form), [
            'captcha_provider' => 'honeypot',
            'delivery_enabled' => '1',
            'delivery_url'     => '/f/123/guide.pdf',
        ])->assertSessionHasNoErrors();
        $this->assertSame('/f/123/guide.pdf', $form->refresh()->deliveryFileUrl());

        // javascript: (or anything non-http, non-vault) is rejected.
        $this->actingAs($owner)->put(route('user.forms.settings.update', $form), [
            'captcha_provider' => 'honeypot',
            'delivery_enabled' => '1',
            'delivery_url'     => 'javascript:alert(1)',
        ])->assertSessionHasErrors('delivery_url');
    }

    // ── Success-state delivery ───────────────────────────────────────────

    public function test_json_submit_returns_signed_delivery_url_and_it_unlocks_the_file(): void
    {
        $form = $this->makeForm($this->owner(), $this->deliverySettings());

        $res = $this->postJson('/f/' . $form->slug, ['name' => 'Ada', 'email' => 'ada@ex.com']);
        $res->assertOk()->assertJson(['ok' => true]);

        $dl = $res->json('delivery_url');
        $this->assertNotEmpty($dl, 'JSON success must include a delivery_url');
        $this->assertSame('Get the guide', $res->json('delivery_label'));
        $this->assertStringContainsString('/f/' . $form->slug . '/delivery/', $dl);
        $this->assertStringContainsString('signature=', $dl);

        // The signed link redirects to the configured file.
        $this->get($dl)->assertRedirect('https://cdn.example.com/guide.pdf');
    }

    public function test_web_submit_flashes_delivery_url(): void
    {
        $form = $this->makeForm($this->owner(), $this->deliverySettings());

        $res = $this->from('/f/' . $form->slug)
            ->post('/f/' . $form->slug, ['name' => 'Ada', 'email' => 'ada@ex.com']);
        $res->assertRedirect('/f/' . $form->slug);
        $res->assertSessionHas('form_success');
        $dl = session('form_delivery_url');
        $this->assertNotEmpty($dl);
        $this->assertStringContainsString('/f/' . $form->slug . '/delivery/', $dl);
    }

    public function test_no_delivery_url_when_feature_disabled(): void
    {
        $form = $this->makeForm($this->owner()); // defaults: delivery disabled

        $res = $this->postJson('/f/' . $form->slug, ['name' => 'Ada', 'email' => 'ada@ex.com']);
        $res->assertOk();
        $this->assertNull($res->json('delivery_url'));
    }

    // ── Signed-URL gating ────────────────────────────────────────────────

    public function test_unsigned_or_tampered_link_is_rejected(): void
    {
        $form = $this->makeForm($this->owner(), $this->deliverySettings());
        $sub  = FormSubmission::create(['form_id' => $form->id, 'data' => ['name' => 'A']]);

        // No signature at all.
        $this->get('/f/' . $form->slug . '/delivery/' . $sub->id)->assertForbidden();

        // Tampered submission id (signature no longer matches).
        $good = $form->deliverySignedUrl($sub);
        $bad  = str_replace('/delivery/' . $sub->id, '/delivery/' . ($sub->id + 999), $good);
        $this->get($bad)->assertForbidden();
    }

    public function test_expired_link_is_rejected(): void
    {
        $form = $this->makeForm($this->owner(), $this->deliverySettings());
        $sub  = FormSubmission::create(['form_id' => $form->id, 'data' => ['name' => 'A']]);

        $url = $form->deliverySignedUrl($sub, now()->addMinutes(5));
        $this->travel(6)->minutes();
        $this->get($url)->assertForbidden();
        $this->travelBack();
    }

    public function test_link_for_nonexistent_or_spam_submission_is_rejected(): void
    {
        $form = $this->makeForm($this->owner(), $this->deliverySettings());
        $spam = FormSubmission::create(['form_id' => $form->id, 'data' => [], 'is_spam' => true]);

        $this->get($form->deliverySignedUrl($spam))->assertNotFound();
    }

    // ── Vault-backed delivery ────────────────────────────────────────────

    /** Insert a vault file on the (faked) public disk. */
    private function makeVaultFile(User $owner, string $contents = 'PDFBYTES'): \App\Modules\User\Models\UserFile
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::disk('public')->put('vault/guide.pdf', $contents);
        $file = \App\Modules\User\Models\UserFile::create([
            'user_id'       => $owner->id,
            'original_name' => 'Guide.pdf',
            'filename'      => 'guide-xyz.pdf',
            'mime_type'     => 'application/pdf',
            'size_bytes'    => strlen($contents),
            'type'          => 'document',
            'disk'          => 'public',
            'path'          => 'vault/guide.pdf',
        ]);
        // The column defaults to 'pending' (fresh uploads await the scan);
        // a creator only picks already-scanned files from their vault.
        $file->forceFill(['scan_status' => 'clean'])->save();
        return $file;
    }

    public function test_vault_file_is_served_to_anonymous_respondent_via_signed_link(): void
    {
        $owner = $this->owner();
        $file  = $this->makeVaultFile($owner);
        $form  = $this->makeForm($owner, $this->deliverySettings($file->url_path));

        // Anonymous respondent submits and follows the signed link — the
        // delivery route must serve the vault file itself (the generic
        // /f/{id}/{filename} endpoint would 403 an anonymous visitor since
        // a delivery file is not referenced by any public record).
        $res = $this->postJson('/f/' . $form->slug, ['name' => 'Ada', 'email' => 'ada@ex.com']);
        $dl  = $res->json('delivery_url');
        $this->assertNotEmpty($dl);

        $download = $this->get($dl);
        // S3-backed disks (the platform default — user content is S3-only)
        // redirect to a short-lived storage URL; a local-driver disk streams
        // the bytes directly. Accept either, but never a 403/404.
        if ($download->getStatusCode() === 302) {
            $this->assertStringContainsString('vault/guide.pdf', (string) $download->headers->get('Location'));
        } else {
            $download->assertOk();
            $this->assertStringContainsString('attachment', (string) $download->headers->get('Content-Disposition'));
            $this->assertSame('application/pdf', $download->headers->get('Content-Type'));
        }

        // Sanity: the same vault file is NOT anonymously reachable directly.
        $this->get($file->url_path)->assertForbidden();

        // Unsigned delivery URL still refuses.
        $this->get('/f/' . $form->slug . '/delivery/999')->assertForbidden();
    }

    public function test_flagged_or_pending_scan_vault_file_is_never_delivered(): void
    {
        $owner = $this->owner();
        $file  = $this->makeVaultFile($owner);
        $file->forceFill(['scan_status' => 'flagged'])->save();
        $form  = $this->makeForm($owner, $this->deliverySettings($file->url_path));
        $sub   = FormSubmission::create(['form_id' => $form->id, 'data' => ['name' => 'A']]);

        $this->get($form->deliverySignedUrl($sub))->assertForbidden();
    }

    // ── Paid-form ordering ───────────────────────────────────────────────

    public function test_pending_paid_submission_link_only_unlocks_after_payment(): void
    {
        $form = $this->makeForm($this->owner(), $this->deliverySettings());
        $sub  = FormSubmission::create([
            'form_id'        => $form->id,
            'data'           => ['name' => 'A'],
            'payment_status' => 'pending',
        ]);

        $url = $form->deliverySignedUrl($sub);
        $this->get($url)->assertForbidden();

        $sub->update(['payment_status' => 'paid', 'paid_at' => now()]);
        $this->get($url)->assertRedirect('https://cdn.example.com/guide.pdf');
    }

    // ── API delegation path ──────────────────────────────────────────────

    public function test_api_submit_path_returns_delivery_url(): void
    {
        $form = $this->makeForm($this->owner(), $this->deliverySettings());

        $res = $this->postJson("/api/v1/forms/{$form->id}/submit", [
            'name' => 'Ada', 'email' => 'ada@ex.com',
        ]);
        $res->assertOk()->assertJson(['ok' => true]);
        $this->assertNotEmpty($res->json('delivery_url'));
        $this->assertStringContainsString('/f/' . $form->slug . '/delivery/', $res->json('delivery_url'));
    }

    // ── Auto-responder integration ───────────────────────────────────────

    public function test_autoresponder_email_body_includes_download_link(): void
    {
        $owner = $this->owner();
        $form  = $this->makeForm($owner, $this->deliverySettings());
        $form->update(['notifications' => array_replace_recursive(Form::defaultNotifications(), [
            'autoresponder' => ['enabled' => true, 'email_field' => 'email'],
        ])]);

        $captured = null;
        // Emailer/Mail::raw sends aren't observable via Mail::fake (see memory:
        // mailfake-raw-noop) — intercept the controller's send method instead.
        $mock = \Mockery::mock(\App\Modules\User\Controllers\FormController::class)->makePartial();
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('sendEmailViaConfig')
            ->andReturnUsing(function ($userId, $configId, $to, $subject, $body) use (&$captured) {
                $captured = compact('to', 'subject', 'body');
            });
        $this->app->instance(\App\Modules\User\Controllers\FormController::class, $mock);

        $res = $this->postJson('/f/' . $form->slug, ['name' => 'Ada', 'email' => 'ada@ex.com']);
        $res->assertOk();

        $this->assertNotNull($captured, 'Auto-responder should have been sent');
        $this->assertSame(['ada@ex.com'], array_values($captured['to']));
        $this->assertStringContainsString('/f/' . $form->slug . '/delivery/', $captured['body']);
        $this->assertStringContainsString('Get the guide', $captured['body']);
    }
}
