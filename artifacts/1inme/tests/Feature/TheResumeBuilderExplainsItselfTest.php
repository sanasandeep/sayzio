<?php

namespace Tests\Feature;

use App\Modules\User\Models\Resume;
use App\Modules\User\Models\User;
use App\Modules\User\Services\ResumeVersionService;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Three of Sana's notes on 2026-09-23, which turned out to be one
 * problem wearing three hats:
 *
 *   "Resume Product Designer/Resume Growth marketer i didnt understand
 *    concept. update in better way"
 *   "fix ui in resume manage tab as it looks ugly"
 *   "see if u can add more options in sections and inside it.."
 *
 * The builder showed you its state and never told you what any of it
 * meant. The version row was a set of names with no explanation of what
 * a version IS, which one a visitor gets, or where the others live. The
 * entry rows were a truncated title and five icon buttons -- a lot of
 * furniture around almost no information. And "New version" ran a
 * window.prompt and then made an EMPTY resume, so the obvious thing to
 * press produced a blank page and no hint of why you would want one.
 */
class TheResumeBuilderExplainsItselfTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'handle'       => 'h'.fake()->unique()->numerify('########'),
            'onboarded_at' => now(),
        ]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    private function resume(array $attrs = []): Resume
    {
        return Resume::create(array_merge([
            'user_id'        => $this->user->id,
            'template_id'    => 'classic',
            'color_theme_id' => 'slate',
            'sections'       => [],
            'is_public'      => true,
            'is_default'     => true,
            'name'           => 'Default',
        ], $attrs));
    }

    private function editor(): string
    {
        return $this->actingAs($this->user)->get('/user/resume')->assertOk()->getContent();
    }

    // ===== 1. What a version is =====

    /**
     * The explanation sits in the row itself, not behind a Manage button
     * nobody has a reason to press.
     */
    public function test_the_version_row_says_what_a_version_is(): void
    {
        $this->resume();
        $html = $this->editor();

        $this->assertStringContainsString('A version is a separate copy of this resume', $html);
        $this->assertStringContainsString('with its own link', $html);
        $this->assertStringContainsString('your main resume link', $html,
            'which one a visitor gets is the question the row never answered');
    }

    /** And which one is live is marked, not left to a star icon. */
    public function test_the_live_version_is_labelled(): void
    {
        $this->resume();
        $html = $this->editor();

        $this->assertStringContainsString('>Live<', $html);
    }

    // ===== 2. Making one =====

    /**
     * A dialog that asks what the version is FOR, with suggestions
     * phrased as purposes rather than as job titles -- "Product design
     * roles" reads as a reason, "Product Designer" reads as a job
     * someone had, which is exactly the confusion reported.
     */
    public function test_the_new_version_dialog_asks_what_it_is_for(): void
    {
        $this->resume();
        $html = $this->editor();

        $this->assertStringContainsString('What is it for?', $html);
        $this->assertStringContainsString('Product design roles', $html);
        $this->assertStringNotContainsString("window.prompt('Name this resume version", $html);
    }

    /** With a real choice about where the content comes from. */
    public function test_the_dialog_offers_a_copy_or_a_blank_start(): void
    {
        $this->resume();
        $html = $this->editor();

        $this->assertStringContainsString('Start from this resume', $html);
        $this->assertStringContainsString('Start empty', $html);
    }

    /**
     * And a copy is a real copy: the entries, what is hidden among them,
     * and the look. A duplicate that came back on a different background
     * reads as a bug, not as a fresh start.
     */
    public function test_a_duplicated_version_carries_the_look_and_the_hidden_entries(): void
    {
        $source = $this->resume([
            'page_background' => ['background_type' => 'color', 'background_color' => '#101820'],
            'share_button'    => ['enabled' => false],
        ]);
        $source->items()->create([
            'section_type' => 'experience', 'position' => 0,
            'data' => ['role' => 'Shown', 'company' => 'A'],
        ]);
        $source->items()->create([
            'section_type' => 'experience', 'position' => 1,
            'is_hidden' => true,
            'data' => ['role' => 'Hidden', 'company' => 'B'],
        ]);

        $copy = app(ResumeVersionService::class)
            ->duplicate($this->user, $source, 'Product design roles');

        $this->assertSame('#101820', $copy->page_background['background_color'] ?? null);
        $this->assertFalse($copy->share_button['enabled'] ?? true);

        $hidden = $copy->items->firstWhere('data.role', 'Hidden');
        $this->assertTrue((bool) $hidden->is_hidden,
            'a tailored copy starts from what the source SHOWS, not from everything it holds');
    }

    // ===== 3. The rows =====

    /**
     * Each row carries a second line -- the dates. It is what you scan a
     * resume's list for: which job was when, in what order.
     */
    public function test_an_entry_row_shows_its_dates(): void
    {
        $this->resume();
        $html = $this->editor();

        $this->assertStringContainsString('itemMeta(', $html);
        $this->assertStringContainsString('resume-card-item-meta', $html);
    }

    /** The actions are one quiet group rather than five loose buttons. */
    public function test_the_row_actions_are_grouped(): void
    {
        $this->resume();
        $html = $this->editor();

        $this->assertStringContainsString('resume-item-actions', $html);
        $this->assertStringContainsString('@media (hover: none)', $html,
            'a touch screen has no hover, so the actions must not fade there');
    }

    /**
     * Dragging is the grip, opening is the row. Making one element do
     * both is what makes each of them feel unreliable.
     */
    public function test_the_drag_handle_is_the_grip_not_the_whole_row(): void
    {
        $this->resume();
        $html = $this->editor();

        $this->assertStringContainsString("handle: '.resume-grip'", $html);
    }

    // ===== 4. More options, inside the sections =====

    /**
     * Every field here is one an employer or an applicant tracker looks
     * for, and the form had nowhere to put it.
     *
     * Saving is what proves it: the editor could offer a control that
     * the save path drops, which is the shape of bug this whole day has
     * been about.
     */
    public function test_a_job_can_record_how_it_was_worked(): void
    {
        $resume = $this->resume();
        $item = $resume->items()->create([
            'section_type' => 'experience', 'position' => 0,
            'data' => ['role' => 'Founder', 'company' => 'Sayzio'],
        ]);

        $this->actingAs($this->user)
            ->putJson('/user/resume/items/'.$item->id, [
                'data' => [
                    'role' => 'Founder', 'company' => 'Sayzio',
                    'employment_type' => 'full_time',
                    'work_mode'       => 'remote',
                    'url'             => 'https://sayzio.app',
                ],
            ])->assertOk();

        $d = $item->fresh()->data;

        $this->assertSame('full_time', $d['employment_type']);
        $this->assertSame('remote', $d['work_mode']);
        $this->assertSame('https://sayzio.app', $d['url']);
    }

    public function test_a_degree_can_record_its_grade_and_where_it_was(): void
    {
        $resume = $this->resume();
        $item = $resume->items()->create([
            'section_type' => 'education', 'position' => 0,
            'data' => ['school' => 'USC'],
        ]);

        $this->actingAs($this->user)
            ->putJson('/user/resume/items/'.$item->id, [
                'data' => ['school' => 'USC', 'grade' => '3.8 / 4.0', 'location' => 'Los Angeles, USA'],
            ])->assertOk();

        $d = $item->fresh()->data;

        $this->assertSame('3.8 / 4.0', $d['grade']);
        $this->assertSame('Los Angeles, USA', $d['location']);
    }

    /** Junk in the new choice fields is still refused. */
    public function test_an_invented_employment_type_is_refused(): void
    {
        $resume = $this->resume();
        $item = $resume->items()->create([
            'section_type' => 'experience', 'position' => 0,
            'data' => ['role' => 'Founder', 'company' => 'Sayzio'],
        ]);

        $this->actingAs($this->user)
            ->putJson('/user/resume/items/'.$item->id, [
                'data' => ['role' => 'Founder', 'company' => 'Sayzio', 'employment_type' => 'whatever'],
            ])->assertStatus(422);
    }

    /** The new fields reach the public page, or they are decoration. */
    public function test_the_new_fields_reach_the_public_page(): void
    {
        $resume = $this->resume();
        $resume->items()->create([
            'section_type' => 'experience', 'position' => 0,
            'data' => [
                'role' => 'Founder', 'company' => 'Sayzio',
                'employment_type' => 'full_time', 'work_mode' => 'remote',
            ],
        ]);
        $resume->items()->create([
            'section_type' => 'education', 'position' => 0,
            'data' => ['school' => 'USC', 'degree' => 'M.S.', 'grade' => '3.8 / 4.0'],
        ]);

        $html = $this->get('/'.$this->user->handle.'/resume')->assertOk()->getContent();

        $this->assertStringContainsString('Full-time', $html);
        $this->assertStringContainsString('Remote', $html);
        $this->assertStringContainsString('3.8 / 4.0', $html);
    }

    /**
     * A blank name field is not an offer -- you have to already know
     * what belongs on a resume to fill it in. The sections people most
     * often add are one tap each.
     */
    public function test_extra_sections_are_offered_by_name(): void
    {
        $this->resume();
        $html = $this->editor();

        foreach (['Volunteering', 'Publications', 'Courses', 'Speaking'] as $preset) {
            $this->assertStringContainsString($preset, $html);
        }
    }
}
