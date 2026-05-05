<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\ViewerSession;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserBlock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Viewer-side "block this creator" toggle (Task #1211). Posted from
 * the kebab menu on /@handle and on each post card. The block is
 * idempotent — re-toggling removes the block, mirroring how
 * Twitter/Bluesky handle the same action.
 */
class UserBlockController extends Controller
{
    public function toggle(Request $request, int $creator)
    {
        $viewer = ViewerSession::user() ?? auth()->user();
        if (!$viewer) {
            return response()->json(['success' => false, 'message' => 'Sign in to block creators.'], 401);
        }
        if ((int) $viewer->id === (int) $creator) {
            return response()->json(['success' => false, 'message' => "You can't block yourself."], 422);
        }
        $creatorRow = User::find($creator);
        if (!$creatorRow) return response()->json(['success' => false], 404);

        $reason = $request->input('reason');
        if ($reason !== null) $reason = mb_substr((string) $reason, 0, 60);

        $blocked = false;
        DB::transaction(function () use ($viewer, $creator, $reason, &$blocked) {
            $existing = UserBlock::where('blocker_user_id', $viewer->id)
                ->where('blocked_user_id', $creator)->first();
            if ($existing) {
                $existing->delete();
                $blocked = false;
            } else {
                UserBlock::create([
                    'blocker_user_id' => $viewer->id,
                    'blocked_user_id' => $creator,
                    'reason'          => $reason,
                ]);
                $blocked = true;
            }
        });

        return response()->json(['success' => true, 'blocked' => $blocked]);
    }
}
