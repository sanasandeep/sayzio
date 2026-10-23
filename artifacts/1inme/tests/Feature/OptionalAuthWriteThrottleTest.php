<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class OptionalAuthWriteThrottleTest extends TestCase
{
    public function test_sensitive_optional_auth_writes_enforce_their_limits(): void
    {
        $cases = [
            ['POST', '/me/creator/orders/1/fulfill', 30],
            ['POST', '/creators/example/my-subscription/cancel', 30],
            ['POST', '/creators/example/my-subscription/resume', 30],
            ['PATCH', '/me/creator-profile', 30],
            ['POST', '/me/contact-exchanges/1/accept', 30],
            ['POST', '/me/contact-exchanges/1/decline', 30],
            ['POST', '/me/contact-exchanges/1/cancel', 30],
            ['POST', '/links/1/event-tiers', 30],
            ['PATCH', '/links/1/event-tiers/1', 30],
            ['DELETE', '/links/1/event-tiers/1', 30],
            ['POST', '/links/1/event-tickets/1/refund', 10],
        ];

        foreach ($cases as $index => [$method, $path, $limit]) {
            $request = Request::create('/api/v1'.$path, $method, server: [
                'REMOTE_ADDR' => '192.0.2.'.($index + 1),
            ]);
            $route = app('router')->getRoutes()->match($request);
            $request->setRouteResolver(fn () => $route);
            $middleware = $route->gatherMiddleware();
            $this->assertContains('api.optional_auth', $middleware, $path);
            $this->assertContains("throttle:{$limit},1", $middleware, $path);

            // Exercise the limiter without controller side effects or a database.
            $throttle = app(ThrottleRequests::class);
            $next = fn () => response()->json(['ok' => true]);
            for ($attempt = 0; $attempt < $limit; $attempt++) {
                $this->assertSame(200, $throttle->handle($request, $next, $limit, 1)->getStatusCode());
            }
            try {
                $throttle->handle($request, $next, $limit, 1);
                $this->fail('Rate limit was not enforced for '.$path);
            } catch (ThrottleRequestsException $exception) {
                $this->assertSame(429, $exception->getStatusCode());
                $this->assertGreaterThan(0, $exception->getHeaders()['Retry-After']);
            }
        }
    }
}
