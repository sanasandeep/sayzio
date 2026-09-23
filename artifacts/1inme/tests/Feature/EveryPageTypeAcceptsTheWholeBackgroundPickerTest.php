<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\Resume;
use App\Modules\User\Models\User;
use App\Modules\User\Support\PageBackgroundInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Sana, 2026-09-23, looking at the resume editor: "backgrounds are not
 * working. fix it."
 *
 * They were not, and it was not his data.
 *
 * Every page type that offers a background renders the SAME picker --
 * user.links.partials.biolink-background-card -- but each save path wrote
 * its own validation rules for what that picker is allowed to send. The
 * resume's copy disagreed with the picker in two places:
 *
 *   - `gradient_colors` was validated as an ARRAY. The picker posts it as
 *     a JSON STRING. Every gradient came back 422, and because a 422
 *     rejects the whole request, it took the colour, the blur and the
 *     overlay set in the same submit down with it.
 *   - `background_image` and `torn_image` were validated as STRINGS. They
 *     are file uploads. An uploaded photo was dropped without a word, and
 *     `background_image_asset` -- the gallery pick -- was not in the
 *     accepted list at all, so the library did nothing either.
 *
 * A solid colour worked, which is exactly why this survived: the first
 * thing anyone tries is a colour, and the colour saves.
 *
 * The fix is not more rules. It is one rule set, in PageBackgroundInput,
 * that both paths now call -- so the question "does this page type accept
 * what the picker sends?" has one answer instead of one per controller.
 * The last test here is the one that keeps it that way.
 */
