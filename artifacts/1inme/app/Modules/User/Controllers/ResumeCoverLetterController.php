<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Resume;
use App\Modules\User\Models\ResumeCoverLetter;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\InsufficientCoinsForAiException;
use App\Services\Resume\ResumeCoverLetterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Endpoints for the AI cover-letter generator.
 *
 *   POST   /resume/cover-letters/estimate           upfront credit cost
 *   POST   /resume/cover-letters                    generate (charges credits)
 *   GET    /resume/cover-letters                    list saved letters
 *   GET    /resume/cover-letters/{letter}           one saved letter (full text + JD)
 *   PATCH  /resume/cover-letters/{letter}           save inline edits
 *   POST   /resume/cover-letters/{letter}/regenerate per-section regenerate
 *   DELETE /resume/cover-letters/{letter}           delete
 *   GET    /resume/cover-letters/{letter}/download  PDF export
 *
 * Credits are charged inside ResumeCoverLetterService::generate() and
 * ::regenerateSection(); list / show / update / destroy / download
 * are free reads or saves of already-generated content.
 */
class ResumeCoverLetterController extends Controller
{
    use \App\Modules\User\Concerns\GatesResumeAiTools;

    public function __construct(
        protected ResumeCoverLetterService $letters,
        protected AiUsageCharger $credits,
    ) {}

    public function estimate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'job_description' => ['required', 'string', 'min:30', 'max:20000'],
            'tone'            => ['nullable', 'string', 'in:professional,warm,concise'],
            'persona_id'      => ['nullable', 'integer'],
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
            $cost = $this->letters->estimateCredits(
                $resume,
                $data['job_description'],
                $data['tone'] ?? 'professional',
                isset($data['persona_id']) ? (int) $data['persona_id'] : null,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'estimated_credits' => $cost,
            'balance'           => $this->credits->getBalance($request->user()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'job_description' => ['required', 'string', 'min:30', 'max:20000'],
            'tone'            => ['nullable', 'string', 'in:professional,warm,concise'],
            'persona_id'      => ['nullable', 'integer'],
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
            $result = $this->letters->generate(
                $request->user(),
                $resume,
                $data['job_description'],
                $data['tone'] ?? 'professional',
                isset($data['persona_id']) ? (int) $data['persona_id'] : null,
            );
        } catch (InsufficientCoinsForAiException $e) {
            return response()->json([
                'message'  => 'Not enough AI credits to generate this cover letter.',
                'required' => $e->required ?? null,
                'balance'  => $e->balance ?? null,
            ], 402);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'letter'        => $this->letters->present($result['letter']),
            'credits_spent' => $result['credits_spent'],
            'balance'       => $this->credits->getBalance($request->user()),
            'history'       => $this->letters->recentLetters($request->user(), $resume, 20),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $resume = $request->user()->ensureResume();
        return response()->json([
            'letters'  => $this->letters->recentLetters($request->user(), $resume, 50),
            'balance'  => $this->credits->getBalance($request->user()),
            'personas' => $this->letters->userPersonas($request->user()),
        ]);
    }

    public function show(Request $request, ResumeCoverLetter $letter): JsonResponse
    {
        $this->authorizeOwn($request, $letter);
        return response()->json([
            'letter' => $this->letters->present($letter),
        ]);
    }

    public function update(Request $request, ResumeCoverLetter $letter): JsonResponse
    {
        $this->authorizeOwn($request, $letter);

        $data = $request->validate([
            'title'            => ['nullable', 'string', 'max:200'],
            'content'          => ['required', 'array'],
            'content.greeting' => ['nullable', 'string', 'max:300'],
            'content.body'     => ['nullable', 'array', 'max:8'],
            'content.body.*'   => ['string', 'max:2000'],
            'content.sign_off' => ['nullable', 'string', 'max:400'],
        ]);

        $saved = $this->letters->saveManualEdit($letter, $data['content'], $data['title'] ?? null);

        return response()->json([
            'letter' => $this->letters->present($saved),
        ]);
    }

    public function regenerate(Request $request, ResumeCoverLetter $letter): JsonResponse
    {
        $this->authorizeOwn($request, $letter);

        $data = $request->validate([
            'section'     => ['required', 'string', 'in:greeting,body,sign_off'],
            'instruction' => ['nullable', 'string', 'max:400'],
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
            $result = $this->letters->regenerateSection(
                $request->user(),
                $resume,
                $letter,
                $data['section'],
                $data['instruction'] ?? null,
            );
        } catch (InsufficientCoinsForAiException $e) {
            return response()->json([
                'message'  => 'Not enough AI credits to regenerate this section.',
                'required' => $e->required ?? null,
                'balance'  => $e->balance ?? null,
            ], 402);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'letter'        => $this->letters->present($result['letter']),
            'credits_spent' => $result['credits_spent'],
            'balance'       => $this->credits->getBalance($request->user()),
        ]);
    }

    public function destroy(Request $request, ResumeCoverLetter $letter): JsonResponse
    {
        $this->authorizeOwn($request, $letter);
        $letter->delete();
        $resume = $request->user()->ensureResume();
        return response()->json([
            'deleted' => true,
            'history' => $this->letters->recentLetters($request->user(), $resume, 20),
        ]);
    }

    /**
     * Render the saved letter as a single-page PDF using the same
     * Dompdf stack as the resume download. Lightweight HTML — the
     * letter has no template selector, so we render the persisted
     * greeting / body / sign-off directly with the resume header.
     */
    public function download(Request $request, ResumeCoverLetter $letter): Response
    {
        $this->authorizeOwn($request, $letter);

        $resume = $request->user()->ensureResume();
        $sections = $resume->getMergedSections();
        $header = (array) ($sections['header'] ?? []);

        $html = view('user.resume.cover-letter-pdf', [
            'letter' => $letter,
            'header' => $header,
        ])->render();

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a4');
        $dompdf->render();
        $body = $dompdf->output();

        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($letter->title ?: 'cover-letter'));
        $name = trim($name, '-') ?: 'cover-letter';
        $filename = $name . '.pdf';

        return response($body, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => (string) strlen($body),
            'Cache-Control'       => 'private, max-age=0, no-store',
        ]);
    }

    private function authorizeOwn(Request $request, ResumeCoverLetter $letter): void
    {
        if ($letter->user_id !== $request->user()->id) {
            abort(404);
        }
    }
}
