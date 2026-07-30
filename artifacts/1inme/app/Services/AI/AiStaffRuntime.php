<?php

namespace App\Services\AI;

use App\Modules\User\Models\AiStaff;
use App\Modules\User\Models\AiStaffSuggestion;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Task #3523 — the shared "brain" behind every AI Staff member. There is
 * no separate agent/runtime per staff: a staff row is just an identity
 * (name + personality/instructions) bound to a domain, and this service
 * builds the domain-appropriate grounded system prompt (via
 * AiMindFeatureAdapter) and calls the existing OpenAiService so every
 * call is charged/gated exactly like the rest of the AI surface.
 *
 * Actions that create or send something on the user's behalf (billing
 * drafts/chases) never happen directly here — they only ever produce a
 * pending AiStaffSuggestion, applied via AiStaffSuggestionApplier's
 * confirm-before-act claim/apply flow.
 */
class AiStaffRuntime
{
    public function __construct(
        protected OpenAiService $openai,
        protected AiMindFeatureAdapter $mind,
    ) {}

    /**
     * Free-form chat with a staff member, grounded in its domain's live
     * data. Returns the reply plus coin spend so callers can surface it.
     *
     * @param array<int,array{role:string,content:string}> $history
     * @return array{reply:string,credits_spent:int,model:string}
     */
    public function chat(User $user, AiStaff $staff, string $message, array $history = []): array
    {
        $feature = $staff->featureKey();
        $model = AiEngineSettings::featureModel($feature, $user);

        $messages = [['role' => 'system', 'content' => $this->systemPrompt($staff)]];
        foreach (array_slice($history, -10) as $turn) {
            if (in_array($turn['role'] ?? '', ['user', 'assistant'], true) && trim((string) ($turn['content'] ?? '')) !== '') {
                $messages[] = ['role' => $turn['role'], 'content' => (string) $turn['content']];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        $res = $this->openai->chat($user, $model, $messages, [
            'feature'     => $feature . '.chat',
            'related_id'  => $staff->id,
            'temperature' => 0.6,
            'max_tokens'  => 700,
            'reason'      => 'AI Staff (' . $staff->domainLabel() . ') chat',
        ]);

        $staff->markUsed();

        return [
            'reply'         => trim((string) ($res['content'] ?? '')),
            'credits_spent' => (int) ($res['credits_spent'] ?? 0),
            'model'         => (string) ($res['model'] ?? $model),
        ];
    }

    /** Builds the grounded system prompt for a staff member's domain. */
    public function systemPrompt(AiStaff $staff): string
    {
        $identity = "You are {$staff->name}, an AI staff member on the creator's Sayzio account, working the "
            . strtolower($staff->domainLabel()) . ' domain: ' . (AiStaff::DOMAIN_DESCRIPTIONS[$staff->domain] ?? '') . "\n\n"
            . 'Speak as this staff member, first person, on behalf of the creator when drafting anything client-facing. '
            . 'Never invent facts, amounts, dates or names not present in the data below. '
            . 'You cannot send emails, create invoices, or contact anyone directly — you can only propose actions '
            . 'the creator must explicitly confirm.';

        if (trim((string) $staff->instructions) !== '') {
            $identity .= "\n\nPersonality / instructions from the creator:\n" . trim($staff->instructions);
        }

        $snapshots = $this->snapshotsFor($staff);
        if ($snapshots !== '') {
            $identity .= "\n\nLive workspace data (Snapshots):\n" . $snapshots;
        }

        return $identity;
    }

    protected function snapshotsFor(AiStaff $staff): string
    {
        $user = $staff->user;
        $keys = match ($staff->domain) {
            AiStaff::DOMAIN_BILLING  => ['billing'],
            AiStaff::DOMAIN_CONTACTS => ['contacts'],
            AiStaff::DOMAIN_INBOX    => ['inbox'],
            AiStaff::DOMAIN_GENERAL  => ['profile', 'links', 'biolinks', 'analytics', 'audience', 'payments'],
            default => [],
        };

        $parts = [];
        foreach ($keys as $key) {
            $snap = trim($this->mind->snapshot($user, $key));
            if ($snap !== '') $parts[] = $snap;
        }
        return implode("\n\n", $parts);
    }

    /**
     * Parses a short free-text prompt into a draft invoice (line items +
     * notes + optional due date) via a JSON-mode OpenAI call, then stores
     * it as a pending AiStaffSuggestion — nothing is created until the
     * owner confirms via AiStaffSuggestionApplier.
     */
    public function draftInvoiceFromPrompt(User $user, AiStaff $staff, Workspace $ws, string $prompt): AiStaffSuggestion
    {
        $model = AiEngineSettings::featureModel($staff->featureKey(), $user);

        $system = <<<PROMPT
You turn a short natural-language request into a draft client invoice.
Respond with ONLY a JSON object of this exact shape:
{
  "line_items": [{"label": string, "quantity": number, "amount_minor": integer}],
  "notes_md": string|null,
  "due_in_days": integer|null,
  "recipient_hint": string|null
}
- amount_minor is the price PER UNIT in the smallest currency unit (cents), always a positive integer.
- quantity defaults to 1 if not specified.
- due_in_days is how many days from today the invoice should be due (null if not specified).
- recipient_hint is the client's name or email if mentioned, else null.
- Never invent prices that were not stated or clearly implied; if no amount is given for an item, omit that item.
- Output nothing except the JSON object.
PROMPT;

        $res = $this->openai->chat($user, $model, [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $prompt],
        ], [
            'response_format' => ['type' => 'json_object'],
            'feature'         => $staff->featureKey() . '.draft_invoice',
            'related_id'      => $staff->id,
            'reason'          => 'AI Staff (Billing) draft invoice from prompt',
            'max_tokens'      => 600,
        ]);

        $parsed = json_decode((string) ($res['content'] ?? ''), true);
        if (!is_array($parsed) || empty($parsed['line_items']) || !is_array($parsed['line_items'])) {
            throw new RuntimeException('Could not turn that into an invoice draft. Try describing the item(s) and price(s) more specifically.');
        }

        $items = [];
        foreach ($parsed['line_items'] as $li) {
            if (!is_array($li) || empty($li['label']) || !isset($li['amount_minor'])) continue;
            $items[] = [
                'label'        => mb_substr((string) $li['label'], 0, 240),
                'quantity'     => max(1, (int) ($li['quantity'] ?? 1)),
                'amount_minor' => max(0, (int) $li['amount_minor']),
            ];
        }
        if (empty($items)) {
            throw new RuntimeException('Could not identify any priced line items in that request.');
        }

        $dueDate = null;
        if (!empty($parsed['due_in_days']) && is_numeric($parsed['due_in_days'])) {
            $dueDate = now()->addDays((int) $parsed['due_in_days'])->toDateString();
        }

        $staff->markUsed();

        return AiStaffSuggestion::create([
            'ai_staff_id' => $staff->id,
            'user_id'     => $user->id,
            'kind'        => AiStaffSuggestion::KIND_DRAFT_INVOICE,
            'status'      => AiStaffSuggestion::STATUS_PENDING,
            'title'       => 'Draft invoice: ' . mb_strimwidth($items[0]['label'], 0, 60, '…'),
            'payload'     => [
                'workspace_id'    => $ws->id,
                'line_items'      => $items,
                'notes_md'        => is_string($parsed['notes_md'] ?? null) ? mb_substr($parsed['notes_md'], 0, 4000) : null,
                'due_date'        => $dueDate,
                'recipient_hint'  => is_string($parsed['recipient_hint'] ?? null) ? mb_substr($parsed['recipient_hint'], 0, 190) : null,
                'prompt'          => mb_substr($prompt, 0, 2000),
            ],
        ]);
    }

    /**
     * Deterministically scans the workspace's unpaid/overdue client
     * invoices and raises one pending "chase" suggestion per invoice that
     * doesn't already have one pending — idempotent across repeated calls
     * (e.g. a scheduled nudge). The chase message itself is AI-personalized
     * (charged) but the invoice selection logic is plain SQL, no AI needed.
     *
     * @return \Illuminate\Support\Collection<int,AiStaffSuggestion>
     */
    public function overdueInvoiceSuggestions(User $user, AiStaff $staff, Workspace $ws): \Illuminate\Support\Collection
    {
        $candidates = Invoice::query()
            ->where('workspace_id', $ws->id)
            ->where('kind', 'client')
            ->whereNotIn('status', ['paid', 'refunded', 'partially_refunded'])
            ->whereNotNull('recipient_email')
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        $existingInvoiceIds = AiStaffSuggestion::query()
            ->where('ai_staff_id', $staff->id)
            ->where('kind', AiStaffSuggestion::KIND_CHASE_INVOICE)
            ->where('status', AiStaffSuggestion::STATUS_PENDING)
            ->get()
            ->pluck('payload.invoice_id')
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->all();

        $created = collect();
        foreach ($candidates as $invoice) {
            if (in_array((int) $invoice->id, $existingInvoiceIds, true)) continue;
            $overdue = $invoice->due_date && $invoice->due_date->isPast();
            if (!$overdue && $invoice->sent_at) continue; // only chase overdue-sent or never-sent invoices

            $message = $this->chaseMessage($user, $staff, $invoice);

            $created->push(AiStaffSuggestion::create([
                'ai_staff_id' => $staff->id,
                'user_id'     => $user->id,
                'kind'        => AiStaffSuggestion::KIND_CHASE_INVOICE,
                'status'      => AiStaffSuggestion::STATUS_PENDING,
                'title'       => 'Chase invoice ' . $invoice->number,
                'payload'     => [
                    'invoice_id'     => $invoice->id,
                    'invoice_number' => $invoice->number,
                    'balance_minor'  => $invoice->balanceMinor(),
                    'currency'       => $invoice->currency,
                    'due_date'       => $invoice->due_date?->toDateString(),
                    'draft_message'  => $message,
                ],
            ]));
        }

        $staff->markUsed();

        return $created;
    }

    protected function chaseMessage(User $user, AiStaff $staff, Invoice $invoice): string
    {
        $model = AiEngineSettings::featureModel($staff->featureKey(), $user);
        $balance = number_format($invoice->balanceMinor() / 100, 2) . ' ' . strtoupper((string) $invoice->currency);

        $system = 'You write short, friendly, professional payment-reminder messages for a freelancer/business to send '
            . 'to a client about an unpaid invoice. One short paragraph, no subject line, no invented details.';
        $user_msg = sprintf(
            'Invoice %s, balance due %s, due date %s, status: %s. Write the reminder message.',
            $invoice->number,
            $balance,
            $invoice->due_date?->format('M j, Y') ?? 'not set',
            $invoice->sent_at ? 'already sent to client' : 'not yet sent to client'
        );

        $res = $this->openai->chat($user, $model, [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user_msg],
        ], [
            'feature'    => $staff->featureKey() . '.chase_message',
            'related_id' => $invoice->id,
            'reason'     => 'AI Staff (Billing) chase message',
            'max_tokens' => 300,
        ]);

        return trim((string) ($res['content'] ?? '')) ?: 'Just a friendly reminder that invoice ' . $invoice->number . ' (' . $balance . ') is due.';
    }

