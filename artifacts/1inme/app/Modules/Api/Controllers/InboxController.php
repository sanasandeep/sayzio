<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Read-only inbox API: lists DM threads on the user's biolinks. The web
 * inbox UI (User\InboxController) is the source of truth for the
 * underlying schema; this endpoint exposes its summary in JSON form.
 */
class InboxController extends Controller
{
    use ApiResponses;

    public function threads(Request $request)
    {
        if (!\Schema::hasTable('viewer_dm_messages')) {
            return $this->ok(['items' => []]);
        }

        $userId = $request->user()->id;
        $rows = DB::table('viewer_dm_messages as m')
            ->join('links as l', 'l.id', '=', 'm.link_id')
            ->where('l.user_id', $userId)
            ->select(
                'm.link_id',
                'm.viewer_session_id',
                DB::raw('MAX(m.id) as last_message_id'),
                DB::raw('MAX(m.created_at) as last_message_at'),
                DB::raw('COUNT(*) as message_count')
            )
            ->groupBy('m.link_id', 'm.viewer_session_id')
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get();

        return $this->ok([
            'items' => $rows->map(fn ($r) => [
                'link_id'           => (int) $r->link_id,
                'viewer_session_id' => $r->viewer_session_id,
                'last_message_id'   => (int) $r->last_message_id,
                'last_message_at'   => $r->last_message_at,
                'message_count'     => (int) $r->message_count,
            ])->all(),
        ]);
    }
}
