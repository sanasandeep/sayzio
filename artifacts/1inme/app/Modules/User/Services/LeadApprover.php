<?php

namespace App\Modules\User\Services;

use App\Modules\Common\Services\ContactCandidateValidator;
use App\Modules\User\Controllers\ContactController;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactEmail;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\Lead;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Turns a pending lead (an in-memory row from {@see LeadAggregator}) into a
 * Contact, or just dismisses it. Both paths write a {@see Lead} state row
 * so the item never resurfaces in the pending queue. Approval respects the
 * plan's contact cap and dedupes against existing contacts the same way
 * the manual "Add contact" flow does (via {@see ContactCandidateValidator}).
 */
class LeadApprover
{
    public const RESULT_CREATED = 'created';
    public const RESULT_MERGED  = 'merged';

    /**
     * @throws \RuntimeException when the plan's contact cap is reached and
     *         the lead has no existing-contact match to merge into.
     */
    public function approve(User $owner, array $item, ?int $actorUserId = null): array
    {
        $candidate = new ContactCandidateValidator($owner->id);
        $candidate->validate([
            'display_name' => $item['name'] ?? null,
            'emails'       => $item['email'] ? [['label' => 'Other', 'value' => $item['email']]] : [],
            'phones'       => $item['phone'] ? [['label' => 'Other', 'value' => $item['phone']]] : [],
            'source_url'   => null,
        ]);

        $duplicateOf = $candidate->duplicateOf;
        $normalized  = $candidate->normalized;

        if (!$duplicateOf) {
            $cap = ContactController::planContactsCap($owner);
            $existing = Contact::where('user_id', $owner->id)->count();
            if ($cap !== -1 && $existing >= $cap) {
                throw new \RuntimeException("You've reached your plan's contact limit ({$cap}). Upgrade your plan to approve more leads.");
            }
        }

        return DB::transaction(function () use ($owner, $item, $normalized, $duplicateOf, $actorUserId) {
            if ($duplicateOf) {
                $contact = Contact::find($duplicateOf);
                $enriched = $this->fillMissingFields($contact, $item);
                $this->addSource($contact, $item);
                // Route merged/enriched contacts through the same CRM push a
                // newly-created contact gets (loop-safe crm:-source skip +
                // "only if a CRM is connected" cheap-check live in the helper).
                // Only when new email/phone was actually added, so re-approving
                // the same lead doesn't fire redundant CRM writes.
                if ($enriched) {
                    $contact->queueCrmPush();
                }
                $result = self::RESULT_MERGED;
            } else {
                $contact = Contact::create([
                    'user_id'             => $owner->id,
                    'display_name'        => $normalized['display_name'] ?: ($item['name'] ?? null) ?: ($item['email'] ?? $item['phone'] ?? 'Lead'),
                    'notes'               => $this->buildNote($item),
                    'sources'             => [$this->sourceTag($item)],
                    'locally_modified_at' => now(),
                ]);
                if (!empty($item['email'])) {
                    $contact->emails()->create([
                        'label' => 'Other', 'value' => ContactEmail::normalize($item['email']), 'is_primary' => true,
                    ]);
                }
                if (!empty($item['phone'])) {
                    $contact->phones()->create([
                        'label' => 'Other', 'value' => $item['phone'], 'value_e164' => ContactPhone::normalize($item['phone']), 'is_primary' => true,
                    ]);
                }
                $result = self::RESULT_CREATED;
            }

            $this->writeLead($owner, $item, Lead::STATUS_APPROVED, $contact->id, $actorUserId);

            return ['result' => $result, 'contact' => $contact];
        });
    }

    public function dismiss(User $owner, array $item, ?int $actorUserId = null): void
    {
        $this->writeLead($owner, $item, Lead::STATUS_DISMISSED, null, $actorUserId);
    }

