<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Services\NotificationService;
use App\Modules\User\Models\NotificationPreference;
use App\Modules\User\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NotificationController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        $page = UserNotification::where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 30))));

        return $this->ok([
            'items' => collect($page->items())->map(fn ($n) => [
                'id'         => $n->id,
                'type'       => $n->type ?? null,
                'title'      => $n->title ?? null,
                'body'       => $n->body ?? $n->message ?? null,
                'data'       => $n->data ?? null,
                'url'        => $n->url ?? null,
                'read_at'    => optional($n->read_at)->toIso8601String(),
                'created_at' => optional($n->created_at)->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
                'unread_count' => UserNotification::where('user_id', $request->user()->id)->whereNull('read_at')->count(),
            ],
        ]);
    }

    public function markAllRead(Request $request)
    {
        UserNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        return $this->ok(['marked_read' => true]);
    }

    public function markRead(Request $request, int $id)
    {
        $n = UserNotification::where('user_id', $request->user()->id)->find($id);
        if (!$n) return $this->notFound('Notification not found');
        if (!$n->read_at) $n->forceFill(['read_at' => now()])->save();
        return $this->ok(['marked_read' => true]);
    }

    /**
     * Return the catalog of notification types alongside the user's
     * stored preferences (or the catalog defaults when no row exists).
     * Powers the mobile preference toggle screen.
     */
    public function preferences(Request $request)
    {
        $catalog = NotificationService::catalog();
        $stored  = NotificationPreference::where('user_id', $request->user()->id)
            ->get()
            ->keyBy('type');

        $items = [];
        foreach ($catalog as $type => $meta) {
            $row = $stored->get($type);
            $items[] = [
                'type'        => $type,
                'label'       => $meta['label'],
                'description' => $meta['description'],
                'in_app'      => $row ? (bool) $row->in_app : (bool) $meta['default_in_app'],
                'email'       => $row ? (bool) $row->email  : (bool) $meta['default_email'],
                'push'        => $row ? (bool) $row->push   : (bool) $meta['default_push'],
            ];
        }

        return $this->ok(['items' => $items]);
    }

    public function updatePreferences(Request $request)
    {
        $catalog = NotificationService::catalog();
        $input   = (array) $request->input('prefs', []);

        foreach ($input as $type => $row) {
            if (!isset($catalog[$type])) continue;
            NotificationPreference::updateOrCreate(
                ['user_id' => $request->user()->id, 'type' => $type],
                [
                    'in_app' => (bool) ($row['in_app'] ?? false),
                    'email'  => (bool) ($row['email']  ?? false),
                    'push'   => (bool) ($row['push']   ?? false),
                ],
            );
        }

        return $this->preferences($request);
    }
}
