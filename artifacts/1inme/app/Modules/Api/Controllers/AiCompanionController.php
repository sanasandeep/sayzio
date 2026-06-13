<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\AiCompanion;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AiCompanionController extends Controller
{
    use ApiResponses;

    /**
     * List the signed-in user's biolink-placement AI Companions so the
     * mobile block editor's "AI" picker can offer them — mirroring the
     * web editor's special-panel $userCompanions list. Restricted to the
     * `biolink` placement so embed/inbox-only companions don't leak in.
     */
    public function index(Request $request)
    {
        $items = AiCompanion::where('user_id', $request->user()->id)
            ->where('placement', 'biolink')
            ->orderByDesc('id')
            ->get(['id', 'public_id', 'name', 'is_disabled'])
            ->map(fn ($c) => [
                'id'          => (int) $c->id,
                'public_id'   => $c->public_id,
                'name'        => $c->name,
                'is_disabled' => (bool) $c->is_disabled,
            ])->values()->all();

        return $this->ok(['items' => $items]);
    }
}
