<?php

namespace Tests\Feature;

use App\Console\Commands\BackfillImageReoptimization;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\SplashPage;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end test for the `images:backfill-reoptimize` command. Seeds an
 * oversized vault image referenced from each owning model and verifies
 * the command shrinks the underlying UserFile bytes in place.
 */
class BackfillImageReoptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension not available.');
        }
        Storage::fake('user_files');
    }

    private function makeUser(): User
    {
        $user = User::factory()->create([
            'handle'   => 'h' . Str::random(6),
        ]);
        $ws = app(WorkspaceContext::class)->resolve($user);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $user);
        return $user;
    }

    private function bigJpegBytes(int $w = 2400, int $h = 1800): string
    {
        // Smooth horizontal gradient — compresses well at both source and
        // recompressed quality so the downscale+re-encode reliably yields
        // smaller bytes (random / striped fixtures can paradoxically grow
        // because high-freq noise is harder to JPEG at any quality).
        $im = imagecreatetruecolor($w, $h);
        for ($x = 0; $x < $w; $x++) {
            $r = (int) (255 * $x / $w);
            $color = imagecolorallocate($im, $r, 128, 255 - $r);
            imageline($im, $x, 0, $x, $h - 1, $color);
        }
        $tmp = tempnam(sys_get_temp_dir(), 'tjpg_');
        imagejpeg($im, $tmp, 95);
        imagedestroy($im);
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);
        return $bytes;
    }

    private function seedOversizedFile(User $user): UserFile
    {
        $bytes = $this->bigJpegBytes();
        $path  = $user->id . '/images/' . Str::uuid() . '.jpg';
        Storage::disk('user_files')->put($path, $bytes);
        return UserFile::create([
            'user_id'       => $user->id,
            'original_name' => 'old.jpg',
            'filename'      => basename($path),
            'mime_type'     => 'image/jpeg',
            'size_bytes'    => strlen($bytes),
            'type'          => 'image',
            'disk'          => 'user_files',
            'path'          => $path,
        ]);
    }

    public function test_backfill_shrinks_legacy_images_across_all_contexts(): void
    {
        $user = $this->makeUser();

        $bgFile      = $this->seedOversizedFile($user);
        $fallback    = $this->seedOversizedFile($user);
        $slide       = $this->seedOversizedFile($user);
        $biolinkOg   = $this->seedOversizedFile($user);
        $splashOg    = $this->seedOversizedFile($user);
        $formCover   = $this->seedOversizedFile($user);
        $formCard    = $this->seedOversizedFile($user);
        $linkSeo     = $this->seedOversizedFile($user);

        Link::create([
            'user_id'  => $user->id,
            'type'     => 'biolink',
            'alias'    => 'bl' . Str::random(5),
            'long_url' => 'https://example.test',
            'settings' => [
                'biolink' => [
                    'background_image'  => $bgFile->url,
                    'bg_fallback_image' => $fallback->url,
                    'slideshow_images'  => [$slide->url],
                    'og' => ['image_url' => $biolinkOg->url],
                ],
            ],
        ]);

        $sp = new SplashPage();
        $sp->user_id  = $user->id;
        $sp->name     = 'sp' . Str::random(5);
        $sp->title    = 'SP';
        $sp->og_image = $splashOg->url;
        $sp->save();

        $form = new Form();
        $form->user_id = $user->id;
        $form->slug    = 'fm' . Str::random(5);
        $form->title   = 'Form';
        $form->design  = array_merge(Form::defaultDesign(), [
            'cover'      => $formCover->url,
            'card_image' => $formCard->url,
        ]);
        $form->save();

        Link::create([
            'user_id'   => $user->id,
            'type'      => 'url',
            'alias'     => 'ur' . Str::random(5),
            'long_url'  => 'https://example.test',
            'seo_image' => $linkSeo->url,
        ]);

        $beforeSizes = UserFile::query()->withoutGlobalScope('workspace')
            ->pluck('size_bytes', 'id')->all();

        $this->artisan('images:backfill-reoptimize')->assertSuccessful();

        $afterSizes = UserFile::query()->withoutGlobalScope('workspace')
            ->pluck('size_bytes', 'id')->all();

        foreach ([$bgFile, $fallback, $slide, $biolinkOg, $splashOg, $formCover, $formCard, $linkSeo] as $f) {
            $this->assertLessThan(
                $beforeSizes[$f->id],
                $afterSizes[$f->id],
                "Expected UserFile #{$f->id} to be shrunk by the backfill."
            );
        }

        // Re-running should be a no-op (no further shrinkage).
        $stableSizes = $afterSizes;
        $this->artisan('images:backfill-reoptimize')->assertSuccessful();
        $rerunSizes = UserFile::query()->withoutGlobalScope('workspace')
            ->pluck('size_bytes', 'id')->all();
        foreach ($stableSizes as $id => $size) {
            $this->assertSame($size, $rerunSizes[$id]);
        }
    }

    public function test_dry_run_does_not_modify_files(): void
    {
        $user = $this->makeUser();
        $file = $this->seedOversizedFile($user);

        Link::create([
            'user_id'  => $user->id,
            'type'     => 'biolink',
            'alias'    => 'bl' . Str::random(5),
            'long_url' => 'https://example.test',
            'settings' => ['biolink' => ['background_image' => $file->url]],
        ]);

        $before = UserFile::find($file->id)->size_bytes;
        $this->artisan('images:backfill-reoptimize', ['--dry-run' => true])->assertSuccessful();
        $after = UserFile::find($file->id)->size_bytes;

        $this->assertSame($before, $after);
    }

    /**
     * Helper: seed N oversized images referenced as biolink backgrounds so a
     * single run will shrink them all and trip whatever threshold the test
     * configures. Returns the owner User.
     */
    private function seedShrinkable(int $n, string $context = 'biolink_bg'): User
    {
        $user = $this->makeUser();
        for ($i = 0; $i < $n; $i++) {
            $file = $this->seedOversizedFile($user);
            if ($context === 'biolink_bg') {
                Link::create([
                    'user_id'  => $user->id,
                    'type'     => 'biolink',
                    'alias'    => 'bl' . Str::random(6),
                    'long_url' => 'https://example.test',
                    'settings' => ['biolink' => ['background_image' => $file->url]],
                ]);
            } elseif ($context === 'link_seo') {
                Link::create([
                    'user_id'   => $user->id,
                    'type'      => 'url',
                    'alias'     => 'ur' . Str::random(6),
                    'long_url'  => 'https://example.test',
                    'seo_image' => $file->url,
                ]);
            }
        }
        return $user;
    }

    private function makeAdmin(): User
    {
        $u = User::create([
            'name'              => 'A ' . Str::random(4),
            'email'             => 'a' . Str::random(8) . '@ex.com',
            'password'          => Hash::make('x'),
            'status'            => 'active',
            'handle'            => 'h' . Str::random(6),
            'email_verified_at' => now(),
        ]);
        $userAdminRoleId = \Illuminate\Support\Facades\DB::table('roles')
            ->where('slug', 'user-admin')->where('guard', 'web')
            ->value('id');
        if ($userAdminRoleId) {
            $u->roles()->syncWithoutDetaching([$userAdminRoleId]);
            $u->flushPermissionCache();
        }
        return $u;
    }

    public function test_alert_fires_when_shrunk_count_exceeds_threshold(): void
    {
        $admin = $this->makeAdmin();

        // Tight threshold so the seeded fixtures trip the alert without
        // having to fabricate dozens of oversized files.
        AppSetting::put(BackfillImageReoptimization::ALERT_SETTING_KEY, ['threshold' => 2]);

        $this->seedShrinkable(3, 'biolink_bg');

        $this->artisan('images:backfill-reoptimize')->assertSuccessful();

        $note = UserNotification::where('user_id', $admin->id)
            ->where('type', 'image_reoptimize_alert')
            ->first();
        $this->assertNotNull($note, 'Expected an in-app alert for the admin.');
        $data = $note->data ?? [];
        $this->assertGreaterThan(2, (int) ($data['shrunk'] ?? 0));
        $perCtx = (array) ($data['per_context'] ?? []);
        $this->assertArrayHasKey('biolink_bg', $perCtx, 'Per-context breakdown must name the offending upload surface.');
        $this->assertGreaterThan(0, (int) $perCtx['biolink_bg']);
        $this->assertStringContainsString('biolink_bg', (string) ($data['body'] ?? ''));

        // last_sent_at should now be persisted so the cooldown engages.
        $cfg = AppSetting::get(BackfillImageReoptimization::ALERT_SETTING_KEY, []);
        $this->assertNotNull($cfg['last_sent_at'] ?? null);
    }

    public function test_alert_does_not_fire_below_threshold(): void
    {
        $this->makeAdmin();

        AppSetting::put(BackfillImageReoptimization::ALERT_SETTING_KEY, ['threshold' => 50]);

        $this->seedShrinkable(2, 'biolink_bg');

        $this->artisan('images:backfill-reoptimize')->assertSuccessful();

        $this->assertSame(0, UserNotification::where('type', 'image_reoptimize_alert')->count());
        $cfg = AppSetting::get(BackfillImageReoptimization::ALERT_SETTING_KEY, []);
        $this->assertNull($cfg['last_sent_at'] ?? null);
    }

    public function test_suppress_next_eats_one_alert_then_re_arms(): void
    {
        $this->makeAdmin();

        AppSetting::put(BackfillImageReoptimization::ALERT_SETTING_KEY, [
            'threshold'      => 2,
            'suppress_next'  => true,
            'cooldown_hours' => 1,
        ]);

        // First run trips the threshold but should be eaten by suppress_next.
        $this->seedShrinkable(3, 'biolink_bg');
        $this->artisan('images:backfill-reoptimize')->assertSuccessful();

        $this->assertSame(0, UserNotification::where('type', 'image_reoptimize_alert')->count(),
            'suppress_next must eat the first over-threshold alert.');
        $cfg = AppSetting::get(BackfillImageReoptimization::ALERT_SETTING_KEY, []);
        $this->assertFalse((bool) ($cfg['suppress_next'] ?? true), 'suppress_next must auto-clear after consumption.');

        // Second run with new shrinkable files now actually fires.
        $this->seedShrinkable(3, 'link_seo');
        $this->artisan('images:backfill-reoptimize')->assertSuccessful();

        $this->assertSame(1, UserNotification::where('type', 'image_reoptimize_alert')->count(),
            'Second over-threshold run after suppress_next clears must alert.');
    }

    public function test_no_alert_flag_suppresses_dispatch(): void
    {
        $this->makeAdmin();

        AppSetting::put(BackfillImageReoptimization::ALERT_SETTING_KEY, ['threshold' => 2]);

        $this->seedShrinkable(3, 'biolink_bg');

        $this->artisan('images:backfill-reoptimize', ['--no-alert' => true])->assertSuccessful();

        $this->assertSame(0, UserNotification::where('type', 'image_reoptimize_alert')->count());
    }

    public function test_cooldown_suppresses_back_to_back_alerts(): void
    {
        $this->makeAdmin();

        AppSetting::put(BackfillImageReoptimization::ALERT_SETTING_KEY, [
            'threshold'      => 2,
            'cooldown_hours' => 24,
        ]);

        $this->seedShrinkable(3, 'biolink_bg');
        $this->artisan('images:backfill-reoptimize')->assertSuccessful();
        $this->assertSame(1, UserNotification::where('type', 'image_reoptimize_alert')->count());

        // Second run inside cooldown window — must not double-alert even
        // though the threshold is exceeded again.
        $this->seedShrinkable(3, 'link_seo');
        $this->artisan('images:backfill-reoptimize')->assertSuccessful();
        $this->assertSame(1, UserNotification::where('type', 'image_reoptimize_alert')->count());
    }

    public function test_only_filter_limits_contexts(): void
    {
        $user = $this->makeUser();
        $bgFile  = $this->seedOversizedFile($user);
        $seoFile = $this->seedOversizedFile($user);

        Link::create([
            'user_id'  => $user->id,
            'type'     => 'biolink',
            'alias'    => 'bl' . Str::random(5),
            'long_url' => 'https://example.test',
            'settings' => ['biolink' => ['background_image' => $bgFile->url]],
        ]);
        Link::create([
            'user_id'   => $user->id,
            'type'      => 'url',
            'alias'     => 'ur' . Str::random(5),
            'long_url'  => 'https://example.test',
            'seo_image' => $seoFile->url,
        ]);

        $bgBefore  = UserFile::find($bgFile->id)->size_bytes;
        $seoBefore = UserFile::find($seoFile->id)->size_bytes;

        $this->artisan('images:backfill-reoptimize', ['--only' => 'link_seo'])->assertSuccessful();

        $this->assertSame($bgBefore, UserFile::find($bgFile->id)->size_bytes,
            'Biolink background must be untouched when --only=link_seo.');
        $this->assertLessThan($seoBefore, UserFile::find($seoFile->id)->size_bytes);
    }
}
