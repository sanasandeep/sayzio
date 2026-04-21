<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\CloudConnection;
use App\Modules\User\Models\CloudProviderApp;
use Illuminate\Http\Request;

class CloudConnectionController extends Controller
{
    public function index(Request $request)
    {
        $connections = CloudConnection::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('provider')
            ->get();

        $apps = CloudProviderApp::query()->get()->keyBy('provider');

        return view('user.cloud-files.connections', compact('connections', 'apps'));
    }

    public function destroy(Request $request, CloudConnection $connection)
    {
        abort_unless($connection->user_id === $request->user()->id, 403);
        $connection->delete();
        return back()->with('success', 'Disconnected.');
    }

    /**
     * Dismiss the in-app "broken connection" banner for a single connection.
     * The banner re-arms automatically if the connection breaks AGAIN later
     * (last_error_at advances past banner_dismissed_at), so this is purely a
     * "I've seen it, hide it for now" action — not an opt-out.
     */
    public function dismissBanner(Request $request, CloudConnection $connection)
    {
        abort_unless($connection->user_id === $request->user()->id, 403);
        $connection->forceFill(['banner_dismissed_at' => now()])->save();
        return back();
    }
}
