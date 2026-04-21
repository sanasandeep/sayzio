<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\IntegrationConfig;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class IntegrationController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        $items = IntegrationConfig::where('user_id', $request->user()->id)->orderBy('kind')->get();
        return $this->ok(['items' => $items->map(fn ($i) => [
            'id'         => $i->id,
            'kind'       => $i->kind,
            'provider'   => $i->provider,
            'name'       => $i->name,
            'is_active'  => (bool) $i->is_active,
            'is_default' => (bool) $i->is_default,
            'meta'       => $i->meta,
            'created_at' => optional($i->created_at)->toIso8601String(),
        ])->all()]);
    }

    public function destroy(Request $request, int $id)
    {
        $i = IntegrationConfig::where('user_id', $request->user()->id)->find($id);
        if (!$i) return $this->notFound('Integration not found');
        $i->delete();
        return $this->noContent();
    }
}
