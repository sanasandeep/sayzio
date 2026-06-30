<?php

namespace App\Modules\User\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Services\AI\AiCostEstimator;
use App\Services\AI\AiUsageCharger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Unified, read-only coin-cost estimate endpoint for the creator-facing AI
 * triggers that don't already own a feature-specific estimate route. Returns
 * the worst-case coins, the live wallet balance, and a low-balance flag so the
 * shared estimate badge can render "Up to X coins · Balance: Y" everywhere.
 */
class AiCostEstimateController extends Controller
{
    public function __construct(
        private AiCostEstimator $estimator,
        private AiUsageCharger $charger,
    ) {
    }

    public function estimate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'feature' => ['required', 'string', 'max:64'],
            'text'    => ['nullable', 'string', 'max:40000'],
        ]);

        $user = $request->user();
        $res  = $this->estimator->estimate($user, $data['feature'], (string) ($data['text'] ?? ''));

        return response()->json([
            'coins'   => $res['coins'] > 0 ? $res['coins'] : null,
            'mode'    => $res['mode'],
            'balance' => $this->charger->getBalance($user),
            'low'     => $this->charger->isLowBalance($user),
        ]);
    }
}
