<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Sign-in handshake page for the 1INME browser extension.
 *
 * The extension opens https://1inme.com/extension/handshake in a new
 * tab. If the user isn't signed in, they're sent through the normal
 * login flow (intended-redirect handles the bounce-back). Once signed
 * in, this view embeds a freshly-issued Sanctum token + minimal user
 * payload in a JSON <script> tag that the extension's content script
 * (content-handshake.js, matched on this URL) reads via
 * browser.runtime.sendMessage and persists in browser.storage.local.
 *
 * The token is created with a "browser-extension" device label so the
 * user can revoke it from the dashboard like any other device session.
 */
class ExtensionHandshakeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function show(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->guest(route('login'));
        }

        $token = $user->createToken('browser-extension')->plainTextToken;

        $workspaces = [];
        try {
            if (class_exists(Workspace::class)) {
                $workspaces = Workspace::query()
                    ->where('owner_id', $user->id)
                    ->orWhereHas('members', fn ($q) => $q->where('user_id', $user->id))
                    ->get(['id', 'name'])
                    ->map(fn ($w) => ['id' => (int) $w->id, 'name' => (string) $w->name])
                    ->all();
            }
        } catch (\Throwable $e) {
            // Workspaces are optional — older installs may not have them.
            $workspaces = [];
        }

        $payload = [
            'token' => $token,
            'user'  => [
                'id'     => (int) $user->id,
                'name'   => (string) ($user->name ?? ''),
                'email'  => (string) ($user->email ?? ''),
                'handle' => $user->handle ?? null,
            ],
            'workspaces' => $workspaces,
        ];

        return view('user.extension-handshake', ['payload' => $payload]);
    }
}
