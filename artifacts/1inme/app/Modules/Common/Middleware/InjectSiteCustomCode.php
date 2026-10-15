<?php

namespace App\Modules\Common\Middleware;

use App\Modules\Common\Support\SiteCustomCode;
use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class InjectSiteCustomCode
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse || $response->getStatusCode() !== 200
            || str_contains(strtolower($response->headers->get('Content-Disposition', '')), 'attachment')
            || $request->is('admin', 'admin/*', 'api', 'api/*') || $request->expectsJson()
            || ! str_contains(strtolower($response->headers->get('Content-Type', '')), 'text/html')) {
            return $response;
        }
        $html = $response->getContent();
        if (! is_string($html) || stripos($html, '<html') === false || stripos($html, '</head>') === false || stripos($html, '</body>') === false) {
            return $response;
        }
        $original = method_exists($response, 'getOriginalContent') ? $response->getOriginalContent() : null;
        $view = $original instanceof View ? $original->name() : '';
        $area = null;
        if ($request->is('user', 'user/*') && $request->user() && collect($request->route()?->gatherMiddleware() ?? [])->contains(fn ($middleware) => is_string($middleware) && ($middleware === 'auth' || str_starts_with($middleware, 'auth:') || $middleware === Authenticate::class))) {
            $area = 'dashboard';
        } elseif (in_array($view, ['home', 'home-b'], true) || $request->routeIs('site.*', 'creators.index', 'events.index', 'android.show')) {
            $area = 'marketing';
        }
        if ($area !== null) {
            $updated = SiteCustomCode::inject($html, $area);
            if ($updated !== $html) {
                $response->setContent($updated);
                $response->headers->remove('Content-Length');
                $response->headers->remove('ETag');
            }
        }

        return $response;
    }
}
