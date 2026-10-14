<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\VaultAudit;
use App\Modules\User\Models\VaultClient;
use App\Modules\User\Models\VaultCredential;
use Illuminate\Http\Request;

class VaultEventController extends Controller
{
    public function store(Request $request, string $kind, int $id)
    {
        abort_unless(in_array($kind, ['client', 'credential'], true), 404);
        $item = ($kind === 'client' ? VaultClient::class : VaultCredential::class)::findOrFail($id);
        abort_unless($item->visibleTo($request->user(), app('current_workspace')), 404);
        $data = $request->validate(['action' => 'required|in:copy,open', 'field' => 'required|in:password,username,url,notes,custom_fields,authenticator,email,phone,address,profile']);
        VaultAudit::record($data['action'], $kind, $id, ($item->label ?? $item->name).' · '.$data['field']);

        return response()->noContent()->header('Cache-Control', 'no-store');
    }
}
