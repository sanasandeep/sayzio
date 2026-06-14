<?php

namespace App\Modules\User\Concerns;

use Illuminate\Http\Request;

/**
 * Shared upload-failure response helper for every controller that accepts
 * file uploads (File Share, link favicon/SEO images, biolink page assets).
 *
 * Automation / API clients that send an Accept: application/json (or
 * X-Requested-With: XMLHttpRequest) header get a structured
 * {error: {message}} envelope with an appropriate HTTP status instead of an
 * HTML redirect-back-with-flash, which they cannot follow. Browser form
 * posts keep the original redirect-back behaviour so the inline flash error
 * still renders on the form.
 */
trait RespondsWithUploadErrors
{
    protected function uploadError(Request $request, string $message, int $status = 422)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['error' => ['message' => $message]], $status);
        }

        return back()->withInput()->with('error', $message);
    }
}
