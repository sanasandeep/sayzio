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
use Illuminate\Support\Facades\DB;
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
        $html = $this->get('/user/brand-kits/'.$kit->id.'/edit')->assertOk()->assertSee('Brand identity')->assertSee('Advanced')->getContent();
        file_put_contents('/tmp/brand-editor-fixture.html', $html);
        file_put_contents('/tmp/brand-library-fixture.html', $this->get('/user/brand-kits')->getContent());
    }

    public function test_expanded_manual_fields_versions_and_restore(): void
    {
        $u = $this->user();
        $kit = $this->kit($u);
        $payload = $this->fields() + ['mission' => 'Help small teams', 'dos' => 'Use plain language', 'campaign_disclaimers' => 'Offer valid through October', 'social_banner' => 'https://example.test/banner.png', 'linkedin' => 'https://linkedin.com/company/example', 'approval_status' => 'review'];
        $this->actingAs($u)->put('/user/brand-kits/'.$kit->id, $payload)->assertRedirect();
        $this->assertSame('Help small teams', $kit->fresh()->config['mission']);
        $this->assertSame('review', $kit->fresh()->config['approval_status']);
        $revision = DB::table('brand_kit_revisions')->where('brand_kit_id', $kit->id)->first();
        $this->assertNotNull($revision);
        $this->post('/user/brand-kits/'.$kit->id.'/revisions/'.$revision->id.'/restore')->assertRedirect();
        $this->assertArrayNotHasKey('mission', $kit->fresh()->config);
        $this->assertSame(2, DB::table('brand_kit_revisions')->where('brand_kit_id', $kit->id)->count());
    }

    public function test_delete_detaches_links_and_qrs_without_changing_published_style(): void
    {
        $u = $this->user();
        $kit = $this->kit($u);
        $ws = $u->ensureDefaultWorkspace();
        $link = Link::factory()->create(['user_id' => $u->id, 'workspace_id' => $ws->id, 'type' => 'biolink']);
        $qr = QrCode::create(['user_id' => $u->id, 'workspace_id' => $ws->id, 'name' => 'Brand QR', 'type' => 'url', 'payload' => ['url' => 'https://example.test'], 'design' => []]);
        $qr->forceFill(['workspace_id' => $ws->id])->save();
        $this->actingAs($u)->get('/user/brand-kits')->assertOk()->assertSee('Delete permanently');
        app(BrandKitAssignments::class)->assign($kit, $link);
        app(BrandKitAssignments::class)->assignQr($kit, $qr);
        $before = $link->fresh()->settings['biolink'];
        $qrBefore = $qr->fresh()->design;
        unset($qrBefore['brand_identity']);
        $this->delete('/user/brand-kits/'.$kit->id)->assertRedirect('/user/brand-kits');
        $this->assertNull(BrandKit::find($kit->id));
        $this->assertSame($before, $link->fresh()->settings['biolink']);
        $this->assertArrayNotHasKey('brand_identity', $link->fresh()->settings);
        $this->assertSame($qrBefore, $qr->fresh()->design);
    }

    public function test_exports_use_saved_fields_and_do_not_fetch_external_assets(): void
    {
        $u = $this->user();
        $kit = $this->kit($u);
        $kit->update(['config' => $kit->config + ['mission' => 'Serve small teams', 'logo' => 'http://169.254.169.254/private.png']]);
        $this->actingAs($u)->get('/user/brand-kits/'.$kit->id.'/export/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $response = $this->get('/user/brand-kits/'.$kit->id.'/export/zip')->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path));
        $this->assertStringContainsString('Serve small teams', $zip->getFromName('brand-kit.json'));
        $this->assertStringContainsString('"included": false', $zip->getFromName('asset-manifest.json'));
        $this->assertStringStartsWith('%PDF', $zip->getFromName('brand-guide.pdf'));
        $zip->close();
        file_put_contents('/tmp/brand-guide-fixture.pdf', $this->get('/user/brand-kits/'.$kit->id.'/export/pdf')->getContent());
    }

    public function test_creation_applies_selected_kit_and_rejects_foreign_kit(): void
    {
        $u=$this->user(); $u->plan->update(['features'=>['max_brand_kits'=>10,'max_links'=>100,'max_qr_codes'=>100]]);
        $kit=$this->kit($u);
        $this->actingAs($u)->post('/user/links',['type'=>'biolink','alias'=>'brandstudio-test','title'=>'Branded page','brand_kit_id'=>$kit->id])->assertRedirect();
        $link=Link::where('user_id',$u->id)->where('alias','brandstudio-test')->firstOrFail();
        $this->assertEquals($kit->id,$link->settings['brand_identity']['kit_id']);
        $this->assertSame('#3454ef',$link->settings['biolink']['button_color']);
        $this->post('/user/qr-codes',['type'=>'url','name'=>'Branded QR','payload'=>['url'=>'https://example.test'],'brand_kit_id'=>$kit->id])->assertRedirect();
        $qr=QrCode::where('user_id',$u->id)->where('name','Branded QR')->firstOrFail();
        $this->assertEquals($kit->id,$qr->design['brand_identity']['kit_id']);
        $foreign=BrandKit::create(['user_id'=>User::factory()->create()->id,'name'=>'Private','config'=>[]]);
        $this->post('/user/links',['type'=>'biolink','alias'=>'not-created-test','brand_kit_id'=>$foreign->id])->assertNotFound();
        $this->assertFalse(Link::where('alias','not-created-test')->exists());
        $this->get('/user/brand-kits/'.$foreign->id.'/export/pdf')->assertForbidden();
    }

    public function test_zip_includes_only_owned_clean_library_files(): void
    {
        $u=$this->user(); $kit=$this->kit($u); $this->actingAs($u)->get('/user/brand-kits')->assertOk();
        \Illuminate\Support\Facades\Storage::fake('user_files');
        $owned=\App\Modules\User\Models\UserFile::create(['user_id'=>$u->id,'filename'=>'letterhead.pdf','original_name'=>'letterhead.pdf','path'=>'owned.pdf','disk'=>'user_files','mime_type'=>'application/pdf','size_bytes'=>12,'type'=>'document','scan_status'=>'clean']);
        \Illuminate\Support\Facades\Storage::disk('user_files')->put('owned.pdf','owned-design');
        $foreign=\App\Modules\User\Models\UserFile::create(['user_id'=>User::factory()->create()->id,'filename'=>'private.pdf','original_name'=>'private.pdf','path'=>'private.pdf','disk'=>'user_files','mime_type'=>'application/pdf','size_bytes'=>12,'type'=>'document','scan_status'=>'clean']);
        $kit->update(['config'=>$kit->config+['letterhead'=>$owned->url,'business_card'=>$foreign->url]]);
        $response=$this->get('/user/brand-kits/'.$kit->id.'/export/zip')->assertOk();
        $zip=new \ZipArchive(); $zip->open($response->baseResponse->getFile()->getPathname());
        $this->assertSame('owned-design',$zip->getFromName('assets/letterhead-'.$owned->id.'.pdf'));
        $this->assertFalse($zip->getFromName('assets/business-card-'.$foreign->id.'.pdf')); $zip->close();
    }

    public function test_review_estimate_supports_guidelines_without_saving(): void
    {
        $u = $this->user();
        AiEngineSettings::setEnabled(true);
        $mock = \Mockery::mock(OpenAiService::class);
        $mock->shouldReceive('estimateChatCoins')->once()->andReturn(12);
        $mock->shouldReceive('chat')->once()->andReturn(['content' => '{"name":"Review","palette":{"primary":"#123456"},"dos":"Use plain language","mission":"Help teams","review_notes":"The voice needs clearer writing examples."}', 'credits_spent' => 5]);
        $this->app->instance(OpenAiService::class, $mock);
        $p = ['brief' => 'Suggest clearer guidelines for a small studio', 'components' => ['guidelines', 'identity']];
        $quote = $this->actingAs($u)->postJson('/user/brand-kits/draft', $p)->assertOk()->json('quote');
        $this->postJson('/user/brand-kits/draft', $p + ['quote' => $quote])->assertOk()->assertJsonPath('draft.config.dos', 'Use plain language')->assertJsonPath('draft.config.mission', 'Help teams')->assertJsonPath('draft.config.review_notes', 'The voice needs clearer writing examples.');
        $this->assertSame(0, BrandKit::count());
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
