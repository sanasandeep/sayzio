<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Tests\TestCase;

/**
 * Regression guard for the `/{alias}` catch-all route (web.php ~L493-494).
 *
 * The reserved-word negative-lookahead once listed bare single-letter
 * tokens (`u`, `p`, `c`, `m`, `f`) WITHOUT the trailing `(?:/|$)` anchor,
 * so any auto-generated short-link / biolink alias that merely *started*
 * with one of those letters (about 1 in 12) failed to match the GET
 * catch-all and fell through to a sibling POST route → HTTP 405 instead
 * of resolving. The fix anchors every reserved token with `(?:/|$)` so a
 * token only reserves an *exact* segment or a prefixed path, never a
 * same-prefix alias.
 *
 * This test pins that behaviour at two levels:
 *  - Route resolution: GET `/{alias}` for reserved-letter-prefixed and
 *    normal aliases must resolve to the `redirect.handle` route (a 405 or
 *    404 here is exactly the regression), while exact reserved words and
 *    reserved-prefix paths must NOT be captured by the catch-all.
 *  - End-to-end: a real short link whose alias starts with each reserved
 *    letter actually redirects to its destination (not 405).
 */
class AliasCatchAllReservedPrefixTest extends TestCase
{
    use RefreshDatabase;

    /** Reserved single letters that previously over-matched. */
    private const RESERVED_LETTERS = ['u', 'p', 'c', 'm', 'f'];

    /**
     * Resolve a request against the route table without booting any
     * middleware/controllers. Returns the matched route name, or null when
     * routing rejects it (NotFound = 404, MethodNotAllowed = 405). Both
     * rejections are "did not match the GET catch-all", which is what the
     * regression produced for valid aliases.
     */
    private function matchedRouteName(string $path, string $method = 'GET'): ?string
    {
        $request = Request::create($path, $method);

        try {
            return Route::getRoutes()->match($request)->getName();
        } catch (MethodNotAllowedHttpException | ResourceNotFoundException $e) {
            return null;
        }
    }

    public function test_alias_starting_with_each_reserved_letter_matches_the_catch_all(): void
    {
        foreach (self::RESERVED_LETTERS as $letter) {
            $alias = $letter . Str::lower(Str::random(6));

            $this->assertSame(
                'redirect.handle',
                $this->matchedRouteName('/' . $alias),
                "GET /{$alias} (reserved-letter prefix) must resolve to the alias "
                . 'catch-all, not 405/404. The reserved token over-matched again.'
            );
        }
    }

    public function test_normal_alias_matches_the_catch_all(): void
    {
        $this->assertSame('redirect.handle', $this->matchedRouteName('/' . Str::lower(Str::random(7))));
        $this->assertSame('redirect.handle', $this->matchedRouteName('/abc1234'));
    }

    public function test_exact_reserved_words_stay_reserved(): void
    {
        // Each of these has its own route and must never be swallowed by the
        // single-segment alias catch-all.
        foreach (['pricing', 'features', 'login', 'register', 'about', 'contact', 'docs', 'blogs'] as $word) {
            $this->assertNotSame(
                'redirect.handle',
                $this->matchedRouteName('/' . $word),
                "GET /{$word} must stay a reserved page, not resolve as a short-link alias."
            );
        }
    }

    public function test_reserved_prefix_paths_stay_reserved(): void
    {
        // Multi-segment paths under reserved prefixes (`/user`, `/admin`,
        // `/api`, `/f/...`, `/u|p|c|m/.../report`) must not be captured by the
        // single-segment `/{alias}` catch-all.
        $this->assertNotSame('redirect.handle', $this->matchedRouteName('/user/dashboard'));
        $this->assertNotSame('redirect.handle', $this->matchedRouteName('/admin'));
        $this->assertNotSame('redirect.handle', $this->matchedRouteName('/api/v1/me'));

        // `/f/{slug}` is the public form route, not an alias.
        $this->assertSame('forms.public.show', $this->matchedRouteName('/f/some-form-slug'));

        // The single-letter report endpoints are POST-only; a same-prefix
        // multi-segment GET must not leak into the alias catch-all.
        foreach (['u/123/report', 'p/123/report', 'c/123/report', 'm/123/report'] as $path) {
            $this->assertNotSame(
                'redirect.handle',
                $this->matchedRouteName('/' . $path),
                "GET /{$path} must stay under its reserved prefix, not the alias catch-all."
            );
        }
    }

    public function test_short_link_with_reserved_letter_prefix_actually_redirects(): void
    {
        $user = User::create([
            'name'     => 'Owner ' . Str::random(4),
            'email'    => 'own' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);

        foreach (self::RESERVED_LETTERS as $letter) {
            $alias = $letter . Str::lower(Str::random(6));
            $destination = "https://destination.example.com/{$letter}";

            Link::create([
                'user_id'   => $user->id,
                'domain_id' => null,
                'type'      => 'url',
                'alias'     => $alias,
                'long_url'  => $destination,
                'is_active' => true,
                // Skip the app-opener interstitial so a plain GET redirects.
                'settings'  => ['open_in_app' => false],
            ]);

            $response = $this->get('/' . $alias);

            $this->assertNotSame(
                405,
                $response->getStatusCode(),
                "GET /{$alias} returned 405 — the reserved-prefix over-match regressed."
            );
            $response->assertRedirect($destination);
        }
    }
}
