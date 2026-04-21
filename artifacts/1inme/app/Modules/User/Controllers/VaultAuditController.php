<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\VaultAudit;
use Illuminate\Http\Request;

class VaultAuditController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $ws = app('current_workspace');
        $isAdmin = $user->isSuperAdmin()
            || (int) $ws->owner_user_id === (int) $user->id
            || $user->canInWorkspace($ws, 'vault.delete');

        $q = VaultAudit::query()->with('actor')->orderByDesc('occurred_at');
        if (!$isAdmin) {
            $q->where('actor_user_id', $user->id);
        }
        $items = $q->paginate(50)->withQueryString();

        return view('user.vault.audit.index', compact('items', 'isAdmin'));
    }
}
