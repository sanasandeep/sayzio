<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Services\AI\InsufficientCoinsForAiException;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\AiEngineSettings;
use App\Services\Resume\ResumeTailorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints for the "Tailor my resume to a job description" feature.
 *
 *   POST /resume/tailor/estimate  →  upfront credit cost for a JD
 *   POST /resume/tailor/run       →  AI run, returns suggestions
 *   POST /resume/tailor/apply     →  commit user-accepted picks
 *   GET  /resume/tailor/history   →  recent runs (for "history" panel)
 *
 * The AI charge happens inside the run() call via OpenAiService;
 * applying picks is free (it's just a write to the resume).
 */
class ResumeTailorController extends Controller
{
    use \App\Modules\User\Concerns\GatesResumeAiTools;

    public function __construct(
        protected ResumeTailorService $tailor,
        protected AiUsageCharger $credits,
    ) {}

    public function estimate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'job_description' => ['required', 'string', 'min:30', 'max:20000'],
        ]);

        if (!AiEngineSettings::isEnabled()) {
            return response()->json(['message' => 'AI Engine is disabled.'], 404);
        }
        if ($gate = $this->resumeToolsGate($request)) {
            return $gate;
        }

        $resume = $request->user()->ensureResume();
        $resume->load('items');

        $cost = $this->tailor->estimateCredits($resume, $data['job_description']);
        return response()->json([
            'estimated_credits' => $cost,
            'balance'           => $this->credits->getBalance($request->user()),
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        $data = $request->validate([
            'job_description' => ['required', 'string', 'min:30', 'max:20000'],
        ]);

        if (!AiEngineSettings::isEnabled()) {
            return response()->json(['message' => 'AI Engine is disabled.'], 404);
        }
        if ($gate = $this->resumeToolsGate($request)) {
            return $gate;
        }

        $resume = $request->user()->ensureResume();
        $resume->load('items');

        try {
            $result = $this->tailor->run($request->user(), $resume, $data['job_description']);
        } catch (InsufficientCoinsForAiException $e) {
            return response()->json([
                'message'  => 'Not enough AI credits to tailor this resume.',
                'required' => $e->required ?? null,
                'balance'  => $e->balance ?? null,
            ], 402);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'suggestions'   => $result['suggestions'],
            'credits_spent' => $result['credits_spent'],
            'balance'       => $this->credits->getBalance($request->user()),
            'history'       => $this->tailor->recentRuns($request->user(), 10),
        ]);
    }

    public function apply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'suggestions'              => ['required', 'array'],
            'suggestions.summary'      => ['nullable', 'array'],
            'suggestions.experience'   => ['nullable', 'array'],
            'suggestions.skills'       => ['nullable', 'array'],
            'picks'                    => ['required', 'array'],
            'picks.summary'            => ['nullable', 'boolean'],
            'picks.experience'         => ['nullable', 'array'],
            'picks.experience.*'       => ['integer'],
            'picks.skills'             => ['nullable', 'array'],
            'picks.skills.*'           => ['integer', 'min:0'],
        ]);

        $resume = $request->user()->ensureResume();
        $resume->load('items');

        $result = $this->tailor->applySuggestions(
            $resume,
            $data['suggestions'],
            $data['picks'],
        );

        return response()->json([
            'changed' => $result['changed'],
            'resume'  => $this->presentResume($result['resume']),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        return response()->json([
            'runs' => $this->tailor->recentRuns($request->user(), 10),
        ]);
    }

    /**
     * Same shape as ResumeImportController::presentResume — keeps the
     * editor's hydrate() happy without an extra round-trip.
     */
    private function presentResume($resume): array
    {
        $items = $resume->items->map(fn($i) => [
            'id'           => $i->id,
            'section_type' => $i->section_type,
            'position'     => $i->position,
            'data'         => $i->data ?? [],
        ])->groupBy('section_type');

        return [
            'id'             => $resume->id,
            'template_id'    => $resume->template_id,
            'template'       => $resume->templateMeta(),
            'color_theme_id' => $resume->color_theme_id,
            'color_theme'    => $resume->colorThemeMeta(),
            'sections'       => $resume->getMergedSections(),
            'items'          => $items,
            'updated_at'     => optional($resume->updated_at)->toIso8601String(),
        ];
    }
}