    /**
     * Dry-run a batch of {source_type, source_id} rows and classify what
     * each would do on approve — WITHOUT persisting anything — so the UI
     * can warn the creator before they blow past their plan's contact cap.
     *
     * Classification mirrors {@see approve()}: an item that dedupes into an
     * existing contact "merges" (never counts against the cap); everything
     * else "creates" (consumes one cap slot). We simulate the same
     * sequential ordering the real bulk loop uses, so a later item that
     * matches a contact an earlier item in the batch would have created is
     * also counted as a merge — otherwise the preview would over-count
     * creates and warn about blocks that won't actually happen.
     *
     * @param  array<int, array{source_type: string, source_id: int}>  $items
     * @return array{
     *   total:int, created:int, merged:int, blocked:int, gone:int,
     *   approvable:int, cap:int, existing:int, remaining:int
     * }
     */
    public function planBatch(User $owner, array $items): array
    {
        $aggregator = new LeadAggregator($owner->id);

        $cap      = ContactController::planContactsCap($owner);
        $existing = Contact::where('user_id', $owner->id)->count();
        $capacity = $cap === -1 ? PHP_INT_MAX : max(0, $cap - $existing);

        $created = 0;
        $merged  = 0;
        $blocked = 0;
        $gone    = 0;

        // Emails/phones of contacts this batch would create earlier on, so a
        // later duplicate within the same batch merges instead of creating.
        $pendingEmails = [];
        $pendingPhones = [];

        foreach ($items as $row) {
            $item = $aggregator->find((string) ($row['source_type'] ?? ''), (int) ($row['source_id'] ?? 0));
            if (!$item) {
                $gone++;
                continue;
            }

            $candidate = new ContactCandidateValidator($owner->id);
            $candidate->validate([
                'display_name' => $item['name'] ?? null,
                'emails'       => !empty($item['email']) ? [['label' => 'Other', 'value' => $item['email']]] : [],
                'phones'       => !empty($item['phone']) ? [['label' => 'Other', 'value' => $item['phone']]] : [],
                'source_url'   => null,
            ]);

            $email = $candidate->normalized['emails'][0]['value'] ?? null;
            $phone = $candidate->normalized['phones'][0]['value_e164'] ?? null;

            $mergesIntoExisting = (bool) $candidate->duplicateOf;
            $mergesIntoBatch    = (!$mergesIntoExisting)
                && (($email !== null && isset($pendingEmails[$email]))
                    || ($phone !== null && isset($pendingPhones[$phone])));

            if ($mergesIntoExisting || $mergesIntoBatch) {
                $merged++;
                continue;
            }

            if ($capacity > 0) {
                $created++;
                $capacity--;
                if ($email !== null) $pendingEmails[$email] = true;
                if ($phone !== null) $pendingPhones[$phone] = true;
            } else {
                $blocked++;
            }
        }

        return [
            'total'      => count($items),
            'created'    => $created,
            'merged'     => $merged,
            'blocked'    => $blocked,
            'gone'       => $gone,
            'approvable' => $created + $merged,
            'cap'        => $cap,
            'existing'   => $existing,
            'remaining'  => $cap === -1 ? -1 : max(0, $cap - $existing),
        ];
    }

    /**
     * Add any newly-captured email/phone that the existing contact doesn't
     * already have, comparing by normalized value (not just "has none at
     * all") so a second matching lead can still contribute a new work email
     * or alternate number instead of being silently dropped.
     *
     * @return bool true when a new email/phone was actually added, so the
     *   caller can push the enriched contact to connected CRMs only when
     *   there's genuinely new data to sync.
     */
    protected function fillMissingFields(Contact $contact, array $item): bool
    {
        $changed = false;

        if (!empty($item['email'])) {
            $normalizedEmail = ContactEmail::normalize($item['email']);
            $hasEmail = $contact->emails()
                ->get()
                ->contains(fn (ContactEmail $e) => ContactEmail::normalize($e->value) === $normalizedEmail);
            if (!$hasEmail) {
                $contact->emails()->create([
                    'label' => 'Other', 'value' => $normalizedEmail, 'is_primary' => $contact->emails()->count() === 0,
                ]);
                $changed = true;
            }
        }

        if (!empty($item['phone'])) {
            $normalizedPhone = ContactPhone::normalize($item['phone']);
            $hasPhone = $contact->phones()
                ->get()
                ->contains(fn (ContactPhone $p) => ContactPhone::normalize($p->value_e164 ?: $p->value) === $normalizedPhone);
            if (!$hasPhone) {
                $contact->phones()->create([
                    'label' => 'Other', 'value' => $item['phone'], 'value_e164' => $normalizedPhone, 'is_primary' => $contact->phones()->count() === 0,
                ]);
                $changed = true;
            }
        }

        if ($changed) {
            $contact->update(['locally_modified_at' => now()]);
        }

        return $changed;
    }

    /**
     * Record which capture surface(s) fed a contact, mirroring the `crm:`
     * tag convention CrmSyncService already uses on `contacts.sources`.
     * Append-if-missing so re-approving the same source type is a no-op.
     */
    protected function addSource(Contact $contact, array $item): void
    {
        $tag = $this->sourceTag($item);
        $sources = collect($contact->sources ?? [])->push($tag)->unique()->values()->all();
        if ($sources !== ($contact->sources ?? [])) {
            $contact->sources = $sources;
            $contact->save();
        }
    }

    protected function sourceTag(array $item): string
    {
        return 'lead:' . $item['source_type'];
    }

    protected function buildNote(array $item): string
    {
        $label = LeadAggregator::sourceLabels()[$item['source_type']] ?? $item['source_type'];
        $note = "Captured via {$label}";
        if (!empty($item['context'])) $note .= " ({$item['context']})";
        return $note . '.';
    }

    protected function writeLead(User $owner, array $item, string $status, ?int $contactId, ?int $actorUserId): Lead
    {
        return Lead::updateOrCreate(
            ['source_type' => $item['source_type'], 'source_id' => $item['source_id']],
            [
                'user_id'       => $owner->id,
                'workspace_id'  => app()->bound('current_workspace') ? app('current_workspace')?->id : null,
                'status'        => $status,
                'contact_id'    => $contactId,
                'actor_user_id' => $actorUserId,
                'approved_at'   => $status === Lead::STATUS_APPROVED ? now() : null,
                'dismissed_at'  => $status === Lead::STATUS_DISMISSED ? now() : null,
            ]
        );
    }
}
