<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\IntegrationConfig;
use App\Modules\User\Services\EmailConnectionMailer;
use Illuminate\Http\Request;

/**
 * First-class "SMTP Connections" management area (Task #6632): a dedicated
 * page listing all of the user's email-kind IntegrationConfig rows, with the
 * add/edit forms reusing the existing integrations CRUD and a "send test
 * email" action on top. Every feature that sends on the user's behalf (form
 * notifications, subscriber broadcasts, billing companies) links here.
 */
class EmailConnectionController extends Controller
{
    public function index(Request $request)
    {
        $connections = IntegrationConfig::query()
            ->withoutGlobalScope('workspace')
            ->where('user_id', $request->user()->id)
            ->kind('email')
            ->orderByDesc('is_default')
            ->orderBy('provider')
            ->orderByDesc('id')
            ->get();

        return view('user.email-connections.index', compact('connections'));
    }

    /**
     * Send a sample message through a connection and report the result. The
     * recipient is restricted to the owner's own account email or the
     * connection's configured from-address so this can't be abused as a spam
     * relay to arbitrary third parties.
     */
    public function test(Request $request, IntegrationConfig $integrationConfig)
    {
        abort_unless($integrationConfig->user_id === $request->user()->id, 403);
        abort_unless($integrationConfig->kind === 'email', 404);

        $data = $request->validate(['test_email' => 'required|email']);

        $allowed = array_filter(array_map(
            fn ($e) => is_string($e) ? strtolower(trim($e)) : null,
            [
                $request->user()->email,
                ((array) $integrationConfig->meta)['from_email'] ?? null,
            ]
        ));
        if (! in_array(strtolower(trim($data['test_email'])), $allowed, true)) {
            return back()->withErrors([
                'test_email' => 'Test emails can only be sent to your own account email or this connection\'s from address.',
            ])->withInput();
        }

        $res = EmailConnectionMailer::sendTest($integrationConfig, $data['test_email']);

        return $res['ok']
            ? back()->with('success', 'Test email sent to ' . $data['test_email'] . ' via "' . $integrationConfig->name . '".')
            : back()->with('error', 'Test email failed: ' . $res['error']);
    }
}
