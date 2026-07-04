<?php

namespace App\Modules\User\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\AiStaff;
use App\Modules\User\Models\AiStaffSuggestion;
use App\Modules\User\Models\Contact;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\AiStaffRuntime;
use App\Services\AI\AiStaffSuggestionApplier;
use App\Services\AI\AiStaffSuggestionNotPendingException;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\InsufficientCoinsForAiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Task #3523 — "AI Staff" (web): CRUD for configurable AI agents plus the
 * per-domain actions (billing draft/chase, contacts summarize/follow-up,
 * general chat). Inbox-domain staff intentionally have no bespoke chat
 * endpoint here — they just flip a `settings['inbox_agent']` toggle
 * already owned by InboxAutopilot/InboxAiReplyDrafter, no reimplementation.
 */
class AiStaffController extends Controller
{
    public function __construct(
        protected AiStaffRuntime $runtime,
        protected AiStaffSuggestionApplier $applier,
        protected AiUsageCharger $credits,
    ) {}

    public function index(Request $request)
    {
        $staff = $this->owned($request)->orderByDesc('id')->get();
        $suggestions = AiStaffSuggestion::query()
            ->where('user_id', $request->user()->id)
            ->with('aiStaff')
            ->latest()
            ->limit(30)
            ->get();

        return view('user.ai.staff.index', [
            'staff'       => $staff,
            'domains'     => AiStaff::DOMAINS,
            'domainDesc'  => AiStaff::DOMAIN_DESCRIPTIONS,
            'suggestions' => $suggestions,
            'balance'     => $this->credits->getBalance($request->user()),
            'enabled'     => AiEngineSettings::isEnabled(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:120'],
            'domain'       => ['required', Rule::in(array_keys(AiStaff::DOMAINS))],
            'instructions' => ['nullable', 'string', 'max:4000'],
        ]);

        if (!AiPlanAccess::featureAllowed($request->user(), 'ai_staff_' . $data['domain'])) {
            return back()->with('error', 'AI Staff for this domain is not available on your current plan.')->withInput();
        }

        $staff = AiStaff::create([
            'user_id'      => $request->user()->id,
            'name'         => $data['name'],
            'domain'       => $data['domain'],
            'instructions' => $data['instructions'] ?? null,
        ]);

        return redirect()->route('user.ai.staff.show', $staff)->with('status', "{$staff->name} hired.");
    }

    public function show(Request $request, AiStaff $staff)
    {
        $this->authorizeOwned($request, $staff);

        $suggestions = $staff->suggestions()->latest()->limit(30)->get();
        $contacts = $staff->domain === AiStaff::DOMAIN_CONTACTS
            ? Contact::query()->where('user_id', $request->user()->id)->orderByDesc('updated_at')->limit(50)->get(['id', 'display_name', 'given_name', 'family_name', 'organization'])
            : collect();

        return view('user.ai.staff.show', [
            'staff'       => $staff,
            'suggestions' => $suggestions,
            'contacts'    => $contacts,
            'balance'     => $this->credits->getBalance($request->user()),
            'planAllowed' => AiPlanAccess::featureAllowed($request->user(), $staff->featureKey()),
        ]);
    }

    public function update(Request $request, AiStaff $staff)
    {
        $this->authorizeOwned($request, $staff);

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:120'],
            'instructions' => ['nullable', 'string', 'max:4000'],
            'is_disabled'  => ['nullable', 'boolean'],
        ]);

        $staff->forceFill([
            'name'         => $data['name'],
            'instructions' => $data['instructions'] ?? null,
            'is_disabled'  => (bool) ($data['is_disabled'] ?? false),
        ])->save();

