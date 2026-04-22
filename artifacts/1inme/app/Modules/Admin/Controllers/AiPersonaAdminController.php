<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\AiPersonaAgent;
use App\Services\AI\PersonaSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin "AI Personas" page:
 *   - Aggregate stats (persona count, last-used).
 *   - Per-plan caps (max personas, max attached Minds, max versions).
 *   - Disable / re-enable a user's persona for abuse.
 */
class AiPersonaAdminController extends Controller
{
    public function index(Request $request)
    {
        $totals = [
            'personas' => (int) AiPersonaAgent::count(),
            'disabled' => (int) AiPersonaAgent::where('is_disabled', true)->count(),
            'active'   => (int) AiPersonaAgent::whereNotNull('last_used_at')->count(),
        ];

        $topUsers = AiPersonaAgent::query()
            ->select('user_id', DB::raw('COUNT(*) as personas_count'),
                DB::raw('MAX(last_used_at) as last_used'))
            ->groupBy('user_id')
            ->orderByDesc('personas_count')
            ->limit(20)
            ->get();

        $personas = AiPersonaAgent::query()
            ->with('user:id,name,email')
            ->withCount('minds')
            ->latest('updated_at')
            ->paginate(25);

        return view('admin.ai-personas.index', [
            'totals'   => $totals,
            'topUsers' => $topUsers,
            'personas' => $personas,
            'caps'     => PersonaSettings::caps(),
        ]);
    }

    public function updateCaps(Request $request)
    {
        $defaults = PersonaSettings::capsDefault();
        $rules = [];
        foreach ($defaults as $k => $_) {
            $rules["caps.{$k}"] = 'nullable|integer|min:0|max:1000000';
        }
        $data = $request->validate($rules);
        PersonaSettings::setCaps($data['caps'] ?? []);
        return back()->with('success', 'AI Persona caps updated.');
    }

    public function disable(Request $request, AiPersonaAgent $persona)
    {
        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);
        $persona->forceFill([
            'is_disabled'     => true,
            'disabled_reason' => $data['reason'],
        ])->save();
        return back()->with('success', 'Persona disabled.');
    }

    public function enable(Request $request, AiPersonaAgent $persona)
    {
        $persona->forceFill([
            'is_disabled'     => false,
            'disabled_reason' => null,
        ])->save();
        return back()->with('success', 'Persona re-enabled.');
    }
}
