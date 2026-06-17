<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Services\AI\AiCreditService;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\InsufficientAiCreditsException;
use App\Services\Biolink\AiBiolinkBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Build my Link in Bio with AI" endpoints.
 *
 *   GET  links/{link}/ai-builder           → intake screen
 *   POST links/{link}/ai-builder/estimate  → upfront credit cost
 *   POST links/{link}/ai-builder/generate  → run the build, then open the editor
 *
 * The AI charge happens inside the generate() call via OpenAiService
 * against the `biolink_builder` feature — no new currency/coin path.
 */
class AiBiolinkBuilderController extends Controller
{
    public function __construct(
        protected AiBiolinkBuilderService $builder,
        protected AiCreditService $credits,
    ) {}

    public function intake(Request $request, Link $link)
    {
        $this->authorizeLink($link);

        return view('user.links.ai-builder', [
            'link'         => $link,
            'aiEnabled'    => AiEngineSettings::isEnabled(),
            'balance'      => AiEngineSettings::isEnabled() ? $this->credits->getBalance($request->user()) : 0,
            'allowedTypes' => $this->builder->allowedTypesFor($request->user()),
            'maxLinks'     => 25,
            'maxImages'    => 25,
            'maxFiles'     => 15,
        ]);
    }

    public function estimate(Request $request, Link $link): JsonResponse
    {
        $this->authorizeLink($link);

        if (!AiEngineSettings::isEnabled()) {
            return response()->json(['message' => 'AI Engine is disabled.'], 404);
        }

        $data = $this->validatePayload($request);

        try {
            $cost = $this->builder->estimateCredits(
                $request->user(),
                $data['description'],
                $data['links'],
                $data['images'],
                $data['files'],
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'estimated_credits' => $cost,
            'balance'           => $this->credits->getBalance($request->user()),
        ]);
    }

    public function generate(Request $request, Link $link): JsonResponse
    {
        $this->authorizeLink($link);

        if (!AiEngineSettings::isEnabled()) {
            return response()->json(['message' => 'AI Engine is disabled.'], 404);
        }

        $data = $this->validatePayload($request);

        try {
            $result = $this->builder->generate(
                $request->user(),
                $link,
                $data['description'],
                $data['links'],
                $data['images'],
                $data['files'],
            );
        } catch (InsufficientAiCreditsException $e) {
            return response()->json([
                'message'  => 'Not enough AI credits to build this page.',
                'required' => $e->required ?? null,
                'balance'  => $e->balance ?? null,
            ], 402);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'blocks'        => $result['blocks'],
            'credits_spent' => $result['credits_spent'],
            'balance'       => $this->credits->getBalance($request->user()),
            'redirect'      => route('user.links.blocks.editor', $link),
        ]);
    }

    /**
     * @return array{description:string,links:list<string>,images:list<string>,files:list<string>}
     */
    private function validatePayload(Request $request): array
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'min:10', 'max:4000'],
            'links'       => ['nullable', 'array', 'max:25'],
            'links.*'     => ['string', 'max:2048'],
            'images'      => ['nullable', 'array', 'max:25'],
            'images.*'    => ['string', 'max:2048'],
            'files'       => ['nullable', 'array', 'max:15'],
            'files.*'     => ['string', 'max:2048'],
        ]);

        return [
            'description' => $data['description'],
            'links'       => array_values($data['links'] ?? []),
            'images'      => array_values($data['images'] ?? []),
            'files'       => array_values($data['files'] ?? []),
        ];
    }

    private function authorizeLink(Link $link): void
    {
        abort_if($link->user_id !== workspace_owner_id() || $link->type !== 'biolink', 403);
    }
}
