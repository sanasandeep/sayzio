<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Models\ZioDigest;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;

/**
 * Public Zio Digest surfaces: the themed digest page and the signed
 * per-user email unsubscribe endpoint (GET footer link + RFC 8058
 * one-click POST).
 */
class ZioDigestController extends Controller
{
    public function show(string $slug)
    {
        $digest = ZioDigest::where('slug', $slug)->where('status', 'published')->first();
        abort_unless($digest, 404);

        return view('common.zio-digest', ['digest' => $digest, 'isPreview' => false]);
    }

    public function unsubscribe(Request $request, User $user)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'This unsubscribe link is invalid or has expired.');
        }

        if (!$user->digest_email_opt_out) {
            $user->forceFill(['digest_email_opt_out' => true])->save();

            $digestId = (int) $request->query('digest', 0);
            if ($digestId > 0) {
                ZioDigest::where('id', $digestId)->increment('unsubscribed_count');
            }
        }

        if ($request->isMethod('post')) {
            return response('', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        return response(
            '<!doctype html><html><head><meta charset="utf-8"><title>Unsubscribed</title>'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '</head><body style="font-family:Arial,Helvetica,sans-serif;background:#f8fafc;margin:0;padding:40px 16px;">'
            . '<div style="max-width:480px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:32px;text-align:center;">'
            . '<h1 style="font-size:20px;margin:0 0 12px;color:#0f172a;">You\'re unsubscribed</h1>'
            . '<p style="font-size:14px;color:#475569;margin:0;">You will no longer receive '
            . e((string) config('app.name'))
            . ' digest emails. You can opt back in any time from your notification settings.</p>'
            . '</div></body></html>',
            200,
            ['Content-Type' => 'text/html; charset=utf-8'],
        );
    }
}
