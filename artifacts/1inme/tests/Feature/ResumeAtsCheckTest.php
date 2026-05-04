<?php

namespace Tests\Feature;

use App\Modules\User\Models\Resume;
use App\Modules\User\Models\ResumeSectionItem;
use App\Modules\User\Models\User;
use App\Modules\User\Services\ResumeAtsChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the ATS-readiness checker + its HTTP surface.
 */
class ResumeAtsCheckTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $u = User::create([
            'name'     => 'A '.Str::random(4),
            'email'    => 'a'.Str::random(8).'@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'handle'   => 'h'.Str::random(6),
        ]);
        $u->ensureResume();
        return $u;
    }

    public function test_empty_resume_fails_required_checks(): void
    {
        $user   = $this->makeUser();
        $resume = $user->ensureResume();

        $report = ResumeAtsChecker::check($resume);

        $this->assertSame('fail', $report['overall_status']);
        $this->assertTrue($report['has_unresolved']);
        $byId = collect($report['checks'])->keyBy('id');
        $this->assertSame('fail', $byId['contact_email']['status']);
        $this->assertSame('fail', $byId['section_experience']['status']);
        $this->assertSame('warn', $byId['section_education']['status']);
        $this->assertSame('warn', $byId['contact_phone']['status']);
        $this->assertArrayNotHasKey('keywords', $report);
    }

    public function test_well_formed_resume_passes(): void
    {
        $user   = $this->makeUser();
        $resume = $user->ensureResume();
        $resume->update([
            'template_id' => 'classic',
            'sections' => array_replace_recursive(Resume::defaultSections(), [
                'header' => [
                    'name' => 'Jane Doe', 'headline' => 'Engineer',
                    'email' => 'jane@example.com', 'phone' => '+1 555 0100',
                ],
                'summary' => str_repeat('Experienced engineer with a passion for shipping. ', 12),
            ]),
        ]);
        ResumeSectionItem::create([
            'resume_id' => $resume->id, 'section_type' => 'experience', 'position' => 1,
            'data' => ['company' => 'Acme', 'role' => 'Engineer',
                       'description' => str_repeat('Built things. ', 20)],
        ]);
        ResumeSectionItem::create([
            'resume_id' => $resume->id, 'section_type' => 'education', 'position' => 1,
            'data' => ['school' => 'MIT', 'degree' => 'BSc', 'field' => 'CS'],
        ]);

        $report = ResumeAtsChecker::check($resume->fresh('items'));

        $this->assertSame('pass', $report['overall_status']);
        $this->assertFalse($report['has_unresolved']);
        $this->assertSame(0, $report['fail_count']);
    }

    public function test_keyword_coverage_when_target_role_provided(): void
    {
        $user   = $this->makeUser();
        $resume = $user->ensureResume();
        $resume->update([
            'sections' => array_replace_recursive(Resume::defaultSections(), [
                'header'  => ['name' => 'X', 'email' => 'x@y.co', 'phone' => '5550100'],
                'summary' => 'Senior React engineer experienced with TypeScript and PostgreSQL.',
            ]),
        ]);
        ResumeSectionItem::create([
            'resume_id' => $resume->id, 'section_type' => 'experience', 'position' => 1,
            'data' => ['company' => 'A', 'role' => 'Engineer', 'description' => 'Built dashboards.'],
        ]);

        $report = ResumeAtsChecker::check($resume->fresh('items'), [
            'target_role' => 'Looking for a React engineer with TypeScript, GraphQL and Kubernetes experience.',
        ]);

        $this->assertArrayHasKey('keywords', $report);
        $this->assertGreaterThan(0, $report['keywords']['coverage_pct']);
        $this->assertContains('react', $report['keywords']['matched']);
        $this->assertContains('typescript', $report['keywords']['matched']);
        $this->assertContains('graphql', $report['keywords']['missing']);
        $this->assertContains('kubernetes', $report['keywords']['missing']);
    }

    public function test_endpoint_requires_auth_and_returns_report(): void
    {
        $this->post('/user/resume/ats-check')->assertRedirect(); // unauth → login

        $user = $this->makeUser();
        $res  = $this->actingAs($user)->postJson('/user/resume/ats-check', [
            'target_role' => 'PHP Laravel developer',
        ]);

        $res->assertOk();
        $res->assertJsonStructure(['report' => [
            'overall_status', 'pass_count', 'warn_count', 'fail_count',
            'has_unresolved', 'keywords' => ['coverage_pct', 'matched', 'missing', 'total'],
            'checks' => [['id', 'label', 'status', 'message', 'link']],
        ]]);
    }
}
