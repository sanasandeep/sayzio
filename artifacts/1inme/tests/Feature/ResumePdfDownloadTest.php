<?php

namespace Tests\Feature;

use App\Modules\User\Models\Resume;
use App\Modules\User\Models\User;
use App\Modules\User\Services\ResumeColorThemeRegistry;
use App\Modules\User\Services\ResumePdfRenderer;
use App\Modules\User\Services\ResumeTemplateRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * End-to-end coverage for the resume PDF download.
 *
 * Verifies the HTTP surface (auth, ownership, throttle, content type)
 * and the renderer's invariants across every template + color theme so
 * that future template/theme changes can't silently break export.
 */
class ResumePdfDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $tag = 'u', ?string $handle = null): User
    {
        $u = User::create([
            'name'     => $tag.' '.Str::random(4),
            'email'    => $tag.Str::random(8).'@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'handle'   => $handle ?? ($tag.Str::random(6)),
        ]);
        // Make sure the resume row exists so the renderer has something
        // to chew on. The controller would do this lazily anyway.
        $u->ensureResume();
        return $u;
    }

    public function test_owner_can_download_a4_pdf(): void
    {
        $user = $this->makeUser('owner');

        $res = $this->actingAs($user)->get('/user/resume/download.pdf?size=a4');

        $res->assertOk();
        $res->assertHeader('Content-Type', 'application/pdf');
        $res->assertHeader('X-Resume-Paper-Size', ResumePdfRenderer::SIZE_A4);
        $body = $res->getContent();
        $this->assertStringStartsWith('%PDF-', $body, 'body must be a valid PDF');
        $this->assertGreaterThan(100, strlen($body), 'PDF must not be empty');
    }

    public function test_owner_can_download_letter_pdf(): void
    {
        $user = $this->makeUser('owner');

        $res = $this->actingAs($user)->get('/user/resume/download.pdf?size=letter');

        $res->assertOk();
        $res->assertHeader('Content-Type', 'application/pdf');
        $res->assertHeader('X-Resume-Paper-Size', ResumePdfRenderer::SIZE_LETTER);
        $body = $res->getContent();
        $this->assertStringStartsWith('%PDF-', $body);
    }

    public function test_unauthenticated_download_redirects_to_login(): void
    {
        $res = $this->get('/user/resume/download.pdf');

        $res->assertStatus(302);
        $location = $res->headers->get('Location') ?? '';
        $this->assertStringContainsString('login', $location,
            'unauthenticated PDF download must redirect to login, got '.$location);
    }

    public function test_handle_url_returns_404_for_non_owner(): void
    {
        $owner   = $this->makeUser('owner', 'owner'.Str::random(5));
        $visitor = $this->makeUser('visitor', 'visitor'.Str::random(5));

        $res = $this->actingAs($visitor)->get('/'.$owner->handle.'/resume.pdf');
        $res->assertNotFound();
    }

    public function test_handle_url_redirects_to_login_when_unauthenticated(): void
    {
        // The route ships with the `auth` middleware, so unauthenticated
        // hits redirect to login *before* the controller's owner check
        // would 404. We assert the redirect explicitly so any future
        // change to the middleware stack (e.g. swapping to a 404-only
        // guard) shows up here instead of silently changing UX.
        $owner = $this->makeUser('owner', 'owner'.Str::random(5));

        $res = $this->get('/'.$owner->handle.'/resume.pdf');
        $res->assertStatus(302);
        $this->assertStringContainsString('login', (string) $res->headers->get('Location'));
    }

    public function test_owner_can_download_via_handle_url(): void
    {
        $owner = $this->makeUser('owner', 'owner'.Str::random(5));

        $res = $this->actingAs($owner)->get('/'.$owner->handle.'/resume.pdf');
        $res->assertOk();
        $res->assertHeader('Content-Type', 'application/pdf');
        $body = $res->getContent();
        $this->assertStringStartsWith('%PDF-', $body);
    }

    public function test_rate_limiter_trips_at_configured_threshold(): void
    {
        // Route is throttled at 20 req / minute. The throttle key is
        // derived from the authenticated user; a fresh user per test
        // method gives us a clean budget without poking RateLimiter.
        $user = $this->makeUser('rate');

        // 20 successful requests, then the 21st must trip the limiter.
        // We disable mid-test middleware-only by verifying status codes.
        for ($i = 1; $i <= 20; $i++) {
            $res = $this->actingAs($user)->get('/user/resume/download.pdf');
            $this->assertSame(200, $res->getStatusCode(),
                "request #$i should succeed within the throttle budget");
        }

        $tripped = $this->actingAs($user)->get('/user/resume/download.pdf');
        $this->assertSame(429, $tripped->getStatusCode(),
            'request #21 must be rejected by the throttle middleware');
    }

    /**
     * Renderer smoke test — every (template × theme) combination must
     * produce a non-empty PDF whose body starts with `%PDF-`. This is
     * the safety net for template/theme additions: a broken Blade
     * partial or palette token would surface here even before any HTTP
     * test exercises the route.
     *
     */
    #[DataProvider('templateAndThemeProvider')]
    public function test_renderer_produces_pdf_for_every_template_and_theme(
        string $templateId,
        string $themeId,
    ): void {
        $user = $this->makeUser('smoke');
        $resume = $user->ensureResume();
        $resume->update([
            'template_id'    => $templateId,
            'color_theme_id' => $themeId,
        ]);
        $resume->refresh()->loadMissing('items');

        /** @var ResumePdfRenderer $renderer */
        $renderer = app(ResumePdfRenderer::class);

        // Both paper sizes are exercised by the HTTP feature tests
        // above; here we only need to prove the (template × theme)
        // matrix renders cleanly, so we stick to A4 to keep the
        // 24-combo provider tractable.
        $out = $renderer->render($resume, $user, ResumePdfRenderer::SIZE_A4);
        $this->assertSame(ResumePdfRenderer::SIZE_A4, $out['size']);
        $this->assertStringEndsWith('.pdf', $out['filename']);
        $this->assertStringStartsWith('%PDF-', $out['body'],
            "template=$templateId theme=$themeId must produce a PDF");
        $this->assertGreaterThan(100, strlen($out['body']),
            "template=$templateId theme=$themeId must not be empty");
    }

    /** @return iterable<string, array{0:string,1:string}> */
    public static function templateAndThemeProvider(): iterable
    {
        foreach (ResumeTemplateRegistry::ids() as $tplId) {
            foreach (array_column(ResumeColorThemeRegistry::all(), 'id') as $themeId) {
                yield "$tplId+$themeId" => [$tplId, $themeId];
            }
        }
    }
}