    /** @return array{summary:string,next_steps:array<int,string>} */
    public function summarizeContact(User $user, AiStaff $staff, Contact $contact): array
    {
        $model = AiEngineSettings::featureModel($staff->featureKey(), $user);
        $profile = $this->contactBrief($contact);

        $system = <<<PROMPT
You summarize a CRM contact/lead for a busy creator and suggest next steps.
Respond with ONLY a JSON object: {"summary": string, "next_steps": [string, ...]}
- summary: 2-3 sentences, plain language.
- next_steps: 2-4 short, concrete, actionable bullet points.
- Never invent facts not present in the contact data below.
PROMPT;

        $res = $this->openai->chat($user, $model, [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $profile],
        ], [
            'response_format' => ['type' => 'json_object'],
            'feature'         => $staff->featureKey() . '.summarize_contact',
            'related_id'      => $contact->id,
            'reason'          => 'AI Staff (Contacts) summarize',
            'max_tokens'      => 400,
        ]);

        $parsed = json_decode((string) ($res['content'] ?? ''), true);
        $staff->markUsed();

        return [
            'summary'    => is_array($parsed) ? trim((string) ($parsed['summary'] ?? '')) : '',
            'next_steps' => is_array($parsed) && is_array($parsed['next_steps'] ?? null)
                ? array_values(array_filter(array_map('strval', $parsed['next_steps'])))
                : [],
        ];
    }

    public function draftFollowup(User $user, AiStaff $staff, Contact $contact, string $goal = ''): string
    {
        $model = AiEngineSettings::featureModel($staff->featureKey(), $user);
        $profile = $this->contactBrief($contact);
        $goalLine = trim($goal) !== '' ? "\n\nGoal for this follow-up: " . trim($goal) : '';

        $system = 'You draft a short, warm, professional follow-up message for a creator to send to a contact/lead. '
            . 'One short paragraph, no subject line, no invented facts, no placeholders like "[Name]" left unresolved '
            . '— use the contact\'s actual name.';

        $res = $this->openai->chat($user, $model, [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $profile . $goalLine],
        ], [
            'feature'    => $staff->featureKey() . '.draft_followup',
            'related_id' => $contact->id,
            'reason'     => 'AI Staff (Contacts) draft follow-up',
            'max_tokens' => 350,
        ]);

        $staff->markUsed();

        return trim((string) ($res['content'] ?? ''));
    }

    protected function contactBrief(Contact $contact): string
    {
        $bits = array_filter([
            'Name: ' . $contact->nameForDisplay(),
            $contact->organization ? 'Organization: ' . $contact->organization : null,
            $contact->job_title ? 'Title: ' . $contact->job_title : null,
            !empty($contact->tags) ? 'Tags: ' . implode(', ', (array) $contact->tags) : null,
            $contact->notes ? 'Notes: ' . mb_substr((string) $contact->notes, 0, 800) : null,
            $contact->last_synced_at ? 'Last synced: ' . $contact->last_synced_at->diffForHumans() : null,
        ]);
        return implode("\n", $bits);
    }
}
