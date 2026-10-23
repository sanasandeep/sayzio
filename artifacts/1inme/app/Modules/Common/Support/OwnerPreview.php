<?php

namespace App\Modules\Common\Support;

use Illuminate\Http\Request;

/**
 * Is this request the owner looking at their own page, rather than a visit?
 *
 * The editor's device-preview iframe loads the ordinary public page with
 * `?_preview=1` on a relative signed URL. The signature is only ever issued by
 * {@see \App\Modules\User\Controllers\LinkController::previewUrl()}, which
 * checks ownership first, so a valid signature IS the proof of ownership.
 *
 * Four things turn on the answer, and they had each written the test out by
 * hand: analytics must not count a preview as a click, the response must not
 * be cached, unsaved draft settings are merged in before rendering, and the
 * untouched-starter guard is lifted so the owner can see the blocks they are
 * editing. Four copies of a signature check is four chances for one of them to
 * drift, so it lives here once.
 *
 * Two details the copies all got right and a fifth would be likely to miss:
 * the signature is checked RELATIVE (that is how it was signed, so the iframe
 * works whichever host the browser is on), and the params the editor appends
 * after signing are ignored.
 */
class OwnerPreview
{
    /** Params the editor appends to the signed URL after it was signed. */
    private const APPENDED_AFTER_SIGNING = ['_draft', '_t', '_sim_country', '_sim_device'];

    public static function is(?Request $request = null): bool
    {
        $request = $request ?: request();

        if (! $request instanceof Request) {
            return false;
        }

        return $request->boolean('_preview')
            && $request->hasValidSignatureWhileIgnoring(self::APPENDED_AFTER_SIGNING, false);
    }
    /** Ignore only the publication switch for a signed editor preview. */
    public static function canAccess(\App\Modules\User\Models\Link $link, Request $request): bool
    {
        if ($link->isAccessible()) return true;
        if ($link->is_active || !self::is($request)) return false;
        $preview = clone $link;
        $preview->is_active = true;
        return $preview->isAccessible();
    }
}
