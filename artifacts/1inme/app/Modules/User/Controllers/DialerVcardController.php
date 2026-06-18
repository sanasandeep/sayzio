<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\User;
use App\Modules\User\Support\DialerIdentity;
use App\Modules\User\Support\DialerVcard;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams a shareable .vcf for a Dialer identity. The route is signed (not
 * auth-gated) so the owner can hand the URL to anyone; the signature is the
 * only authorization and the `u` param scopes resolution to that owner.
 */
class DialerVcardController extends Controller
{
    public function show(Request $request)
    {
        // Signature is enforced by the `signed` middleware on the route.
        $owner = User::find((int) $request->query('u'));
        if (!$owner) {
            return response('Not found', Response::HTTP_NOT_FOUND);
        }

        $contactId = $request->query('contact');
        $number = trim((string) ($request->query('number') ?? ''));

        $resolved = DialerIdentity::resolve($owner, $contactId ? (int) $contactId : null, $number);

        /** @var ?Contact $contact */
        $contact = $resolved['contact'];
        if (!$contact && !$resolved['number']) {
            return response('Nothing to export', Response::HTTP_NOT_FOUND);
        }

        $vcf = DialerVcard::build(
            $owner,
            $contact,
            (string) $resolved['number'],
            $resolved['matchedUser'],
            $resolved['bio'],
        );
        $filename = DialerVcard::filename($contact, $resolved['matchedUser'], (string) $resolved['number']);

        return response($vcf, Response::HTTP_OK, [
            'Content-Type'        => 'text/vcard; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
