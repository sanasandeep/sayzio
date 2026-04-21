<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\SocialAccountConnection;
use App\Modules\User\Models\SocialProof;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SocialAccountController extends Controller
{
    use ApiResponses;

    public function connections(Request $request)
    {
        $items = SocialAccountConnection::where('user_id', $request->user()->id)
            ->orderBy('platform')
            ->get();
        return $this->ok(['items' => $items->map(fn ($c) => [
            'id'                  => $c->id,
            'platform'            => $c->platform,
            'handle'              => $c->handle,
            'display_name'        => $c->display_name,
            'profile_url'         => $c->profile_url,
            'avatar_url'          => $c->avatar_url,
            'follower_count'      => (int) ($c->follower_count ?? 0),
            'last_refreshed_at'   => optional($c->last_refreshed_at)->toIso8601String(),
            'last_refresh_status' => $c->last_refresh_status,
        ])->all()]);
    }

    public function disconnect(Request $request, int $id)
    {
        $c = SocialAccountConnection::where('user_id', $request->user()->id)->find($id);
        if (!$c) return $this->notFound('Connection not found');
        $c->delete();
        return $this->noContent();
    }

    public function socialProofs(Request $request)
    {
        $items = SocialProof::where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();
        return $this->ok(['items' => $items->map(fn ($p) => $p->toArray())->all()]);
    }
}