class EveryPageTypeAcceptsTheWholeBackgroundPickerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('s3');
        Storage::fake('user_files');

        // UserFile refuses to write before S3 is fully configured, which is
        // right in production and unhelpful here: the faked disk needs the
        // same four keys present for the guard to pass.
        config([
            'filesystems.disks.user_files.driver' => 's3',
            'filesystems.disks.user_files.key'    => 'testing-key',
            'filesystems.disks.user_files.secret' => 'testing-secret',
            'filesystems.disks.user_files.bucket' => 'testing-bucket',
            'filesystems.disks.user_files.region' => 'us-east-1',
        ]);

        $this->user = User::factory()->create([
            'handle'       => 'h'.fake()->unique()->numerify('########'),
            'onboarded_at' => now(),
        ]);
    }

    private function resume(array $background = []): Resume
    {
        return Resume::create([
            'user_id'         => $this->user->id,
            'template_id'     => 'classic',
            'color_theme_id'  => 'slate',
            'sections'        => [],
            'is_public'       => true,
            'is_default'      => true,
            'name'            => 'Default',
            'page_background' => $background ?: null,
        ]);
    }

    private function biolink(): Link
    {
        return Link::create([
            'user_id'   => $this->user->id,
            'type'      => 'biolink',
            'alias'     => 'bl'.fake()->unique()->numerify('#####'),
            'title'     => 'Page',
            'is_active' => true,
        ]);
    }

    /** What the picker actually posts for a two-stop gradient. */
    private const GRADIENT_JSON = '[{"color":"#ff7a18","stop":0},{"color":"#af002d","stop":100}]';

    // ===== 1. The gradient, which is the bug =====

    /**
     * The headline. Nothing about this payload is unusual -- it is what
     * the picker sends when you drag two stops and hit save.
     */
    public function test_a_gradient_saves_on_a_resume(): void
    {
        $resume = $this->resume();

        $this->actingAs($this->user)
            ->postJson('/user/resume/page-background', [
                'background_type'  => 'gradient',
                'gradient_colors'  => self::GRADIENT_JSON,
                'gradient_angle'   => 135,
                'gradient_type'    => 'linear',
            ])
            ->assertOk();

        $stored = $resume->fresh()->page_background;

        $this->assertSame('gradient', $stored['background_type']);
        $this->assertEquals(
            [['color' => '#ff7a18', 'stop' => 0], ['color' => '#af002d', 'stop' => 100]],
            $stored['gradient_colors'],
            'the picker posts the stop list as JSON; it must be decoded, not rejected'
        );
        $this->assertSame(135, (int) $stored['gradient_angle']);
    }

    /**
     * And it reaches the page. A save that stores nothing readable is the
     * same bug wearing a 200.
     *
     * The picker composes the CSS value into `background_gradient` and
     * posts the stop list alongside it so it can rebuild its own sliders
     * on the next edit -- the renderer paints from the composed value.
     */
    public function test_a_saved_gradient_renders_on_the_public_page(): void
    {
        $this->resume([
            'background_type'     => 'gradient',
            'background_gradient' => 'linear-gradient(135deg, #ff7a18 0%, #af002d 100%)',
            'gradient_colors'     => [['color' => '#ff7a18', 'stop' => 0], ['color' => '#af002d', 'stop' => 100]],
            'gradient_angle'      => 135,
            'gradient_type'       => 'linear',
        ]);

        $html = $this->get('/'.$this->user->handle.'/resume')->assertOk()->getContent();

        $this->assertStringContainsString('#ff7a18', $html);
        $this->assertStringContainsString('#af002d', $html);
        $this->assertStringNotContainsString('background: #f3f4f6', $html,
            'the hardcoded desk must be gone once a background is chosen');
    }

    /**
     * The old rules did not merely ignore the gradient -- they 422'd the
     * whole request, so the blur set in the same submit was lost too.
     * This is the test that would have caught the original bug.
     */
    public function test_a_gradient_does_not_take_the_rest_of_the_form_down_with_it(): void
    {
        $resume = $this->resume();

        $this->actingAs($this->user)
            ->postJson('/user/resume/page-background', [
                'background_type'    => 'gradient',
                'gradient_colors'    => self::GRADIENT_JSON,
                'bg_blur'            => 12,
                'bg_overlay_color'   => '#000000',
                'bg_overlay_opacity' => 40,
            ])
            ->assertOk();

        $stored = $resume->fresh()->page_background;

        $this->assertSame(12, (int) $stored['bg_blur']);
        $this->assertSame(40, (int) $stored['bg_overlay_opacity']);
    }

    // ===== 2. The uploads =====

    /** An uploaded photo is a file, and must be stored as one. */
    public function test_an_uploaded_photo_saves_on_a_resume(): void
    {
        $resume = $this->resume();

        $this->actingAs($this->user)
            ->post('/user/resume/page-background', [
                'background_type'  => 'image',
                'background_image' => UploadedFile::fake()->image('desk.jpg', 1200, 800),
            ]);

        $stored = $resume->fresh()->page_background;

        $this->assertSame('image', $stored['background_type'] ?? null);
        $this->assertNotEmpty($stored['background_image'] ?? null,
            'the picker sends a file here; validating it as a string dropped it silently');
    }

    /** The torn-paper backdrop is the same kind of upload. */
    public function test_a_torn_paper_backdrop_photo_saves(): void
    {
        $resume = $this->resume();

        $this->actingAs($this->user)
            ->post('/user/resume/page-background', [
                'background_type'  => 'torn',
                'torn_paper_color' => '#fdf6e3',
                'torn_image'       => UploadedFile::fake()->image('backdrop.jpg', 900, 900),
            ]);

        $this->assertNotEmpty($resume->fresh()->page_background['torn_image'] ?? null);
    }

    /** A pick from the platform gallery resolves to its URL. */
    public function test_a_gallery_pick_saves_on_a_resume(): void
    {
        $resume = $this->resume();

        $this->actingAs($this->user)
            ->post('/user/resume/page-background', [
                'background_type'        => 'image',
                'background_image_asset' => 'assets/biolink-backgrounds/dunes.jpg',
            ]);

        $stored = $resume->fresh()->page_background;

        $this->assertNotEmpty($stored['background_image'] ?? null,
            'the gallery key was not even in the accepted field list before');
        $this->assertStringContainsString('dunes.jpg', $stored['background_image']);
    }

    /** A key outside the curated folders is still refused. */
    public function test_an_arbitrary_object_key_is_still_refused(): void
    {
        $this->resume();

        $this->actingAs($this->user)
            ->postJson('/user/resume/page-background', [
                'background_type'        => 'image',
                'background_image_asset' => 'private/someone-elses/secret.jpg',
            ])
            ->assertStatus(422);
    }

    // ===== 3. What a second save must not destroy =====

    /**
     * A stored photo lives in the settings as a URL, and the form cannot
     * re-post the file it came from. So a save that only touches the blur
     * must not throw away the picture it is blurring.
     */
    public function test_changing_the_blur_keeps_the_photo(): void
    {
        $resume = $this->resume([
            'background_type'  => 'image',
            'background_image' => 'https://cdn.example.test/vault/desk.jpg',
        ]);

        $this->actingAs($this->user)
            ->postJson('/user/resume/page-background', [
                'background_type' => 'image',
                'bg_blur'         => 20,
            ])
            ->assertOk();

        $stored = $resume->fresh()->page_background;

        $this->assertSame('https://cdn.example.test/vault/desk.jpg', $stored['background_image']);
        $this->assertSame(20, (int) $stored['bg_blur']);
    }

    /**
     * A scalar the creator cleared, though, has to disappear. The card is
     * a plain form that posts all of its controls every time, so "absent"
     * means "removed", not "unchanged" -- otherwise an overlay could never
     * be taken off again.
     */
    public function test_a_cleared_overlay_actually_goes_away(): void
    {
        $resume = $this->resume([
            'background_type'    => 'color',
            'background_color'   => '#101820',
            'bg_overlay_color'   => '#ff0000',
            'bg_overlay_opacity' => 60,
        ]);

        $this->actingAs($this->user)
            ->postJson('/user/resume/page-background', [
                'background_type'  => 'color',
                'background_color' => '#101820',
            ])
            ->assertOk();

        $stored = $resume->fresh()->page_background;

        $this->assertArrayNotHasKey('bg_overlay_color', $stored);
        $this->assertSame('#101820', $stored['background_color']);
    }

    // ===== 4. The Link in Bio path still does all of this =====

    /**
     * The fix moved the biolink's own upload handling into the shared
     * class. The page that has always worked has to keep working.
     */
    public function test_a_biolink_still_takes_a_gradient_and_a_photo(): void
    {
        $link = $this->biolink();

        $this->actingAs($this->user)
            ->post('/user/links/'.$link->id.'/page-settings', [
                'background_type'  => 'gradient',
                'gradient_colors'  => self::GRADIENT_JSON,
                'gradient_angle'   => 90,
                'background_image' => UploadedFile::fake()->image('hero.jpg', 1200, 800),
            ]);

        $bs = $link->fresh()->settings['biolink'] ?? [];

        $this->assertEquals(
            [['color' => '#ff7a18', 'stop' => 0], ['color' => '#af002d', 'stop' => 100]],
            $bs['gradient_colors'] ?? null
        );
        $this->assertNotEmpty($bs['background_image'] ?? null);
    }

    // ===== 5. The guard against a third copy =====

    /**
     * The real fix is that there is now ONE definition of what the picker
     * may send. This test fails the moment someone adds a background field
     * to the picker and forgets a page type, or writes a second rule set
     * for a new one -- which is precisely how the resume drifted.
     */
    public function test_every_field_the_renderer_reads_is_a_field_the_picker_may_send(): void
    {
        $accepted = array_keys(PageBackgroundInput::rules($this->user));

        foreach (\App\Modules\User\Support\PageBackground::FIELDS as $field) {
            $this->assertContains($field, $accepted,
                $field.' is read by the renderer but cannot be saved, so it is dead');
        }
    }

    /**
     * And the split between "absorb handles it" and "copy it straight" has
     * to stay honest: a media key copied straight through would store an
     * UploadedFile object or a raw JSON string in the settings.
     */
    public function test_no_media_key_is_treated_as_a_plain_scalar(): void
    {
        foreach (PageBackgroundInput::MEDIA_KEYS as $key) {
            $this->assertNotContains($key, PageBackgroundInput::scalarKeys(),
                $key.' is handled by absorb(); copying it straight would store the wrong thing');
        }
    }
}
