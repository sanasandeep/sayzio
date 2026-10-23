<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\ProtectedAccount;
use App\Modules\Admin\Services\AdminActionLogger;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\AssetTransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffAssignmentController extends Controller
{
    public function link(Request $request, User $user, AssetTransferService $transfers, AdminActionLogger $audit)
    {
        $data = $request->validate([
            'link_id' => 'required|integer|exists:links,id',
            'source_user_id' => 'required|integer|exists:users,id',
            'reason' => 'required|string|max:255',
        ]);
        abort_if((int) $data['source_user_id'] === (int) $user->id, 422, 'Choose a different source account.');
        $actor = auth('admin')->user();
        abort_unless($actor?->hasPermission('users.links.assign'), 403);
        // Deliberately require both IDs so an operator cannot move an arbitrary
        // link merely by selecting its guessed ID from another account.
        DB::transaction(function () use ($data, $actor, $user, $transfers, $audit) {
            $link = Link::where('user_id', $data['source_user_id'])->lockForUpdate()->findOrFail($data['link_id']);
            $sender = User::findOrFail($link->user_id);
            abort_if(ProtectedAccount::isProtected($sender) || ProtectedAccount::isProtected($user), 403, 'Protected account assets cannot be reassigned here.');
            $transfer = $transfers->transferLink($link, $sender, $user, 'admin', $actor);
            $audit->log(AdminActionLogger::LINK_ASSIGNED, $user, [
                'link_id' => $link->id, 'from_user_id' => $sender->id,
                'transfer_id' => $transfer->id, 'reason' => $data['reason'],
            ]);
        });
        return back()->with('success', 'Link reassigned. Both owners are notified.');
    }
}
