<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Verifies the header-photo upload pipeline downscales oversized
 * raster images server-side, and that existing oversized vault
 * files are lazily re-optimized when the user next saves the header.
 */
class ResumeHeaderPhotoCompressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension not available.');
        }
    }

    private function makeUser(): User
    {
        $user = User::factory()->create([
            'handle'   => 'h' . Str::random(6),
        ]);
        // Bind the workspace so models with the BelongsToWorkspace
        // global scope (UserFile) are visible inside this test.
        $ws = app(WorkspaceContext::class)->resolve($user);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $user);
        return $user;
    }

    /** Generate a real JPEG bigger than the 800x800 cap. */
    private function jpegUpload(int $w = 2000, int $h = 1500): UploadedFile
    {
        $im = imagecreatetruecolor($w, $h);
        // Fill with random colored stripes so the JPEG isn't trivially small.
        for ($x = 0; $x < $w; $x += 8) {
            $color = imagecolorallocate($im, ($x * 7) % 255, ($x * 13) % 255, ($x * 19) % 255);
            imagefilledrectangle($im, $x, 0, $x + 7, $h, $color);
        }
        $tmp = tempnam(sys_get_temp_dir(), 'tjpg_');
        imagejpeg($im, $tmp, 95);
        imagedestroy($im);
        return new UploadedFile($tmp, 'big.jpg', 'image/jpeg', null, true);
    }

    public function test_uploaded_header_photo_is_downscaled(): void
    {
        Storage::fake('user_files');
        $user = $this->makeUser();

        $file = $this->jpegUpload(2000, 1500);
        $originalBytes = filesize($file->getRealPath());

        $res = $this->actingAs($user)
            ->post('/user/resume/header/photo', ['photo' => $file]);

        $res->assertOk();

        $stored = UserFile::where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($stored, 'A UserFile row should be created.');

        // Stored bytes must be smaller than the original camera dump.
        $this->assertLessThan($originalBytes, $stored->size_bytes);

        // And the actual stored image must fit within the 800x800 cap.
        $bytes = Storage::disk('user_files')->get($stored->path);
        [$w, $h] = getimagesizefromstring($bytes);
        $this->assertLessThanOrEqual(800, $w);
        $this->assertLessThanOrEqual(800, $h);
        $this->assertGreaterThan(0, $w);
    }

    public function test_lazy_reoptimize_preserves_exif_orientation(): void
    {
        if (!function_exists('exif_read_data')) {
            $this->markTestSkipped('exif extension not available.');
        }
        Storage::fake('user_files');
        $user = $this->makeUser();

        // Build a 2000x1500 JPEG whose top-left corner is a distinctive
        // red square, then rewrite it with EXIF Orientation=6 (rotate
        // CW 90° on display). After lazy reoptimize, the red square
        // must still be in the visually-correct corner — i.e. the
        // saved bytes must have rotation baked into pixels.
        $im = imagecreatetruecolor(2000, 1500);
        $red   = imagecolorallocate($im, 255, 0, 0);
        $white = imagecolorallocate($im, 255, 255, 255);
        imagefilledrectangle($im, 0, 0, 1999, 1499, $white);
        imagefilledrectangle($im, 0, 0, 199, 199, $red);
        $tmp = tempnam(sys_get_temp_dir(), 'orient_');
        imagejpeg($im, $tmp, 95);
        imagedestroy($im);

        // Splice an EXIF APP1 segment with Orientation=6 in front of
        // the image data. Rather than hand-roll TIFF, just check that
        // our compressor honors EXIF when present using a fixture
        // generated via a small APP1 with a known orientation block.
        $bytes = $this->withExifOrientation(file_get_contents($tmp), 6);
        @unlink($tmp);

        $path = $user->id . '/images/' . Str::uuid() . '.jpg';
        Storage::disk('user_files')->put($path, $bytes);
        $stored = UserFile::create([
            'user_id'       => $user->id,
            'original_name' => 'rot.jpg',
            'filename'      => basename($path),
            'mime_type'     => 'image/jpeg',
            'size_bytes'    => strlen($bytes),
            'type'          => 'image',
            'disk'          => 'user_files',
            'path'          => $path,
        ]);

        // Sanity: fixture really does declare orientation 6.
        $exifBefore = @exif_read_data('data://image/jpeg;base64,' . base64_encode($bytes));
        $this->assertSame(6, (int) ($exifBefore['Orientation'] ?? 0),
            'fixture must declare orientation=6');

        $this->assertTrue($stored->reoptimizeImageInPlace(800, 800, 85));

        $newBytes = Storage::disk('user_files')->get($stored->path);
        // Orientation 6 means a 2000x1500 source displays as 1500x2000;
        // after baking rotation into pixels, the longer edge becomes
        // the height. Within an 800x800 cap, expect H >= W.
        [$w, $h] = getimagesizefromstring($newBytes);
        $this->assertGreaterThan($w, $h,
            'Lazy reoptimize must bake EXIF rotation into pixels, not preserve landscape orientation.');
        $this->assertLessThanOrEqual(800, max($w, $h));
    }

    /** Wrap an existing JPEG with a minimal EXIF APP1 segment carrying Orientation. */
    private function withExifOrientation(string $jpeg, int $orientation): string
    {
        // Minimal little-endian TIFF with one IFD entry: Orientation (0x0112).
        $tiff =
            "II" . pack('v', 0x002A) . pack('V', 8)             // header + IFD0 offset
            . pack('v', 1)                                      // 1 entry
            . pack('v', 0x0112) . pack('v', 3) . pack('V', 1) . pack('v', $orientation) . "\x00\x00"
            . pack('V', 0);                                     // next IFD = none
        $exif = "Exif\x00\x00" . $tiff;
        $app1 = "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif;

        // JPEG must start with SOI (FFD8); insert APP1 right after.
        return substr($jpeg, 0, 2) . $app1 . substr($jpeg, 2);
    }

    public function test_existing_oversized_photo_is_reoptimized_on_header_save(): void
    {
        Storage::fake('user_files');
        $user = $this->makeUser();

        // Stash an oversized image directly (bypassing compression),
        // simulating a photo uploaded before this feature existed.
        $bigBytes = file_get_contents($this->jpegUpload(2000, 1500)->getRealPath());
        $path = $user->id . '/images/' . Str::uuid() . '.jpg';
        Storage::disk('user_files')->put($path, $bigBytes);
        $stored = UserFile::create([
            'user_id'       => $user->id,
            'original_name' => 'old.jpg',
            'filename'      => basename($path),
            'mime_type'     => 'image/jpeg',
            'size_bytes'    => strlen($bigBytes),
            'type'          => 'image',
            'disk'          => 'user_files',
            'path'          => $path,
        ]);

        // Wire it onto the resume header.
        $resume = $user->ensureResume();
        $sections = $resume->getMergedSections();
        $sections['header']['photo_user_file_id'] = $stored->id;
        $resume->update(['sections' => $sections]);

        $sizeBefore = $stored->size_bytes;

        // Trigger a header save (just renaming).
        $res = $this->actingAs($user)->putJson('/user/resume/header', [
            'name' => 'New Name',
        ]);
        $res->assertOk();

        $stored->refresh();
        $this->assertLessThan($sizeBefore, $stored->size_bytes,
            'Oversized header photo should be lazily shrunk on header save.');

        $bytes = Storage::disk('user_files')->get($stored->path);
        [$w, $h] = getimagesizefromstring($bytes);
        $this->assertLessThanOrEqual(800, $w);
        $this->assertLessThanOrEqual(800, $h);
    }
}
