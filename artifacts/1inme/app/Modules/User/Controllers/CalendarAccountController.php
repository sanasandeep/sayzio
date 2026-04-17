<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\CalendarAccount;
use App\Modules\User\Services\Calendar\CalendarProviderRegistry;
use App\Modules\User\Services\Calendar\CalendarSyncService;
use App\Modules\User\Services\Calendar\GoogleCalendarProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CalendarAccountController extends Controller
{
    public function __construct(
        protected CalendarProviderRegistry $registry,
        protected CalendarSyncService $sync,
    ) {}

    public function index(Request $request)
    {
        $accounts = CalendarAccount::where('user_id', $request->user()->id)
            ->orderByDesc('id')->get();

        $googleConfigured = (new GoogleCalendarProvider())->isConfigured();

        return view('user.settings.calendar', [
            'accounts'         => $accounts,
            'googleConfigured' => $googleConfigured,
        ]);
    }

    public function connect(Request $request, string $provider)
    {
        if ($provider === 'microsoft' || $provider === 'caldav') {
            return back()->with('error', ucfirst($provider) . ' integration is coming soon.');
        }

        try {
            $driver = $this->registry->get($provider);
        } catch (\Throwable $e) {
            return back()->with('error', 'Unknown provider.');
        }

        $state = Str::random(40);
        $request->session()->put('calendar_oauth_state', [
            'state'    => $state,
            'provider' => $provider,
            'user_id'  => $request->user()->id,
        ]);

        $redirect = route('user.calendar.callback', ['provider' => $provider]);
        return redirect()->away($driver->authorizationUrl($state, $redirect));
    }

    public function callback(Request $request, string $provider)
    {
        $state = $request->query('state');
        $code  = $request->query('code');
        $err   = $request->query('error');

        $stored = $request->session()->pull('calendar_oauth_state');
        if (!$stored || $stored['state'] !== $state || $stored['provider'] !== $provider) {
            return redirect()->route('user.calendar.index')->with('error', 'Connection request expired or invalid. Please try again.');
        }
        if ($err || !$code) {
            return redirect()->route('user.calendar.index')->with('error', 'Authorization was cancelled or denied.');
        }

        try {
            $driver  = $this->registry->get($provider);
            $account = $driver->exchangeCode($stored['user_id'], $code, route('user.calendar.callback', ['provider' => $provider]));
            // Kick off an initial sync so the user sees events right away.
            $this->sync->syncAccount($account);
            return redirect()->route('user.calendar.index')->with('success', 'Calendar connected — your upcoming events are syncing now.');
        } catch (\Throwable $e) {
            Log::error('Calendar callback failed', ['err' => $e->getMessage()]);
            return redirect()->route('user.calendar.index')->with('error', 'Could not connect: ' . $e->getMessage());
        }
    }

    public function syncNow(Request $request, CalendarAccount $account)
    {
        abort_if($account->user_id !== $request->user()->id, 403);
        $stats = $this->sync->syncAccount($account);
        $msg = "Synced — {$stats['created']} new, {$stats['updated']} updated, {$stats['deleted']} removed.";
        if ($stats['errors']) $msg .= " ({$stats['errors']} errors — see logs.)";
        return back()->with('success', $msg);
    }

    public function update(Request $request, CalendarAccount $account)
    {
        abort_if($account->user_id !== $request->user()->id, 403);
        $account->update([
            'mirror_enabled' => $request->boolean('mirror_enabled'),
            'push_enabled'   => $request->boolean('push_enabled'),
            'display_name'   => $request->input('display_name', $account->display_name),
        ]);
        return back()->with('success', 'Calendar settings updated.');
    }

    public function destroy(Request $request, CalendarAccount $account)
    {
        abort_if($account->user_id !== $request->user()->id, 403);

        // Optionally delete mirrored Event Invite links (keep by default — user may want them).
        if ($request->boolean('purge_mirrored')) {
            foreach ($account->mirrors()->where('source', 'pull')->with('link')->get() as $m) {
                if ($m->link) $m->link->delete();
            }
        }
        $account->delete();
        return redirect()->route('user.calendar.index')->with('success', 'Calendar disconnected.');
    }
}
