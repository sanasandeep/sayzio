<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\AiMind;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiMindQueryService;
use App\Services\AI\InsufficientCoinsForAiException;
use Illuminate\Http\Request;

/**
 * "Test this Mind" panel — POST a question, get back the model's
 * answer plus the cited sources and the credit cost. Returns JSON for
 * the in-page panel; falls back to a flash message on non-JSON.
 */
class MindChatController extends Controller
{
    public function __construct(protected AiMindQueryService $svc) {}

    public function ask(Request $request, AiMind $mind)
    {
        if (!AiEngineSettings::isEnabled()) abort(404);
        $user = $request->user();
        if ($mind->is_disabled) {
            return $this->respond($request, ['error' => 'This Mind is disabled.'], 403);
        }
        // Allow asking against your own Mind OR the platform default.
        if (!$mind->isPlatform() && (int) $mind->user_id !== (int) $user->id) {
            abort(403);
        }

        $data = $request->validate([
            'question' => 'required|string|max:1500',
            // Optional extra mind ids to include alongside the focused mind.
            // Useful for queries like "use my Mind + 1INME default".
            'also'     => 'array',
            'also.*'   => 'integer',
        ]);
        $minds = [$mind];
        if (!empty($data['also'])) {
            $extras = AiMind::whereIn('id', $data['also'])->get();
            foreach ($extras as $m) {
                if ($m->isPlatform() || (int) $m->user_id === (int) $user->id) {
                    $minds[] = $m;
                }
            }
        }

        try {
            $result = $this->svc->ask($user, $minds, $data['question']);
        } catch (InsufficientCoinsForAiException $e) {
            return $this->respond($request, [
                'error'    => "Need {$e->required} coins — only {$e->balance} available.",
                'top_up'   => route('user.wallet.buy'),
            ], 402);
        } catch (\Throwable $e) {
            return $this->respond($request, ['error' => $e->getMessage()], 422);
        }
        return $this->respond($request, $result, 200);
    }

    protected function respond(Request $request, array $payload, int $status)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($payload, $status);
        }
        if (!empty($payload['error'])) {
            return back()->with('error', $payload['error']);
        }
        return back()->with('status', 'Answer ready (' . ($payload['credits_spent'] ?? 0) . ' credits).');
    }
}
