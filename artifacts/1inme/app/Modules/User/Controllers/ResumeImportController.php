<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Resume;
use App\Modules\User\Models\ResumeSectionItem;
use App\Modules\User\Services\ResumeColorThemeRegistry;
use App\Modules\User\Services\ResumeTemplateRegistry;
use App\Services\AI\InsufficientCoinsForAiException;
use App\Services\Resume\ResumeImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Importer endpoints sit alongside ResumeController so each method
 * lives in one focused class. Every handler returns a normalised
 * "candidates" payload the editor's Review & Merge modal already
 * understands; the only side-effecting endpoint is `merge()`.
 */
class ResumeImportController extends Controller
{
    use \App\Modules\User\Concerns\GatesResumeAiTools;

    public function __construct(protected ResumeImportService $importer) {}

    /** POST /resume/import/file — upload + parse a PDF/DOCX. */
    public function file(Request $request): JsonResponse
    {
        $request->validate([
            'file'     => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx'],
            'linkedin' => ['nullable', 'boolean'],
        ]);

        try {
            $candidates = $this->importer->importFromUpload(
                $request->user(),
                $request->file('file'),
                (bool) $request->boolean('linkedin'),
            );
        } catch (InsufficientCoinsForAiException $e) {
            return response()->json(['message' => $e->getMessage()], 402);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['candidates' => $candidates]);
    }

    /** POST /resume/import/linkedin — URL + optional uploaded export PDF. */
    public function linkedin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url'  => ['nullable', 'string', 'url', 'max:500'],
            'file' => ['nullable', 'file', 'max:20480', 'mimes:pdf'],
        ]);

        if (empty($data['url']) && !$request->hasFile('file')) {
            return response()->json(['message' => 'Provide a LinkedIn URL or upload your exported PDF.'], 422);
        }

        try {
            if ($request->hasFile('file')) {
                $candidates = $this->importer->importFromUpload(
                    $request->user(),
                    $request->file('file'),
                    true,
                    $data['url'] ?? null,
                );
            } else {
                $candidates = $this->importer->importFromLinkedinUrl($request->user(), $data['url']);
            }
        } catch (InsufficientCoinsForAiException $e) {
            return response()->json(['message' => $e->getMessage()], 402);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['candidates' => $candidates]);
    }

    /** POST /resume/import/biolink — pull from the user's bio link content. */
    public function biolink(Request $request): JsonResponse
    {
        $candidates = $this->importer->importFromBiolink($request->user());
        return response()->json(['candidates' => $candidates]);
    }

    /** POST /resume/import/ai — generate a draft from a short prompt. */
    public function ai(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prompt'     => ['required', 'string', 'min:10', 'max:1500'],
            'sections'   => ['nullable', 'array'],
            'sections.*' => ['string', Rule::in(['summary', 'experience', 'skills', 'projects'])],
        ]);

        if ($gate = $this->resumeToolsGate($request)) {
            return $gate;
        }

        $resume = $request->user()->resolveResume($request);
        $context = [
            'header'  => $resume->getMergedSections()['header'] ?? [],
            'summary' => $resume->getMergedSections()['summary'] ?? '',
        ];

        try {
            $candidates = $this->importer->importFromAi(
                $request->user(),
                $data['prompt'],
                $data['sections'] ?? ['summary', 'experience', 'skills'],
                $context,
            );
        } catch (InsufficientCoinsForAiException $e) {
            return response()->json([
                'message' => 'Not enough coins to generate a draft.',
                'required' => $e->required ?? null,
                'balance'  => $e->balance ?? null,
            ], 402);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['candidates' => $candidates]);
    }

    /** POST /resume/import/merge — commit the user-confirmed picks. */
    public function merge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'candidates'           => ['required', 'array'],
            'candidates.header'    => ['nullable', 'array'],
            'candidates.summary'   => ['nullable', 'string'],
            'candidates.items'     => ['nullable', 'array'],
            'picks'                => ['required', 'array'],
            'picks.header'         => ['nullable', 'array'],
            'picks.header.mode'    => ['nullable', Rule::in(['replace', 'append', 'skip'])],
            'picks.header.fields' => ['nullable', 'array'],
            'picks.summary'        => ['nullable', 'array'],
            'picks.summary.mode'   => ['nullable', Rule::in(['replace', 'append', 'skip'])],
            'picks.items'          => ['nullable', 'array'],
            'picks.items.*'        => ['integer', 'min:0'],
        ]);

        $result = $this->importer->applyMerge($request->user(), $data['candidates'], $data['picks']);

        return response()->json([
            'changed' => $result['changed'],
            'resume'  => $this->presentResume($result['resume']),
        ]);
    }

    /**
     * Mirror of ResumeController::present so the modal can refresh the
     * editor in place after merging without an extra round-trip.
     */
    private function presentResume(Resume $resume): array
    {
        $items = $resume->items->map(fn ($i) => [
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
