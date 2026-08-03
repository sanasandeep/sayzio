<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Api\Resources\UserResource;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DiscoveryController extends Controller
{
    use ApiResponses;

    public function creators(Request $request)
    {
        $q = User::where('discoverable', true)->where('status', 'active');
        if ($search = $request->string('q')->toString()) {
            $q->where(function ($w) use ($search) {
                $w->where('name', 'ilike', "%{$search}%")
                  ->orWhere('handle', 'ilike', "%{$search}%")
                  ->orWhere('bio', 'ilike', "%{$search}%");
            });
        }
        $page = $q->orderByDesc('followers_count')->paginate(min(50, max(1, (int) $request->input('per_page', 20))));

        return $this->ok([
            'items' => collect($page->items())->map(fn ($u) => UserResource::toArray($u))->all(),
            'meta'  => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }

    public function creator(Request $request, string $handle)
    {
        $u = \App\Modules\User\Models\CreatorProfile::ownerUserForHandle($handle);
        if ($u && $u->status !== 'active') $u = null;
        if (!$u || !$u->discoverable) return $this->notFound('Creator not found');
        return $this->ok(['user' => UserResource::toArray($u)]);
    }
}
