{{--
    Resilient error-page renderer.

    Renders the rich, branded error view (errors/_site-error, which extends the
    full site layout and depends on the database + Vite asset manifest). In
    production, if that rich view throws while rendering — the very situation
    that often *causes* the error, e.g. the DB is unreachable or the Vite
    manifest is missing — we swallow the failure and emit the bulletproof,
    dependency-free errors/_fallback page instead, so a live visitor never sees
    a raw stack trace or framework debug screen.

    In development we deliberately let the rich view throw so Laravel surfaces
    the real reason the error page failed (full debug output stays intact).

    Inputs:
      - $statusCode (int)             HTTP status, e.g. 404
      - $slug (string|null)           SitePage slug, defaults to "error-{code}"
      - $suggestions (array|null)     optional 404 path suggestions
--}}
@php
    $__statusCode  = (int) ($statusCode ?? 500);

    // Production diagnosability: the view-level error net below can swallow
    // the real exception entirely, leaving deployment logs empty for a 500.
    // Laravel passes the original Throwable to error views as $exception —
    // emit a one-line summary (class, message, file:line, request path) to
    // stderr so it always lands in the deployment logs. No stack trace, no
    // secrets/PII beyond the exception message itself.
    if (app()->environment('production') && isset($exception) && $exception instanceof \Throwable) {
        try {
            $__summary = sprintf(
                '[error-page %d] %s: %s at %s:%d (path: %s)',
                $__statusCode,
                get_class($exception),
                \Illuminate\Support\Str::limit((string) $exception->getMessage(), 300),
                $exception->getFile(),
                $exception->getLine(),
                request()?->path() ?? 'unknown'
            );
            @file_put_contents('php://stderr', $__summary . PHP_EOL);
        } catch (\Throwable $__ignore) {}
    }
    $__slug        = $slug ?? ('error-' . $__statusCode);
    $__suggestions = $suggestions ?? [];

    $__renderRich = function () use ($__slug, $__statusCode, $__suggestions) {
        $page = \App\Modules\Common\Models\SitePage::resolveErrorPage($__slug);

        return view('errors._site-error', [
            'page'        => $page,
            'statusCode'  => $__statusCode,
            'suggestions' => $__suggestions,
        ])->render();
    };

    if (app()->environment('production')) {
        try {
            echo $__renderRich();
        } catch (\Throwable $__e) {
            // Log why the rich page failed, but never let logging itself break
            // the last-resort response.
            try { report($__e); } catch (\Throwable $__ignore) {}

            echo view('errors._fallback', ['statusCode' => $__statusCode])->render();
        }
    } else {
        // Development: do not mask rendering failures — let them bubble up so
        // the developer sees the real cause via the normal debug screen.
        echo $__renderRich();
    }
@endphp
