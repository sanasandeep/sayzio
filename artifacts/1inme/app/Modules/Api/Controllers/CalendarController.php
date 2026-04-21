<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\CalendarAccount;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CalendarController extends Controller
{
    use ApiResponses;

    public function accounts(Request $request)
    {
        $items = CalendarAccount::where('user_id', $request->user()->id)->orderBy('provider')->get();
        return $this->ok(['items' => $items->map(fn ($a) => [
            'id'                => $a->id,
            'provider'          => $a->provider,
            'display_name'      => $a->display_name,
            'account_email'     => $a->account_email,
            'mirror_enabled'    => (bool) $a->mirror_enabled,
            'push_enabled'      => (bool) $a->push_enabled,
            'last_synced_at'    => optional($a->last_synced_at)->toIso8601String(),
            'last_sync_status'  => $a->last_sync_status,
        ])->all()]);
    }

    public function disconnectAccount(Request $request, int $id)
    {
        $a = CalendarAccount::where('user_id', $request->user()->id)->find($id);
        if (!$a) return $this->notFound('Calendar account not found');
        $a->delete();
        return $this->noContent();
    }

    public function rsvps(Request $request, int $linkId)
    {
        $link = Link::where('user_id', $request->user()->id)->find($linkId);
        if (!$link) return $this->notFound('Link not found');

        $page = Rsvp::where('link_id', $link->id)->orderByDesc('id')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 50))));
        return $this->ok([
            'items' => collect($page->items())->map(fn ($r) => [
                'id'         => $r->id,
                'name'       => $r->name,
                'email'      => $r->email,
                'phone'      => $r->phone,
                'response'   => $r->response,
                'plus_ones'  => (int) ($r->plus_ones ?? 0),
                'message'    => $r->message,
                'source'     => $r->source,
                'created_at' => optional($r->created_at)->toIso8601String(),
            ])->all(),
            'meta'  => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }
}
