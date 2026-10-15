<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Services\Uploads\UploadScanner;
use App\Services\Brand\BrandIconPackage;
use App\Services\Brand\BrandKitPresentation;
use App\Services\Brand\BrandKitShowcase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandPresentationTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $p = Plan::create(['name' => 'Brand', 'slug' => 'brand', 'monthly_price' => 100, 'status' => 'active', 'features' => ['max_brand_kits' => 10, 'max_links' => 100]]);

        return User::factory()->create(['plan_id' => $p->id]);
    }

    public function test_both_themes_are_saved_copied_and_editable_without_changing_blocks(): void
    {
        $user = $this->owner();
        $this->actingAs($user);
        $fields = BrandKitPresentation::defaults() + ['name' => 'Aurora', 'primary' => '#3454ef', 'dark_text' => '#ffffff'];
        $fields['dark_background'] = '#111122';
        $this->post('/user/brand-kits/manual', $fields)->assertRedirect();
        $kit = BrandKit::firstOrFail();
        $link = Link::create(['user_id' => $user->id, 'type' => 'biolink', 'alias' => 'theme-test', 'title' => 'Aurora', 'is_active' => false]);
        app(BrandKitShowcase::class)->seed($link, $kit, ['brand', 'downloads']);
        $link->refresh();
        $this->assertSame('#111122', $link->settings['biolink']['brand_presentation']['dark_background']);
        $count = $link->biolinkBlocks()->count();
        file_put_contents('/tmp/brand-public-theme.html', view('common.biolink', ['link' => $link])->render());
        $this->post('/user/links/'.$link->id.'/brand-page-theme', ['showcase_mode' => 'dark', 'dark_background' => '#222233'])->assertRedirect();
        $this->assertSame('#222233', $link->fresh()->settings['biolink']['brand_presentation']['dark_background']);
        $this->assertSame($count, $link->biolinkBlocks()->count());
        $this->post('/user/links/'.$link->id.'/brand-page-theme', ['dark_background' => 'red;display:none'])->assertSessionHasErrors('dark_background');
        $foreign = Link::create(['user_id' => User::factory()->create()->id, 'type' => 'biolink', 'alias' => 'foreign-theme', 'is_active' => false]);
        $this->post('/user/links/'.$foreign->id.'/brand-page-theme', ['showcase_mode' => 'light'])->assertForbidden();
    }

    public function test_generated_uploads_become_public_page_icons_only_after_publication(): void
    {
        $user = $this->owner();
        $this->actingAs($user);
        Storage::fake('user_files');
        config(['filesystems.disks.user_files.driver' => 's3', 'filesystems.disks.user_files.key' => 'test', 'filesystems.disks.user_files.secret' => 'test', 'filesystems.disks.user_files.bucket' => 'test', 'filesystems.disks.user_files.region' => 'us-east-1']);
        $this->mock(UploadScanner::class, function ($mock) {
            $mock->shouldReceive('scan')->andReturnUsing(function ($file) {
                $file->update(['scan_status' => 'clean']);

                return [];
            });
        });
        $kit = BrandKit::create(['user_id' => $user->id, 'name' => 'Aurora', 'config' => BrandKitPresentation::defaults()]);
        $input = ['fingerprint' => BrandIconPackage::fingerprint($kit)];
        foreach (['main', 'dark', 'apple', 'android', 'maskable'] as $variant) {
            $input[$variant] = UploadedFile::fake()->image($variant.'.png', 512, 512);
        }
        $this->postJson('/user/brand-kits/'.$kit->id.'/icons', $input)->assertOk();
        $kit->refresh();
        $this->assertCount(5, $kit->config['icon_package_files']);
        $link = Link::create(['user_id' => $user->id, 'type' => 'biolink', 'alias' => 'public-icons', 'title' => 'Aurora', 'is_active' => false]);
        app(BrandKitShowcase::class)->seed($link, $kit, ['brand', 'downloads']);
        $link->refresh();
        $base = $link->settings['biolink']['brand_icon_base'];
        $this->get($base.'favicon-32.png')->assertNotFound();
        $link->update(['is_active' => true]);
        auth()->forgetGuards();
        $this->app['session']->flush();
        $this->get($base.'favicon-32.png')->assertOk()->assertHeader('Content-Type', 'image/png');
        $manifest = $this->getJson($base.'site.webmanifest')->assertOk()->json();
        $this->assertStringContainsString('/brand-showcases/'.$link->id.'/', $manifest['icons'][0]['src']);
        $this->assertStringEndsWith('/public-icons', $manifest['start_url']);
        $link->update(['is_active' => false]);
        $this->get($base.'favicon-32.png')->assertNotFound();
    }

    public function test_icon_sizes_manifest_ico_and_stale_generation_detection(): void
    {
        $user = $this->owner();
        $this->actingAs($user);
        Storage::fake('user_files');
        $kit = BrandKit::create(['user_id' => $user->id, 'name' => 'Aurora', 'config' => BrandKitPresentation::defaults()]);
        $image = imagecreatetruecolor(512, 512);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);
        Storage::disk('user_files')->put('icons/source.png', $png);
        $file = UserFile::create(['user_id' => $user->id, 'filename' => 'source.png', 'original_name' => 'source.png', 'path' => 'icons/source.png', 'disk' => 'user_files', 'mime_type' => 'image/png', 'type' => 'image', 'size_bytes' => strlen($png), 'scan_status' => 'clean']);
        $c = $kit->config;
        $c['icon_package_files'] = array_fill_keys(['main', 'dark', 'apple', 'android', 'maskable'], $file->id);
        $c['icon_package_fingerprint'] = BrandIconPackage::fingerprint($kit);
        $kit->update(['config' => $c]);
        $files = app(BrandIconPackage::class)->files($kit);
        foreach ([16, 32, 48, 96, 192, 512] as $size) {
            $info = getimagesizefromstring($files['favicon-'.$size.'.png']);
            $this->assertSame($size, $info[0]);
            $this->assertSame($size, $info[1]);
        }
        $this->assertSame(3, unpack('vreserved/vtype/vcount', substr($files['favicon.ico'], 0, 6))['count']);
        $manifest = json_decode($files['site.webmanifest'], true);
        $this->assertCount(3, $manifest['icons']);
        $this->assertSame('maskable', $manifest['icons'][2]['purpose']);
        $this->assertStringContainsString('prefers-color-scheme: dark', $files['head.html']);
        $this->get('/user/brand-kits/'.$kit->id.'/icons')->assertOk();
        $kit->update(['name' => 'Changed']);
        $this->assertArrayNotHasKey('favicon-32.png', app(BrandIconPackage::class)->files($kit));
        $this->get('/user/brand-kits/'.$kit->id.'/icons')->assertStatus(422);
        $file->update(['scan_status' => 'flagged', 'quarantined_at' => now()]);
        $kit->update(['name' => 'Aurora']);
        $this->assertArrayNotHasKey('favicon-32.png', app(BrandIconPackage::class)->files($kit));
    }
}
