<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\GoogleContactsAccount;
use App\Modules\User\Services\Contacts\GoogleContactsProvider;
use App\Modules\User\Services\Contacts\GoogleContactsSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleContactsAccountController extends Controller
{
    public function __construct(
        protected GoogleContactsProvider $provider,
        protected GoogleContactsSyncService $sync,
    ) {}

    public function connect(Request $request)
    {
        $state = Str::random(40);
        $request->session()->put('google_contacts_oauth_state', [
            'state'   => $state,
            'user_id' => $request->user()->id,
        ]);
        try {
            $url = $this->provider->authorizationUrl($state, route('user.contacts.google.callback'));
        } catch (\Throwable $e) {
            return redirect()->route('user.contacts.index')->with('error', $e->getMessage());
        }
        return redirect()->away($url);
    }

    public function callback(Request $request)
    {
        $state = $request->query('state');
        $code  = $request->query('code');
        $err   = $request->query('error');

        $stored = $request->session()->pull('google_contacts_oauth_state');
        if (!$stored || $stored['state'] !== $state) {
            return redirect()->route('user.contacts.index')->with('error', 'Connection request expired or invalid.');
        }
        if ($err || !$code) {
            return redirect()->route('user.contacts.index')->with('error', 'Authorization was cancelled.');
        }
        try {
            $account = $this->provider->exchangeCode($stored['user_id'], $code, route('user.contacts.google.callback'));
            $stats = $this->sync->syncAccount($account);
            if (!empty($stats['skipped_capped'])) {
                return redirect()->route('user.contacts.index')->with('error', "Google Contacts connected, but {$stats['skipped_capped']} contact(s) were not imported because you've reached your plan's contact limit. Upgrade your plan to import the rest.");
            }
            return redirect()->route('user.contacts.index')->with('success', 'Google Contacts connected — syncing now.');
        } catch (\Throwable $e) {
            Log::error('Google contacts connect failed', ['err' => $e->getMessage()]);
            return redirect()->route('user.contacts.index')->with('error', 'Could not connect: ' . $e->getMessage());
        }
    }

    public function syncNow(Request $request, GoogleContactsAccount $account)
    {
        abort_if($account->user_id !== $request->user()->id, 403);

        $result = $this->sync->syncNow($account);

        if ($result['status'] === 'throttled') {
            return back()->with('info', "Already up to date — you just synced. Try again in {$result['retry_after']}s.");
        }
        if ($result['status'] === 'in_progress') {
            return back()->with('info', 'A sync is already running — give it a few seconds.');
        }

        $stats = $result['stats'];
        $msg = "Synced — +{$stats['created']} ~{$stats['updated']} -{$stats['deleted']} pushed {$stats['pushed']}";
        if (!empty($stats['skipped_capped'])) {
            return back()->with('error', $msg . " — {$stats['skipped_capped']} contact(s) were not imported because you've reached your plan's contact limit. Upgrade your plan to import the rest.");
        }
        if ($stats['errors']) $msg .= " ({$stats['errors']} errors — see logs.)";
        return back()->with('success', $msg);
    }

    public function destroy(Request $request, GoogleContactsAccount $account)
    {
        abort_if($account->user_id !== $request->user()->id, 403);
        $account->delete();
        return redirect()->route('user.contacts.index')->with('success', 'Google Contacts disconnected. Existing contacts kept.');
    }
}
