<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Services\EventPageExtractor;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Server-side "detect event details" endpoint for Add-to-Calendar flows.
 *
 * The browser extension scrapes the page in-tab via a content script;
 * the mobile app can't do that, so it calls this endpoint instead. The
 * extraction (JSON-LD Event → microdata → og:type=event → <title>)
 * mirrors the extension's content-event-extract.ts exactly, just run
 * against a server-side fetch of the shared URL.
 *
 * Best-effort by design: any fetch/parse problem returns 422 with a
 * human message, and callers fall back to their manual fields.
 */
class EventExtractController extends Controller
{
    use ApiResponses;

    public function extract(Request $request, EventPageExtractor $extractor)
    {
        $data = $request->validate([
            'url' => 'required|string|max:2048',
        ]);

        try {
            return $this->ok(['event' => $extractor->extractFromUrl($data['url'])]);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422, 'extract_failed');
        }
    }
}
