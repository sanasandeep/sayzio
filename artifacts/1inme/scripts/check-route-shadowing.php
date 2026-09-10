<?php

/**
 * Regression guard: two routes claiming the same HTTP method and URI.
 *
 * Background: routes/modules/user.php ended its /user group with a block of
 * legacy landing-URL redirects, introduced with this comment:
 *
 *     These redirects are registered LAST on purpose: Route::redirect
 *     responds to any verb, so placing them after every real POST/PUT/
 *     DELETE route guarantees the real routes win for their own methods.
 *
 * The framework does the opposite. RouteCollection::addToCollections()
 * stores each route as routes[$method][$domainAndUri] -- keyed by URI -- so
 * a later route with the same method and URI REPLACES the earlier one.
 * Registering an any-verb redirect last is precisely what makes it win.
 *
 * Six real handlers were being swallowed that way, every one of them a
 * user-facing write that answered 302 to a settings tab and saved nothing:
 *
 *     PUT    user/notifications/preferences   save notification preferences
 *     POST   user/account/two-factor          confirm/enable two-factor
 *     DELETE user/account/two-factor          disable two-factor
 *     POST   user/api-keys                    create an API key
 *     POST   user/billing/companies           add a billing company
 *     POST   user/social-accounts             connect a social account
 *
 * Nothing failed. No error surfaced. The redirect looked like success.
 *
 * The fix was to make those redirects GET-only. This guard is what stops
 * the next one: any two routes sharing a method and URI is almost always a
 * silent shadow, because only one of them can ever run.
 *
 * Mechanics: boots the application, walks the real route collection, and
 * groups by method + domain + URI. HEAD and OPTIONS are ignored -- Laravel
 * adds HEAD alongside every GET, and any-verb routes legitimately answer
 * OPTIONS. Anything left with more than one route is a collision, reported
 * with both route names so the shadowed handler is obvious.
 *
 * Adding a legit exception: append to ALLOWLIST below with a reason. There
 * is currently no such case, and a genuine one should be rare -- if two
 * routes really must share a method and URI, only the last is reachable,
 * so the earlier one should be deleted rather than allowlisted.
 *
 * Usage:
 *   php scripts/check-route-shadowing.php
 *
 * Exit codes:
 *   0  every method+URI pair is claimed by exactly one route
 *   1  at least one non-allowlisted collision
 */

declare(strict_types=1);

/**
 * Collisions that are deliberate. Key format: "METHOD uri", e.g.
 * "POST user/example". Value is the reason, shown in the output.
 *
 * @var array<string, string>
 */
const ALLOWLIST = [];

/** Verbs Laravel adds implicitly; a clash on these is not a real shadow. */
const IGNORED_METHODS = ['HEAD', 'OPTIONS'];

require __DIR__ . '/../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

/** @var \Illuminate\Routing\Router $router */
$router = $app->make('router');
$router->getRoutes()->refreshNameLookups();

/** @var array<string, list<\Illuminate\Routing\Route>> $byKey */
$byKey = [];

foreach ($router->getRoutes()->getRoutes() as $route) {
    $uri = $route->getDomain() . $route->uri();

    foreach ($route->methods() as $method) {
        if (in_array($method, IGNORED_METHODS, true)) {
            continue;
        }

        $byKey[$method . ' ' . $uri][] = $route;
    }
}

$collisions = [];

foreach ($byKey as $key => $routes) {
    if (count($routes) < 2) {
        continue;
    }

    if (array_key_exists($key, ALLOWLIST)) {
        continue;
    }

    $collisions[$key] = $routes;
}

if ($collisions === []) {
    $total = count($byKey);
    fwrite(STDOUT, "\u{2713} route shadowing guard passed \u{2014} {$total} method+URI pairs, each claimed by exactly one route.\n");
    exit(0);
}

fwrite(STDERR, "\n");
fwrite(STDERR, str_repeat('=', 78) . "\n");
fwrite(STDERR, "ROUTE SHADOWING: " . count($collisions) . " method+URI pair(s) claimed by more than one route.\n");
fwrite(STDERR, str_repeat('=', 78) . "\n\n");

fwrite(STDERR, "Laravel keys its route table by method+URI, so only the LAST route\n");
fwrite(STDERR, "registered for a pair is reachable. Every other one below is dead code\n");
fwrite(STDERR, "and its handler will never run.\n\n");

foreach ($collisions as $key => $routes) {
    fwrite(STDERR, "  {$key}\n");

    $last = count($routes) - 1;
    foreach ($routes as $i => $route) {
        $name = $route->getName() ?: '(unnamed)';
        $action = $route->getActionName();
        $verdict = $i === $last ? 'REACHABLE' : 'shadowed';
        fwrite(STDERR, sprintf("      %-9s %-46s %s\n", $verdict, substr($name, 0, 46), $action));
    }
    fwrite(STDERR, "\n");
}

fwrite(STDERR, "Most common cause: Route::redirect() / Route::permanentRedirect(),\n");
fwrite(STDERR, "which register for EVERY verb. If the intent is a legacy GET landing,\n");
fwrite(STDERR, "write it as a GET route instead:\n\n");
fwrite(STDERR, "    Route::get('old/path', static fn () => redirect('/new/path'));\n\n");
fwrite(STDERR, "If a collision is genuinely intended, delete the unreachable route\n");
fwrite(STDERR, "rather than allowlisting it -- it cannot run either way.\n\n");

exit(1);
