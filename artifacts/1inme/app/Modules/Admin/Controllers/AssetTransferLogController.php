<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\User\Models\AssetTransfer;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Read-only back-office viewer for the asset_transfers audit log
 * (admin-granted link & workspace transfers between accounts).
 */
class AssetTransferLogController extends Controller
{
    public function index(Request $request)
    {
        $kind  = (string) $request->query('kind', '');
        $q     = trim((string) $request->query('q', ''));

        $transfers = AssetTransfer::query()
            ->with(['fromUser:id,name,email', 'toUser:id,name,email'])
            ->when(in_array($kind, [AssetTransfer::KIND_LINK, AssetTransfer::KIND_WORKSPACE], true),
                fn ($query) => $query->where('kind', $kind))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . strtolower($q) . '%';
                $query->where(function ($w) use ($like) {
                    $w->whereRaw('lower(coalesce(asset_label, \'\')) like ?', [$like])
                      ->orWhereRaw('lower(coalesce(from_email, \'\')) like ?', [$like])
                      ->orWhereRaw('lower(coalesce(to_email, \'\')) like ?', [$like]);
                });
            })
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.asset-transfers.index', [
            'transfers' => $transfers,
            'kind'      => $kind,
            'q'         => $q,
        ]);
    }
}
