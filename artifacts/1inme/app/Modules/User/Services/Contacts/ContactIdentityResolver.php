<?php

namespace App\Modules\User\Services\Contacts;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactEmail;
use App\Modules\User\Models\ContactPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Central identity resolution for customer captures (Task #6501).
 *
 * Given the owning creator + whatever identity a public flow captured
 * (email / phone / name), finds that creator's matching Contact — or
 * auto-creates one flagged is_auto_captured. Matching is strictly scoped to
 * the owner's own address book (contacts.user_id), so a match can never
 * cross users or workspaces.
 *
 * Safety contract: resolve() NEVER throws — it is called from queued jobs
 * fed by public/unauthenticated flows (orders, bookings, reviews, …) and a
 * linking failure must never surface into the customer path. Plan contact
 * caps are NOT enforced here: auto-captured linking must never block (or
 * silently drop) a customer order/subscription, so creation always proceeds;
 * cap enforcement stays on the manual "add contact" surfaces.
 */
class ContactIdentityResolver
{
    /**
     * Find or create the owner's Contact for the given identity.
     * Returns null when there is nothing addressable (no email AND no
     * phone), when the plan cap prevents creating a new contact, or on any
     * unexpected error.
     */
    public function resolve(int $ownerUserId, ?string $email, ?string $phone, ?string $name = null, string $source = 'capture'): ?Contact
    {
        try {
            $email = $this->cleanEmail($email);
            $phone = $this->cleanPhone($phone);
            if ($email === null && $phone === null) {
                return null;
            }

            $existing = $this->match($ownerUserId, $email, $phone);
            if ($existing) {
                $this->enrich($existing, $email, $phone);
                return $existing;
            }

            // Plan contact caps are intentionally NOT enforced for
            // auto-captured linking: creating the contact must never block
            // (or silently drop) a customer order/subscription. Cap
            // enforcement stays on the manual "add contact" surfaces.
            return $this->create($ownerUserId, $email, $phone, $name, $source);
        } catch (\Throwable $e) {
            Log::warning('ContactIdentityResolver: resolve failed', [
                'owner' => $ownerUserId,
                'source' => $source,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /** Match an existing contact by normalized email first, then phone. */
    public function match(int $ownerUserId, ?string $email, ?string $phone): ?Contact
    {
        if ($email !== null) {
            $contactId = ContactEmail::query()
                ->join('contacts', 'contacts.id', '=', 'contact_emails.contact_id')
                ->where('contacts.user_id', $ownerUserId)
                ->whereRaw('LOWER(TRIM(contact_emails.value)) = ?', [ContactEmail::normalize($email)])
                ->orderBy('contact_emails.contact_id')
                ->value('contact_emails.contact_id');
            if ($contactId) {
                return Contact::withoutGlobalScope('workspace')->find($contactId);
            }
        }

        if ($phone !== null) {
            $normalized = ContactPhone::normalize($phone);
            $contactId = ContactPhone::query()
                ->join('contacts', 'contacts.id', '=', 'contact_phones.contact_id')
                ->where('contacts.user_id', $ownerUserId)
                ->where(function ($q) use ($normalized) {
                    $q->where('contact_phones.value_e164', $normalized)
                      ->orWhereRaw(
                          "REGEXP_REPLACE(contact_phones.value, '[\\s\\-\\(\\)\\.]+', '', 'g') = ?",
                          [$normalized]
                      );
                })
                ->orderBy('contact_phones.contact_id')
                ->value('contact_phones.contact_id');
            if ($contactId) {
                return Contact::withoutGlobalScope('workspace')->find($contactId);
            }
        }

        return null;
    }

    /**
     * Add any newly-seen identifier to a matched contact so future captures
     * by the other channel also match. Quiet best-effort; never throws (the
     * caller already wraps us).
     */
    protected function enrich(Contact $contact, ?string $email, ?string $phone): void
    {
        if ($email !== null) {
            $norm = ContactEmail::normalize($email);
            $has = $contact->emails()->get()->contains(
                fn ($row) => ContactEmail::normalize((string) $row->value) === $norm
            );
            if (!$has) {
                $contact->emails()->create(['label' => 'other', 'value' => $email, 'is_primary' => !$contact->emails()->exists()]);
            }
        }
        if ($phone !== null) {
            $norm = ContactPhone::normalize($phone);
            $has = $contact->phones()->get()->contains(
                fn ($row) => ContactPhone::normalize((string) ($row->value_e164 ?: $row->value)) === $norm
            );
            if (!$has) {
                $contact->phones()->create([
                    'label' => 'other', 'value' => $phone,
                    'value_e164' => $norm, 'is_primary' => !$contact->phones()->exists(),
                ]);
            }
        }
    }

    protected function create(int $ownerUserId, ?string $email, ?string $phone, ?string $name, string $source): Contact
    {
        return DB::transaction(function () use ($ownerUserId, $email, $phone, $name, $source) {
            $name = trim((string) $name);
            $parts = $name !== '' ? preg_split('/\s+/', $name, 2) : [];

            $contact = new Contact([
                'user_id'          => $ownerUserId,
                'display_name'     => $name !== '' ? $name : ($email ?: $phone),
                'given_name'       => $parts[0] ?? null,
                'family_name'      => $parts[1] ?? null,
                'sources'          => ['auto:' . $source],
                'is_auto_captured' => true,
            ]);
            // Auto-captured contacts belong to the owner account, not the
            // acting workspace (there is none on public flows).
            $contact->setAttribute('workspace_id', null);
            $contact->saveQuietly();
            // saveQuietly skips the created hook — push to CRM explicitly so
            // auto-captured contacts behave like every other capture path.
            $contact->queueCrmPush();

            if ($email !== null) {
                $contact->emails()->create(['label' => 'other', 'value' => $email, 'is_primary' => true]);
            }
            if ($phone !== null) {
                $contact->phones()->create([
                    'label' => 'other', 'value' => $phone,
                    'value_e164' => ContactPhone::normalize($phone), 'is_primary' => true,
                ]);
            }

            return $contact;
        });
    }

    protected function cleanEmail(?string $email): ?string
    {
        $email = trim((string) $email);
        return ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) ? $email : null;
    }

    protected function cleanPhone(?string $phone): ?string
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return null;
        }
        // Require a plausible phone: at least 6 digits once formatting is stripped.
        $digits = preg_replace('/\D+/', '', $phone);
        return strlen((string) $digits) >= 6 ? $phone : null;
    }

    /**
     * Sniff submitter identity out of a form submission's payload. Mirrors
     * the trusted-sender sniff in FormController::publicSubmit so both paths
     * agree on who submitted.
     *
     * @param array<string,mixed> $data
     * @return array{email:?string, phone:?string, name:?string}
     */
    public static function identityFromFormData(array $data): array
    {
        $email = null;
        foreach (['email', 'Email', 'email_address', 'e_mail'] as $k) {
            if (!empty($data[$k]) && is_string($data[$k]) && filter_var($data[$k], FILTER_VALIDATE_EMAIL)) {
                $email = $data[$k];
                break;
            }
        }
        if ($email === null) {
            foreach ($data as $v) {
                if (is_string($v) && filter_var($v, FILTER_VALIDATE_EMAIL)) {
                    $email = $v;
                    break;
                }
            }
        }

        $phone = null;
        foreach (['phone', 'Phone', 'tel', 'mobile', 'phone_number'] as $k) {
            if (!empty($data[$k]) && is_string($data[$k])) {
                $phone = $data[$k];
                break;
            }
        }
        if ($phone === null) {
            foreach ($data as $key => $v) {
                if (!is_string($v)) {
                    continue;
                }
                if (preg_match('/(phone|tel|mobile|whatsapp)/i', (string) $key)
                    && preg_match('/[\d][\d\-\s().+]{6,}/', $v)) {
                    $phone = $v;
                    break;
                }
            }
        }

        $name = null;
        foreach (['name', 'Name', 'full_name', 'first_name'] as $k) {
            if (!empty($data[$k])) {
                if (is_string($data[$k])) {
                    $name = $data[$k];
                    break;
                }
                if (is_array($data[$k])) { // full_name fields store {first,last}
                    $joined = trim(implode(' ', array_filter([
                        $data[$k]['first'] ?? null, $data[$k]['last'] ?? null,
                    ], 'is_string')));
                    if ($joined !== '') {
                        $name = $joined;
                        break;
                    }
                }
            }
        }

        return ['email' => $email, 'phone' => $phone, 'name' => $name];
    }
}
