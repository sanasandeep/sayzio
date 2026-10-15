<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Services\Brand\BrandKitShowcase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandKitShowcaseTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $plan = Plan::create(['name' => 'Brand', 'slug' => 'brand', 'monthly_price' => 100, 'status' => 'active', 'features' => ['max_brand_kits' => 10, 'max_links' => 100]]);

        return User::factory()->create(['plan_id' => $plan->id]);
    }

    private function kit(User $user): BrandKit
    {
        return BrandKit::create(['user_id' => $user->id, 'name' => 'Aurora Studio', 'config' => ['palette' => ['primary' => '#3454ef', 'secondary' => '#7c3aed', 'accent' => '#10b981'], 'fonts' => ['body' => 'Inter', 'heading' => 'Poppins', 'internal_prompt' => 'NESTED-PRIVATE-XYZ'], 'bio' => 'Thoughtful design for small teams.', 'logo' => 'https://example.test/logo.png', 'letterhead' => 'https://example.test/letterhead.pdf', 'dos' => 'Use plain language.', 'instructions' => 'INTERNAL-SECRET-XYZ', 'campaign_restrictions' => 'PRIVATE-CAMPAIGN-XYZ']]);
    }

    public function test_preview_uses_regular_renderer_without_saving_or_exposing_internal_fields(): void
    {
        $user = $this->owner();
        $kit = $this->kit($user);
        $result = $this->actingAs($user)->postJson('/user/brand-kits/showcase-preview', ['kit_id' => $kit->id, 'form' => ['name' => 'Live draft studio', 'logo' => 'https://example.test/logo.png', 'secondary' => '#7c3aed', 'accent' => '#10b981', 'primary' => '#3454ef', 'body' => 'Inter', 'heading' => 'Poppins', 'bio' => 'Live draft description', 'instructions' => 'INTERNAL-SECRET-XYZ', 'campaign_restrictions' => 'PRIVATE-CAMPAIGN-XYZ', 'letterhead' => 'https://example.test/letterhead.pdf'], 'sections' => BrandKitShowcase::DEFAULTS])->assertOk();
        $html = $result->json('html');
        $this->assertStringContainsString('Live draft studio', $html);
        $this->assertStringContainsString('Download Letterhead', $html);
        $this->assertStringContainsString('Download all brand assets', $html);
        $this->assertStringNotContainsString('INTERNAL-SECRET-XYZ', $html);
        $this->assertStringNotContainsString('PRIVATE-CAMPAIGN-XYZ', $html);
        $this->assertSame(0, Link::count());
        $this->assertSame(0, BiolinkBlock::count());
        file_put_contents('/tmp/brand-showcase-preview.html', $html);
        file_put_contents('/tmp/brand-showcase-editor.html', $this->get('/user/brand-kits/'.$kit->id.'/edit')->assertOk()->getContent());
    }

    public function test_creation_seeds_real_editable_blocks_and_public_downloads_are_filtered_and_revocable(): void
    {
        $user = $this->owner();
        $kit = $this->kit($user);
        $this->actingAs($user)->post('/user/links', ['type' => 'biolink', 'title' => 'Aurora resources', 'alias' => 'aurora-brand-kit', 'brand_kit_id' => $kit->id, 'brand_showcase' => 1, 'showcase_sections' => BrandKitShowcase::DEFAULTS])->assertRedirect();
        $link = Link::where('alias', 'aurora-brand-kit')->firstOrFail();
        $this->assertFalse((bool) $link->is_active);
        $blocks = $link->biolinkBlocks()->orderBy('sort_order')->get();
        $this->assertGreaterThan(5, $blocks->count());
        $this->assertContains('Download Letterhead', $blocks->pluck('settings.text')->all());
        $this->assertFalse($link->isUntouchedStarterPage());
        $this->assertStringNotContainsString('INTERNAL-SECRET-XYZ', json_encode($link->settings['brand_showcase']));
        $this->assertStringNotContainsString('NESTED-PRIVATE-XYZ', json_encode($link->settings['brand_showcase']));
        $token = $link->settings['brand_showcase']['token'];
        $url = '/brand-showcases/'.$link->id.'/'.$token.'/pdf';
        $this->get($url)->assertNotFound();
        $link->update(['is_active' => true, 'visibility' => 'public']);
        auth()->forgetGuards();
        $this->assertGuest();
        $this->app->forgetInstance('current_workspace');
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $response = $this->get('/brand-showcases/'.$link->id.'/'.$token.'/zip')->assertOk();
        $zip = new \ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $config = $zip->getFromName('brand-kit.json');
        $this->assertStringNotContainsString('INTERNAL-SECRET-XYZ', $config);
        $this->assertStringNotContainsString('PRIVATE-CAMPAIGN-XYZ', $config);
        $zip->close();
        $blocks->firstWhere('type', 'heading')->update(['settings' => ['text' => 'Edited in normal block editor']]);
        $this->assertTrue($link->biolinkBlocks()->where('settings->text', 'Edited in normal block editor')->exists());
        $link->update(['is_active' => false]);
        $this->get($url)->assertNotFound();
    }

    public function test_sections_and_foreign_assets_are_excluded_from_public_snapshot(): void
    {
        $user = $this->owner();
        $kit = $this->kit($user);
        $this->actingAs($user)->get('/user/brand-kits');
        $snapshot = app(BrandKitShowcase::class)->snapshot($kit, ['brand', 'downloads']);
        $this->assertSame([], $snapshot['assets']);
        $this->assertArrayNotHasKey('dos', $snapshot['config']);
        $this->assertArrayNotHasKey('letterhead', $snapshot['config']);
        $foreign = $this->kit(User::factory()->create());
        $this->postJson('/user/brand-kits/showcase-preview', ['kit_id' => $foreign->id, 'form' => ['name' => 'Test'], 'sections' => ['brand']])->assertNotFound();
    }
}
