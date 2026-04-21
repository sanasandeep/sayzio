<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
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
}
