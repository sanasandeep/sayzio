<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\Resume;
use App\Modules\User\Models\User;
use App\Modules\User\Support\PageBackground;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The one surface in this rollout that could not hang off a link.
 *
 * A resume has two public URLs. `@handle/{slug}` is served straight by
 * PublicResumeController with no Link in scope at all, and a resume LINK's
 * alias reaches the same controller through RedirectController, which
 * resolves the resume and then discards the link. Storing the background
 * in `links.settings['biolink']` like every other type would therefore
 * give the same resume a background at one of its URLs and not the other
 * -- worse than not shipping the feature.
 *
 * So it lives on the resume, in a new nullable `page_background` column,
 * and both routes render the same page.
 *
 * What it changes is the DESK, not the paper: a resume already has a
 * template and a colour theme, and those style the sheet. The surface it
 * sits on was a fixed #f3f4f6 nobody could touch.
 *
 * The background card needed one line to support this -- it took $link and
 * read $link->settings['biolink']; it now takes either that or a settings
 * array directly. That one line is what kept this from becoming a second
 * background picker, which is the thing this entire run of work exists to
 * undo.
 */
class AResumeDeskIsChosenOnTheResumeTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['handle' => 'h'.fake()->unique()->numerify('########')]);
    }

    private function resume(User $user, array $background = []): Resume
    {
        return Resume::create([
            'user_id'        => $user->id,
            'template_id'    => 'classic',
            'color_theme_id' => 'slate',
            'sections'       => [],
            'is_public'      => true,
            'is_default'     => true,
            'name'           => 'Default',
            'page_background' => $background ?: null,
        ]);
    }

    // ===== Where it is stored, and why =====

    /** The column exists and round-trips as an array. */
    public function test_the_background_lives_on_the_resume(): void
    {
        $user   = $this->owner();
        $resume = $this->resume($user, ['background_type' => 'color', 'background_color' => '#c8f7c5']);

        $this->assertSame('#c8f7c5', $resume->fresh()->page_background['background_color'] ?? null);
    }

    /**
     * BOTH public routes render the same desk. This is the whole reason
     * the column exists rather than reusing the link's settings.
     */
    public function test_both_of_a_resumes_urls_render_the_same_background(): void
    {
        $user   = $this->owner();
        $resume = $this->resume($user, ['background_type' => 'color', 'background_color' => '#c8f7c5']);

        // 1. The handle route, which has no Link at all.
        $viaHandle = $this->get(route('resume.public.show', $user->handle))->assertOk()->getContent();

        // 2. A resume link's alias, which resolves the resume and drops the link.
        /** @var Link $link */
        $link = $user->links()->create([
            'user_id'   => $user->id,
            'type'      => 'resume',
            'resume_id' => $resume->id,
            'alias'     => 'r'.fake()->unique()->numerify('########'),
            'is_active' => true,
        ]);
        $viaAlias = $this->get('/'.$link->alias)->assertOk()->getContent();

        foreach (['handle' => $viaHandle, 'alias' => $viaAlias] as $route => $html) {
            $this->assertStringContainsString('resume-paper', $html,
                "the {$route} route must render the resume, not something else");
            $this->assertStringContainsString('#c8f7c5', $html,
                "the {$route} route must render the chosen desk");
            $this->assertStringContainsString('class="bg-page-fixed bg-layer"', $html,
                "the {$route} route must render the shared background layer");
        }
    }

    // ===== Opt-in =====

    /** Nothing chosen: the desk is the one it has always been. */
    public function test_an_unchosen_resume_keeps_its_original_desk(): void
    {
        $user = $this->owner();
        $this->resume($user);

        $html = $this->get(route('resume.public.show', $user->handle))->assertOk()->getContent();

        $this->assertStringContainsString('background: #f3f4f6', $html,
            'an unedited resume must keep the desk it always had');
        $this->assertStringNotContainsString('class="bg-page-fixed bg-layer"', $html);
    }

    /** The paper is not the desk: template and theme survive. */
    public function test_the_resume_sheet_is_untouched_by_a_chosen_desk(): void
    {
        $user = $this->owner();
        $this->resume($user, ['background_type' => 'color', 'background_color' => '#c8f7c5']);

        $html = $this->get(route('resume.public.show', $user->handle))->assertOk()->getContent();

        $this->assertStringContainsString('resume-paper', $html);
        $this->assertStringContainsString('background: #fff', $html,
            'the sheet stays white; only the surface under it changed');
    }

    // ===== Saving =====

    /** The owner can set one, and only the renderer's own fields land. */
    public function test_the_owner_can_save_a_background(): void
    {
        $user = $this->owner();
        $this->resume($user);

        $this->actingAs($user)->postJson('/user/resume/page-background', [
            'background_type'  => 'color',
            'background_color' => '#c8f7c5',
            'sections'         => ['not' => 'allowed'],
            'template_id'      => 'something-else',
        ])->assertOk();

        $resume = $user->resumes()->first()->fresh();

        $this->assertSame('color', $resume->page_background['background_type']);
        $this->assertSame('#c8f7c5', $resume->page_background['background_color']);
        $this->assertArrayNotHasKey('sections', $resume->page_background);
        $this->assertArrayNotHasKey('template_id', $resume->page_background);
        $this->assertSame('classic', $resume->template_id,
            'the background endpoint must not be a way to change the template');
    }

    /** A junk colour is refused rather than rendered. */
    public function test_a_malformed_colour_is_rejected(): void
    {
        $user = $this->owner();
        $this->resume($user);

        $this->actingAs($user)->postJson('/user/resume/page-background', [
            'background_type'  => 'color',
            'background_color' => 'javascript:alert(1)',
        ])->assertStatus(422);
    }

    /** An unknown background type is refused. */
    public function test_an_unknown_type_is_rejected(): void
    {
        $user = $this->owner();
        $this->resume($user);

        $this->actingAs($user)->postJson('/user/resume/page-background', [
            'background_type' => 'iframe',
        ])->assertStatus(422);
    }

    /** Clearing it puts the resume back on its original desk. */
    public function test_clearing_the_background_restores_the_original_desk(): void
    {
        $user = $this->owner();
        $this->resume($user, ['background_type' => 'color', 'background_color' => '#c8f7c5']);

        $this->actingAs($user)->postJson('/user/resume/page-background', [])->assertOk();

        $resume = $user->resumes()->first()->fresh();
        $this->assertNull($resume->page_background);
        $this->assertFalse(PageBackground::chosen($resume->page_background ?? []));

        $html = $this->get(route('resume.public.show', $user->handle))->assertOk()->getContent();
        $this->assertStringContainsString('background: #f3f4f6', $html);
    }

    /** Somebody else's resume is not theirs to restyle. */
    public function test_another_user_cannot_set_it(): void
    {
        $owner = $this->owner();
        $this->resume($owner);

        $this->actingAs($this->owner())->postJson('/user/resume/page-background', [
            'background_type'  => 'color',
            'background_color' => '#000000',
        ]);

        $this->assertNull($owner->resumes()->first()->fresh()->page_background,
            "another account's request must not reach this resume");
    }

    /** The editor offers the picker, and a plain form save works. */
    public function test_the_resume_editor_offers_the_picker(): void
    {
        $editor = file_get_contents(base_path('resources/views/user/resume/editor.blade.php'));

        $this->assertStringContainsString('biolink-background-card', $editor,
            'the resume editor must show the shared picker');
        $this->assertStringContainsString("route('user.resume.page-background.update')", $editor);

        // ...and a non-JSON post redirects rather than dumping JSON at them.
        $user = $this->owner();
        $this->resume($user);

        $this->actingAs($user)
            ->post('/user/resume/page-background', [
                'background_type'  => 'color',
                'background_color' => '#c8f7c5',
            ])
            ->assertRedirect();

        $this->assertSame('#c8f7c5',
            $user->resumes()->first()->fresh()->page_background['background_color']);
    }

    // ===== One picker, still =====

    /** The card takes a settings array, so resumes need no second picker. */
    public function test_the_shared_card_works_without_a_link(): void
    {
        $card = file_get_contents(
            base_path('resources/views/user/links/partials/biolink-background-card.blade.php')
        );

        $this->assertStringContainsString('$bs = $bs ?? ($link->settings[\'biolink\'] ?? []);', $card,
            'the card must accept a settings array directly, or a resume needs its own picker');

        // And it renders standalone, which is the actual claim.
        $html = view('user.links.partials.biolink-background-card', [
            'bs'   => ['background_type' => 'color', 'background_color' => '#c8f7c5'],
            'link' => null,
        ])->render();

        $this->assertStringContainsString('Page background', $html);
        $this->assertStringContainsString('#c8f7c5', $html);
    }
}
