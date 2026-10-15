<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\QrCode;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\OpenAiService;
use App\Services\Brand\BrandKitAssignments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandKitLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $p = Plan::create(['name' => 'Brand', 'slug' => 'brand-paid', 'monthly_price' => 100, 'annual_price' => 1000, 'status' => 'active', 'features' => ['max_brand_kits' => 10]]);

        return User::factory()->create(['plan_id' => $p->id]);
    }

    private function fields(): array
    {
        return ['name' => 'Aurora', 'primary' => '#3454ef', 'secondary' => '#8b5cf6', 'accent' => '#10b981', 'body' => 'Inter', 'heading' => 'Poppins'];
    }

    private function kit(User $u): BrandKit
    {
        return BrandKit::create(['user_id' => $u->id, 'name' => 'Aurora', 'config' => ['palette' => ['primary' => '#3454ef', 'neutrals' => ['#ffffff', '#111111']], 'fonts' => ['body' => 'Inter', 'heading' => 'Poppins']]]);
    }

    public function test_manual_editor_library_and_no_audit(): void
    {
        $u = $this->user();
        $this->actingAs($u)->get('/user/brand-kits/create')->assertOk()->assertSee('Enter details myself')->assertSee('Estimate coins');
        $this->post('/user/brand-kits/manual', $this->fields())->assertRedirect();
        $kit = BrandKit::first();
        $this->assertNull(BrandKit::defaultFor($u->id));
        $this->get('/user/brand-kits')->assertOk()->assertSee('Aurora')->assertDontSee('Brand Consistency Score')->assertDontSee('Off-brand');
        $html = $this->get('/user/brand-kits/'.$kit->id.'/edit')->assertOk()->assertSee('More info')->assertSee('Advanced')->getContent();
        file_put_contents('/tmp/brand-editor-fixture.html', $html);
        file_put_contents('/tmp/brand-library-fixture.html', $this->get('/user/brand-kits')->getContent());
    }

    public function test_assignment_preview_is_read_only_and_publish_preserves_overrides(): void
    {
        $u = $this->user();
        $kit = $this->kit($u);
        $ws = $u->ensureDefaultWorkspace();
        $link = Link::factory()->create(['user_id' => $u->id, 'workspace_id' => $ws->id, 'type' => 'biolink', 'settings' => ['biolink' => ['button_color' => '#ff0000']]]);
        $this->actingAs($u)->postJson('/user/links/'.$link->id.'/brand-identity', ['kit_id' => $kit->id, 'mode' => 'follow', 'preview' => true])->assertOk();
        $this->assertNull($link->fresh()->settings['brand_identity'] ?? null);
        $this->post('/user/links/'.$link->id.'/brand-identity', ['kit_id' => $kit->id, 'mode' => 'custom', 'overrides' => ['button_color']])->assertRedirect();
        $this->assertSame('#ff0000', $link->fresh()->settings['biolink']['button_color']);
        $this->assertSame($kit->id, $link->fresh()->settings['brand_identity']['kit_id']);
        $c = $kit->config;
        $c['palette']['primary'] = '#00ff00';
        $kit->update(['config' => $c]);
        $this->post('/user/brand-kits/'.$kit->id.'/publish', ['link_ids' => [$link->id]])->assertRedirect();
        $this->assertSame('#ff0000', $link->fresh()->settings['biolink']['button_color']);
        $this->post('/user/links/'.$link->id.'/brand-identity', ['kit_id' => $kit->id, 'mode' => 'follow'])->assertRedirect();
        $this->assertSame('#00ff00', $link->fresh()->settings['biolink']['button_color']);
        file_put_contents('/tmp/brand-assignment-fixture.html', $this->get('/user/brand-kits/'.$kit->id.'/edit')->getContent());
        $this->post('/user/links/'.$link->id.'/brand-identity', ['mode' => 'detach'])->assertRedirect();
        $this->assertNull($link->fresh()->settings['brand_identity'] ?? null);
        $this->assertSame('#00ff00', $link->fresh()->settings['biolink']['button_color']);
    }

    public function test_workspace_separation_applies_even_to_admin_actor(): void
    {
        $u = $this->user();
        $kit = $this->kit($u);
        $kit->update(['name' => 'Private workspace kit XYZ']);
        $u->update(['role' => 'admin']);
        $other = Workspace::create(['owner_user_id' => $u->id, 'name' => 'Other', 'slug' => 'other', 'is_personal' => false]);
        $this->actingAs($u)->withSession(['active_workspace_id' => $other->id])->get('/user/brand-kits')->assertOk()->assertDontSee('Private workspace kit XYZ');
        $this->get('/user/brand-kits/'.$kit->id.'/edit')->assertNotFound();
    }

    public function test_ai_estimate_and_draft_do_not_save_a_kit(): void
    {
        $u = $this->user();
        AiEngineSettings::setEnabled(true);
        $mock = \Mockery::mock(OpenAiService::class);
        $mock->shouldReceive('estimateChatCoins')->once()->andReturn(12);
        $mock->shouldReceive('chat')->once()->andReturn(['content' => '{"name":"AI draft","palette":{"primary":"#123456"},"fonts":{"body":"Inter","heading":"Poppins"}}', 'credits_spent' => 5]);
        $this->app->instance(OpenAiService::class, $mock);
        $payload = ['brief' => 'A friendly design studio', 'components' => ['palette', 'fonts']];
        $quote = $this->actingAs($u)->postJson('/user/brand-kits/draft', $payload)->assertOk()->json('quote');
        $this->assertSame(0, BrandKit::count());
        $this->postJson('/user/brand-kits/draft', $payload + ['quote' => $quote])->assertOk()->assertJsonPath('draft.name', 'AI draft');
        $this->assertSame(0, BrandKit::count());
        $this->postJson('/user/brand-kits/draft', $payload + ['quote' => $quote])->assertUnprocessable();
    }

    public function test_saving_kit_does_not_change_assigned_link(): void
    {
        $u = $this->user();
        $kit = $this->kit($u);
        $link = Link::factory()->create(['user_id' => $u->id, 'workspace_id' => $u->ensureDefaultWorkspace()->id, 'type' => 'biolink']);
        app(BrandKitAssignments::class)->assign($kit, $link);
        $this->actingAs($u)->put('/user/brand-kits/'.$kit->id, $this->fields() + ['bio' => 'New bio'])->assertRedirect();
        $this->assertSame('#3454ef', $link->fresh()->settings['biolink']['button_color']);
        $this->get('/user/links/'.$link->id.'/settings/appearance')->assertOk()->assertSee('Follow Brand Kit');
    }

    public function test_qr_assignment_is_visible_from_both_editors_and_keeps_custom_colors(): void
    {
        $u = $this->user();
        $kit = $this->kit($u);
        $qr = QrCode::create(['user_id' => $u->id, 'name' => 'QR example', 'type' => 'url', 'payload' => ['url' => 'https://example.com'], 'design' => ['fg_color' => '#ff0000']]);
        $qr->forceFill(['workspace_id' => $u->ensureDefaultWorkspace()->id])->save();
        $this->actingAs($u)->post('/user/qr-codes/'.$qr->id.'/brand-identity', ['kit_id' => $kit->id, 'mode' => 'custom', 'overrides' => ['fg_color']])->assertRedirect();
        $this->assertSame('#ff0000', $qr->fresh()->design['fg_color']);
        $this->get('/user/brand-kits/'.$kit->id.'/edit')->assertOk()->assertSee('QR example');
        $this->get('/user/qr-codes/'.$qr->id.'/edit')->assertOk()->assertSee('Edit shared Brand Kit');
        $this->post('/user/brand-kits/'.$kit->id.'/publish', ['qr_ids' => [$qr->id]])->assertRedirect();
        $this->assertSame('#ff0000', $qr->fresh()->design['fg_color']);
    }

    public function test_foreign_kit_cannot_be_assigned_or_edited(): void
    {
        $u = $this->user();
        $other = User::factory()->create();
        $kit = $this->kit($other);
        $link = Link::factory()->create(['user_id' => $u->id, 'workspace_id' => $u->ensureDefaultWorkspace()->id, 'type' => 'biolink']);
        $this->actingAs($u)->postJson('/user/links/'.$link->id.'/brand-identity', ['kit_id' => $kit->id, 'mode' => 'follow'])->assertNotFound();
        $this->put('/user/brand-kits/'.$kit->id, $this->fields())->assertNotFound();
    }

    public function test_archive_duplicate_and_legacy_source_are_safe(): void
    {
        $u = $this->user();
        $kit = $this->kit($u);
        $c = $kit->config;
        $c['source'] = ['type' => 'prompt', 'value' => 'A calm studio'];
        $kit->update(['config' => $c]);
        $this->assertSame('', $kit->logo());
        $this->actingAs($u)->post('/user/brand-kits/'.$kit->id.'/archive')->assertRedirect();
        $this->assertNotEmpty($kit->fresh()->config['archived_at']);
        $this->post('/user/brand-kits/'.$kit->id.'/duplicate')->assertRedirect();
        $copy = BrandKit::latest('id')->first();
        $this->assertNotSame($kit->id, $copy->id);
        $this->assertEmpty($copy->config['archived_at'] ?? null);
        $this->get('/user/brand-kits')->assertOk();
    }
}
