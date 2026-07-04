<?php

namespace App\Modules\Api\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\AiStaff;
use App\Modules\User\Models\AiStaffSuggestion;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\AiStaffSuggestionApplier;
use App\Services\AI\AiStaffSuggestionNotPendingException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Task #3523 — AI Staff mobile REST parity.
 *
 * Deliberately narrow: list/enable staff, and read/apply/dismiss the
 * confirm-before-act suggestions they raise. Drafting/chat/chase actions
 * stay web-only for now (per spec); mobile is a read + confirm surface.
 */
class AiStaffController extends Controller
{
    use ApiResponses;

    public function __construct(protected AiStaffSuggestionApplier $applier) {}

    /** List the caller's AI staff. */
    public function index(Request $request): JsonResponse
    {
        $staff = AiStaff::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (AiStaff $s) => $this->staffPayload($request, $s));

        return $this->ok(['staff' => $staff->values(), 'domains' => AiStaff::DOMAINS]);
    }

    /** Enable/disable a staff member. */
    public function update(Request $request, AiStaff $staff): JsonResponse
    {
        if ($staff->user_id !== $request->user()->id) return $this->notFound();

        $data = $request->validate(['is_disabled' => ['required', 'boolean']]);
        $staff->forceFill(['is_disabled' => $data['is_disabled']])->save();

        return $this->ok($this->staffPayload($request, $staff));
    }

    /** List the caller's AI staff suggestions (optionally filtered by staff id). */
    public function suggestions(Request $request): JsonResponse
    {
        $query = AiStaffSuggestion::query()
            ->where('user_id', $request->user()->id)
            ->with('aiStaff')
            ->latest();

        if ($request->filled('staff_id')) {
            $query->where('ai_staff_id', (int) $request->input('staff_id'));
        }

        $suggestions = $query->limit(50)->get()->map(fn (AiStaffSuggestion $s) => $this->suggestionPayload($s));

        return $this->ok(['suggestions' => $suggestions->values()]);
    }

    public function applySuggestion(Request $request, AiStaffSuggestion $suggestion): JsonResponse
    {
        if ($suggestion->user_id !== $request->user()->id) return $this->notFound();
        if (!$suggestion->isPending()) return $this->fail('This suggestion is no longer pending.', 422);

        try {
            $result = $this->applier->claimAndApply($request->user(), $suggestion);
        } catch (AiStaffSuggestionNotPendingException $e) {
            return $this->fail('This suggestion is no longer pending.', 422);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok([
            'suggestion' => $this->suggestionPayload($suggestion->fresh()),
            'message'    => $result['message'],
        ]);
    }

    public function dismissSuggestion(Request $request, AiStaffSuggestion $suggestion): JsonResponse
    {
        if ($suggestion->user_id !== $request->user()->id) return $this->notFound();

        try {
            $this->applier->dismiss($suggestion);
        } catch (AiStaffSuggestionNotPendingException $e) {
            // Already resolved — no-op success.
        }

        return $this->ok(['suggestion' => $this->suggestionPayload($suggestion->fresh())]);
    }

    protected function staffPayload(Request $request, AiStaff $staff): array
    {
        return [
            'id'           => $staff->id,
            'name'         => $staff->name,
            'domain'       => $staff->domain,
            'domain_label' => $staff->domainLabel(),
            'instructions' => $staff->instructions,
            'is_disabled'  => (bool) $staff->is_disabled,
            'plan_allowed' => AiPlanAccess::featureAllowed($request->user(), $staff->featureKey()),
            'last_used_at' => optional($staff->last_used_at)->toIso8601String(),
        ];
    }

    protected function suggestionPayload(AiStaffSuggestion $s): array
    {
        return [
            'id'         => $s->id,
            'ai_staff_id'=> $s->ai_staff_id,
            'staff_name' => $s->aiStaff?->name,
            'kind'       => $s->kind,
            'status'     => $s->status,
            'title'      => $s->title,
            'message'    => $s->message,
            'payload'    => $s->payload,
            'created_at' => optional($s->created_at)->toIso8601String(),
        ];
    }
}