        return back()->with('status', 'Saved.');
    }

    public function destroy(Request $request, AiStaff $staff)
    {
        $this->authorizeOwned($request, $staff);
        $staff->delete();

        return redirect()->route('user.ai.staff.index')->with('status', 'Staff member removed.');
    }

    /** Free-form chat with a staff member. */
    public function chat(Request $request, AiStaff $staff)
    {
        $this->authorizeOwned($request, $staff);
        $this->ensureActionable($request, $staff);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'history' => ['nullable', 'array'],
        ]);

        try {
            $result = $this->runtime->chat($request->user(), $staff, $data['message'], $data['history'] ?? []);
        } catch (InsufficientCoinsForAiException $e) {
            return response()->json(['error' => ['message' => $e->getMessage()]], 402);
        } catch (\Throwable $e) {
            Log::warning('AI Staff chat failed: ' . $e->getMessage());
            return response()->json(['error' => ['message' => 'The assistant could not reply right now. Please try again.']], 422);
        }

        return response()->json($result);
    }

    /** Billing domain: draft an invoice from a free-text prompt. */
    public function draftInvoice(Request $request, AiStaff $staff)
    {
        $this->authorizeOwned($request, $staff);
        $this->ensureActionable($request, $staff, AiStaff::DOMAIN_BILLING);

        $data = $request->validate(['prompt' => ['required', 'string', 'max:2000']]);
        $ws = $this->currentWorkspace($request);
        if (!$ws) {
            return response()->json(['error' => ['message' => 'No active workspace.']], 422);
        }

        try {
            $suggestion = $this->runtime->draftInvoiceFromPrompt($request->user(), $staff, $ws, $data['prompt']);
        } catch (InsufficientCoinsForAiException $e) {
            return response()->json(['error' => ['message' => $e->getMessage()]], 402);
        } catch (\Throwable $e) {
            return response()->json(['error' => ['message' => $e->getMessage()]], 422);
        }

        return response()->json(['suggestion' => $suggestion]);
    }

    /** Billing domain: scan unpaid/overdue invoices and raise chase suggestions. */
    public function generateChaseSuggestions(Request $request, AiStaff $staff)
    {
        $this->authorizeOwned($request, $staff);
        $this->ensureActionable($request, $staff, AiStaff::DOMAIN_BILLING);

        $ws = $this->currentWorkspace($request);
        if (!$ws) {
            return response()->json(['error' => ['message' => 'No active workspace.']], 422);
        }

        try {
            $created = $this->runtime->overdueInvoiceSuggestions($request->user(), $staff, $ws);
        } catch (InsufficientCoinsForAiException $e) {
            return response()->json(['error' => ['message' => $e->getMessage()]], 402);
        }

        return response()->json(['created' => $created->count(), 'suggestions' => $created->values()]);
    }

    /** Contacts domain: summarize + suggest next steps for one contact. */
    public function summarizeContact(Request $request, AiStaff $staff, Contact $contact)
    {
        $this->authorizeOwned($request, $staff);
        $this->ensureActionable($request, $staff, AiStaff::DOMAIN_CONTACTS);
        abort_unless($contact->user_id === $request->user()->id, 404);

        try {
            $result = $this->runtime->summarizeContact($request->user(), $staff, $contact);
        } catch (InsufficientCoinsForAiException $e) {
            return response()->json(['error' => ['message' => $e->getMessage()]], 402);
        }

        return response()->json($result);
    }

    /** Contacts domain: draft a follow-up message for one contact. */
    public function draftFollowup(Request $request, AiStaff $staff, Contact $contact)
    {
        $this->authorizeOwned($request, $staff);
        $this->ensureActionable($request, $staff, AiStaff::DOMAIN_CONTACTS);
        abort_unless($contact->user_id === $request->user()->id, 404);

        $data = $request->validate(['goal' => ['nullable', 'string', 'max:500']]);

        try {
            $message = $this->runtime->draftFollowup($request->user(), $staff, $contact, $data['goal'] ?? '');
        } catch (InsufficientCoinsForAiException $e) {
            return response()->json(['error' => ['message' => $e->getMessage()]], 402);
        }

        return response()->json(['message' => $message]);
    }

    public function applySuggestion(Request $request, AiStaffSuggestion $suggestion)
    {
        abort_unless($suggestion->user_id === $request->user()->id, 404);

        if (!$suggestion->isPending()) {
            return response()->json(['error' => ['message' => 'This suggestion is no longer pending.']], 422);
        }

        try {
            $result = $this->applier->claimAndApply($request->user(), $suggestion);
        } catch (AiStaffSuggestionNotPendingException $e) {
            return response()->json(['error' => ['message' => 'This suggestion is no longer pending.']], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => ['message' => $e->getMessage()], 'status' => $suggestion->status], 422);
        }

        return response()->json(['status' => $suggestion->status, 'message' => $result['message'], 'url' => $result['url']]);
    }

    public function dismissSuggestion(Request $request, AiStaffSuggestion $suggestion)
    {
        abort_unless($suggestion->user_id === $request->user()->id, 404);

        try {
            $this->applier->dismiss($suggestion);
        } catch (AiStaffSuggestionNotPendingException $e) {
            // Already resolved — treat as a no-op success.
        }

        return response()->json(['status' => $suggestion->status]);
    }

    protected function owned(Request $request)
    {
        return AiStaff::query()->where('user_id', $request->user()->id);
    }

    protected function authorizeOwned(Request $request, AiStaff $staff): void
    {
        abort_unless($staff->user_id === $request->user()->id, 404);
    }

    protected function ensureActionable(Request $request, AiStaff $staff, ?string $expectDomain = null): void
    {
        if ($expectDomain !== null && $staff->domain !== $expectDomain) abort(404);
        if (!AiEngineSettings::isEnabled()) abort(404);
        if (!$staff->isEnabled()) abort(403, 'This AI staff member is disabled.');
        if (!AiPlanAccess::featureAllowed($request->user(), $staff->featureKey())) {
            abort(403, 'AI Staff for this domain is not available on your current plan.');
        }
    }

    protected function currentWorkspace(Request $request)
    {
        return app()->bound('current_workspace') ? app('current_workspace') : null;
    }
}
