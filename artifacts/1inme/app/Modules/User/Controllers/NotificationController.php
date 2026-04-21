<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\NotificationService;
use App\Modules\User\Models\NotificationPreference;
use App\Modules\User\Models\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = UserNotification::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate(30);
        return view('user.notifications.index', compact('notifications'));
    }

    public function markRead(Request $request)
    {
        UserNotification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        return back()->with('success', 'Notifications marked read.');
    }

    public function preferences()
    {
        $catalog = NotificationService::catalog();
        $prefs   = NotificationPreference::where('user_id', auth()->id())
            ->get()
            ->keyBy('type')
            ->map(fn ($r) => [
                'in_app' => (bool) $r->in_app,
                'email'  => (bool) $r->email,
                'push'   => (bool) $r->push,
            ])
            ->all();

        return view('user.notifications.preferences', compact('catalog', 'prefs'));
    }

    public function updatePreferences(Request $request)
    {
        $catalog = NotificationService::catalog();
        $input   = (array) $request->input('prefs', []);

        foreach ($catalog as $type => $meta) {
            $row = $input[$type] ?? [];
            NotificationPreference::updateOrCreate(
                ['user_id' => auth()->id(), 'type' => $type],
                [
                    'in_app' => (bool) (int) ($row['in_app'] ?? 0),
                    'email'  => (bool) (int) ($row['email']  ?? 0),
                    'push'   => (bool) (int) ($row['push']   ?? 0),
                ],
            );
        }

        return back()->with('success', 'Preferences saved.');
    }
}
