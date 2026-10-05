<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sana, 2026-10-05: "why fall back image? it should be fall back color...".
 *
 * `bg_fallback_image` is the still frame behind media that has not started
 * yet -- a video's poster, the first paint of a slideshow. The public page
 * reads it for slideshow, video and template and for nothing else.
 *
 * The editor showed the uploader for `image` too, where it is never read:
 * an upload box that did nothing, sitting directly under the control that
 * answers the question he was actually asking. "Colour behind it" IS the
 * fallback colour, and the image branch paints it.
 *
 * The same expression had the opposite fault at the other end: `template`
 * DOES read the poster and was never offered one.
 *
 * So the pair of tests here is the point, not either one alone: the set of
 * types the editor offers the control for must equal the set of types the
 * renderer reads it for. Showing it where it is dead and hiding it where it
 * is live are the same bug seen from two sides.
 */
class ThePosterImageIsOfferedWhereItIsUsedTest extends TestCase
{
    use RefreshDatabase;

    /** The three the public page actually paints a poster for. */
    private const USED_BY = ['slideshow', 'template', 'video'];

    private function makeOwner(): User
    {
        $user = User::create([
            'name'     => 'Owner '.Str::random(4),
            'email'    => 'own'.Str::random(8).'@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);

        $ws = app(WorkspaceContext::class)->resolve($user);
        if ($ws !== null) {
            app()->instance('current_workspace', $ws);
        }
        app()->instance('workspace_owner', $user);

        return $user;
    }

    private function makePage(User $owner, array $biolinkSettings): Link
    {
        $link = Link::create([
            'user_id'   => $owner->id,
            'type'      => 'biolink',
            'alias'     => Link::generateAlias(),
            'title'     => 'My Bio',
            'is_active' => true,
        ]);
        $link->settings = ['biolink' => $biolinkSettings];
        $link->save();

        return $link->fresh();
    }

    public function test_an_image_background_never_paints_the_poster(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makePage($owner, [
            'background_type'   => 'image',
            'background_image'  => 'https://example.com/real-bg.jpg',
            'bg_fallback_image' => 'https://example.com/poster-never-used.jpg',
            'bg_fallback_color' => '#123456',
        ]);

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        // The chosen image renders; the poster is not read on this branch.
        $this->assertStringContainsString('real-bg.jpg', $html);
        $this->assertStringNotContainsString('poster-never-used.jpg', $html);
        // And the thing he was looking for is here, and works: the colour.
        $this->assertStringContainsString('#123456', $html);
    }

    public function test_a_video_background_does_paint_the_poster(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makePage($owner, [
            'background_type'   => 'video',
            'bg_fallback_image' => 'https://example.com/poster-used.jpg',
        ]);

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();
        $this->assertStringContainsString('poster-used.jpg', $html);
    }

    public function test_a_template_background_does_paint_the_poster(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makePage($owner, [
            'background_type'   => 'template',
            'bg_fallback_image' => 'https://example.com/poster-used.jpg',
        ]);

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();
        $this->assertStringContainsString('poster-used.jpg', $html);
    }

    /**
     * The editor offers the control for exactly those three.
     *
     * Read off the blade source rather than a rendered page, because the
     * control's visibility is an Alpine expression evaluated in the browser
     * -- the markup is present at every background type and only `x-show`
     * decides. A rendered-HTML assertion would pass with the expression
     * saying anything at all.
     */
    public function test_the_editor_offers_it_for_exactly_those_types(): void
    {
        $blade = file_get_contents(
            resource_path('views/user/links/partials/biolink-background-card.blade.php')
        );

        // The dropzone's own `name`, not the first mention of the key in the
        // file -- the settings are read into a variable near the top, long
        // before any wrapper, and anchoring there finds the wrong div.
        $pos = strpos($blade, "=> 'bg_fallback_image'");
        $this->assertNotFalse($pos, 'the poster uploader is gone from the background card');

        // The x-show on the wrapper immediately above the include.
        $before = substr($blade, 0, $pos);
        $start  = strrpos($before, '<div x-show=');
        $this->assertNotFalse($start);
        $expr = substr($blade, $start, strpos($blade, '>', $start) - $start);

        preg_match_all("/bgType === '([a-z]+)'/", $expr, $m);
        $offered = $m[1];
        sort($offered);

        $this->assertSame(
            self::USED_BY,
            $offered,
            'the editor offers the poster for a different set of background types than the public page paints it for'
        );
    }

    /**
     * And the renderer reads it for exactly those three.
     *
     * Together with the test above this is the guard: change one side alone
     * and one of the two fails. An uploader that does nothing and a
     * capability with no screen are the same drift in opposite directions,
     * and this is the only thing that notices either.
     */
    public function test_the_renderer_reads_it_for_exactly_those_types(): void
    {
        $blade = file_get_contents(
            resource_path('views/common/page-background/body-declarations.blade.php')
        );

        $pos = strpos($blade, "\$pb['fallbackImage']");
        $this->assertNotFalse($pos, 'the renderer no longer paints a poster at all');

        $before = substr($blade, 0, $pos);
        $start  = strrpos($before, '@elseif(');
        $this->assertNotFalse($start);
        $branch = substr($blade, $start, strpos($blade, ')', $start) - $start);

        preg_match_all("/\\\$pb\['type'\] === '([a-z]+)'/", $branch, $m);
        $read = $m[1];
        sort($read);

        $this->assertSame(self::USED_BY, $read);
    }
}
