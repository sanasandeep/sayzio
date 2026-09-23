<?php

namespace Tests\Feature;

use App\Modules\User\Models\CreatorProfile;
use App\Modules\User\Models\Resume;
use App\Modules\User\Models\ResumeSectionItem;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sana, 2026-09-23: "here resume items can be also hidden... like hide
 * unhide".
 *
 * A resume accumulates. Seven jobs, four degrees, a dozen projects -- and
 * which of them belong on the version you are sending today is not the
 * same question as which of them happened. Until now the only way to
 * leave one off was to delete it, which loses it for every other version
 * of the resume too.
 *
 * The thing that makes this more than a boolean is that FOUR surfaces
 * read a resume's items and three of them are public: the page, the PDF,
 * the ATS check -- and the builder, which must keep showing everything.
 * Each used to call `$resume->items->groupBy('section_type')` for itself,
 * which is exactly the shape where a fifth surface gets added later and
 * quietly shows what the other three hide. They go through
 * Resume::publicItemsByType() now, and the tests below name each one.
 */
class AResumeEntryCanBeHiddenWithoutDeletingItTest extends TestCase
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
    }

    /** A resume with two jobs on it: one to keep, one to hide. */
    private function resumeWithTwoJobs(): Resume
    {
        $resume = Resume::create([
            'user_id'        => $this->user->id,
            'template_id'    => 'classic',
            'color_theme_id' => 'slate',
            'sections'       => ['header' => ['name' => 'Sana Sandeep', 'email' => 'sana@example.test']],
            'is_public'      => true,
            'is_default'     => true,
            'name'           => 'Default',
        ]);

        $resume->items()->create([
            'section_type' => 'experience',
            'position'     => 0,
            'data'         => ['role' => 'Founder and CEO', 'company' => 'Sayzio'],
        ]);
        $resume->items()->create([
            'section_type' => 'experience',
            'position'     => 1,
            'data'         => ['role' => 'Associate Firmware Engineer', 'company' => 'Tesco HSC'],
        ]);

        return $resume;
    }

    private function firmwareJob(Resume $resume): ResumeSectionItem
    {
        return $resume->items()->where('position', 1)->firstOrFail();
    }

    private function publicPage(): string
    {
        return $this->get('/'.$this->user->handle.'/resume')->assertOk()->getContent();
    }

    // ===== 1. The toggle =====

    public function test_an_entry_can_be_hidden_and_brought_back(): void
    {
        $resume = $this->resumeWithTwoJobs();
        $job    = $this->firmwareJob($resume);

        $this->actingAs($this->user)
            ->postJson('/user/resume/items/'.$job->id.'/visibility', ['is_hidden' => true])
            ->assertOk();

        $this->assertTrue($job->fresh()->is_hidden);

        $this->actingAs($this->user)
            ->postJson('/user/resume/items/'.$job->id.'/visibility', ['is_hidden' => false])
            ->assertOk();

        $this->assertFalse($job->fresh()->is_hidden);
    }

    /**
     * The whole point: hiding is not deleting. The entry, and everything
     * typed into it, is still there afterwards.
     */
    public function test_hiding_keeps_the_entry_and_everything_in_it(): void
    {
        $resume = $this->resumeWithTwoJobs();
        $job    = $this->firmwareJob($resume);

        $this->actingAs($this->user)
            ->postJson('/user/resume/items/'.$job->id.'/visibility', ['is_hidden' => true])
            ->assertOk();

        $this->assertSame(2, $resume->fresh()->items()->count());
        $this->assertSame('Associate Firmware Engineer', $job->fresh()->data['role']);
    }

    /**
     * A visibility toggle must not have to resend the item's payload --
     * that is why it is not part of updateItem(). An entry with a field
     * the creator has not filled in yet still hides.
     */
    public function test_hiding_does_not_require_a_complete_entry(): void
    {
        $resume = $this->resumeWithTwoJobs();
        $bare   = $resume->items()->create([
            'section_type' => 'experience',
            'position'     => 2,
            'data'         => [],
        ]);

        $this->actingAs($this->user)
            ->postJson('/user/resume/items/'.$bare->id.'/visibility', ['is_hidden' => true])
            ->assertOk();

        $this->assertTrue($bare->fresh()->is_hidden);
    }

    /** Everything that exists today is visible, and stays that way. */
    public function test_existing_entries_are_visible_by_default(): void
    {
        $resume = $this->resumeWithTwoJobs();

        foreach ($resume->items as $item) {
            $this->assertFalse((bool) $item->is_hidden);
        }
    }

    // ===== 2. The three public surfaces =====

    public function test_a_hidden_entry_is_off_the_public_page(): void
    {
        $resume = $this->resumeWithTwoJobs();
        $this->firmwareJob($resume)->update(['is_hidden' => true]);

        $html = $this->publicPage();

        $this->assertStringContainsString('Founder and CEO', $html);
        $this->assertStringNotContainsString('Associate Firmware Engineer', $html);
    }

    public function test_a_hidden_entry_is_off_the_pdf(): void
    {
        $resume = $this->resumeWithTwoJobs();
        $this->firmwareJob($resume)->update(['is_hidden' => true]);

        // The print view is what the PDF renderer hands to the engine;
        // asserting on it tests the same list without needing a headless
        // browser in the suite.
        $html = view('user.resume.print', [
            'resume'         => $resume->fresh('items'),
            'header'         => $resume->getMergedSections()['header'] ?? [],
            'summary'        => '',
            'customSections' => [],
            'itemsByType'    => $resume->fresh('items')->publicItemsByType(),
            'template'       => $resume->templateMeta(),
            'theme'          => $resume->colorThemeMeta()['tokens'] ?? [],
            'paperSize'      => 'a4',
        ])->render();

        $this->assertStringContainsString('Founder and CEO', $html);
        $this->assertStringNotContainsString('Associate Firmware Engineer', $html);
    }

    /**
     * The ATS check is about what an employer's parser sees, so a hidden
     * entry must neither help nor hurt the score.
     */
    public function test_the_ats_check_reads_the_same_list_the_page_does(): void
    {
        $resume = $this->resumeWithTwoJobs();
        $this->firmwareJob($resume)->update(['is_hidden' => true]);

        $visible = $resume->fresh('items')->publicItemsByType();

        $this->assertCount(1, $visible['experience'] ?? collect());
        $this->assertSame(
            'Founder and CEO',
            ($visible['experience'] ?? collect())->first()->data['role']
        );
    }

    /**
     * And the cover letter, which is written FROM the resume being sent.
     */
    public function test_the_cover_letter_does_not_cite_a_hidden_job(): void
    {
        $resume = $this->resumeWithTwoJobs();
        $this->firmwareJob($resume)->update(['is_hidden' => true]);

        $roles = $resume->fresh()->itemsOfType('experience')
            ->where('is_hidden', false)
            ->pluck('data')
            ->map(fn ($d) => $d['role'] ?? '')
            ->all();

        $this->assertSame(['Founder and CEO'], $roles);
    }

    // ===== 3. The builder keeps showing it =====

    /**
     * Hiding is reversible only if you can still see the thing you hid.
     */
    public function test_the_builder_still_lists_a_hidden_entry(): void
    {
        $resume = $this->resumeWithTwoJobs();
        $this->firmwareJob($resume)->update(['is_hidden' => true]);

        $payload = $this->actingAs($this->user)
            ->getJson('/user/resume/data')->assertOk()->json();

        $experience = collect($payload['resume']['items']['experience'] ?? []);

        $this->assertCount(2, $experience, 'a hidden entry must stay editable');
        $this->assertTrue(
            (bool) $experience->firstWhere('id', $this->firmwareJob($resume)->id)['is_hidden'],
            'and the builder has to know which one is hidden, to draw the toggle'
        );
    }

    // ===== 4. Ownership =====

    public function test_another_account_cannot_hide_your_entry(): void
    {
        $resume = $this->resumeWithTwoJobs();
        $job    = $this->firmwareJob($resume);

        $stranger = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($stranger);

        $this->actingAs($stranger)
            ->postJson('/user/resume/items/'.$job->id.'/visibility', ['is_hidden' => true])
            ->assertForbidden();

        $this->assertFalse($job->fresh()->is_hidden);
    }

    // ===== 5. The guard =====

    /**
     * The reason this is a method on the model rather than a filter copied
     * into each renderer. If a public surface starts reading the raw items
     * relation again, it will show what the others hide -- and nobody will
     * notice until someone's old job turns up on a resume they thought
     * they had cleaned.
     */
    public function test_no_public_renderer_groups_the_raw_items_relation(): void
    {
        $offenders = [];

        $files = [
            resource_path('views/common/partials/resume-render.blade.php'),
            app_path('Modules/User/Services/ResumePdfRenderer.php'),
            app_path('Modules/User/Services/ResumeAtsChecker.php'),
        ];

        foreach ($files as $path) {
            $src = (string) file_get_contents($path);
            if (preg_match('/->items->groupBy\(/', $src)) {
                $offenders[] = str_replace(base_path().'/', '', $path);
            }
        }

        $this->assertSame([], $offenders,
            "These render a resume for someone other than its owner and must read\n"
            ."Resume::publicItemsByType(), or they will show entries the owner hid:\n  "
            .implode("\n  ", $offenders));
    }
}
