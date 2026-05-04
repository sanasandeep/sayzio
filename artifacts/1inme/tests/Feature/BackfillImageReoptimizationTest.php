<?php

namespace Tests\Feature;

use App\Modules\User\Models\Form;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\SplashPage;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
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
        $user = User::create([
            'name'     => 'U ' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
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
