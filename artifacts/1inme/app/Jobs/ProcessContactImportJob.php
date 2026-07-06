<?php

namespace App\Jobs;

use App\Modules\User\Controllers\ContactController;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactEmail;
use App\Modules\User\Models\ContactImport;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\GoogleContactsAccount;
use App\Modules\User\Models\User;
use App\Modules\User\Services\Contacts\BiolinkAttachResolver;
use App\Modules\User\Services\Contacts\GoogleContactsSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Process a parsed contact-import in the background. The HTTP request only
 * parses the file and persists the parsed rows on the ContactImport record;
 * the slow per-row create + Google push happens here so users never wait
 * inside an HTTP timeout.
 */
class ProcessContactImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 minutes ceiling per import
    public int $tries = 1;       // failures get parked on the import row, not retried blindly

    public function __construct(public int $importId) {}

    public function handle(
        BiolinkAttachResolver $resolver,
        GoogleContactsSyncService $sync,
    ): void {
        /** @var ContactImport|null $import */
        $import = ContactImport::find($this->importId);
        if (!$import || $import->status === 'completed') return;

        $import->forceFill([
            'status'     => 'processing',
            'started_at' => $import->started_at ?? now(),
        ])->save();

        $userId = $import->user_id;
        $rows   = (array) ($import->rows ?? []);
        // Resume support: skip rows already processed if the worker died midway.
        $alreadyDone = (int) $import->processed_rows;

        // Account-wide count (contacts are an account-level address book) so
        // the plan cap is enforced consistently whether the job runs on a
        // queue worker (no workspace bound) or inline under the sync driver.
        $existingCount = Contact::withoutGlobalScope('workspace')->where('user_id', $userId)->count();
        $googleAccount = GoogleContactsAccount::where('user_id', $userId)->where('push_enabled', true)->first();
        $cap = ContactController::planContactsCap(User::find($userId));

        try {
            foreach ($rows as $i => $row) {
                if ($i < $alreadyDone) continue;
                $this->processRow($import, $row, $i, $existingCount, $cap, $resolver, $sync, $googleAccount);
                // Persist progress every 25 rows to keep the banner moving without
                // hammering the DB with a write per contact.
                if (($i + 1) % 25 === 0) $import->save();
            }

            $import->forceFill([
                'status'        => 'completed',
                'rows'          => null, // free the parsed payload now that we're done
                'completed_at'  => now(),
            ])->save();
        } catch (\Throwable $e) {
            $import->forceFill([
                'status'        => 'failed',
                'error'         => Str::limit($e->getMessage(), 500),
                'completed_at'  => now(),
            ])->save();
            throw $e;
        }
    }

    private function processRow(
        ContactImport $import,
        array $row,
        int $index,
        int &$existingCount,
        int $cap,
        BiolinkAttachResolver $resolver,
        GoogleContactsSyncService $sync,
        ?GoogleContactsAccount $googleAccount,
    ): void {
        $rowNum = $row['source_line'] ?? ($index + 1);
        $label  = $row['display_name'] ?: trim(($row['given_name'] ?? '') . ' ' . ($row['family_name'] ?? ''));
        $label  = $label !== '' ? $label : ('Row ' . $rowNum);

        $import->processed_rows = $index + 1;

        // -1 = unlimited (paid plans). Any other non-negative value caps the
        // user's address book; rows past the cap are recorded as "skipped".
        if ($cap !== -1 && $existingCount >= $cap) {
            $import->skipped_cap_count++;
            return;
        }

        $payload = [
            'display_name' => $row['display_name'] ?: trim(($row['given_name'] ?? '') . ' ' . ($row['family_name'] ?? '')),
            'given_name'   => $row['given_name'] ?? null,
            'family_name'  => $row['family_name'] ?? null,
            'organization' => $row['organization'] ?? null,
            'phones'       => $row['phones'] ?? [],
            'emails'       => $row['emails'] ?? [],
        ];

        $v = validator($payload, [
            'display_name' => 'nullable|string|max:191',
            'given_name'   => 'nullable|string|max:191',
            'family_name'  => 'nullable|string|max:191',
            'organization' => 'nullable|string|max:191',
            'phones'                 => 'nullable|array|max:10',
            'phones.*.label'         => 'nullable|string|max:50',
            'phones.*.value'         => 'nullable|string|max:80',
            'emails'                 => 'nullable|array|max:10',
            'emails.*.label'         => 'nullable|string|max:50',
            'emails.*.value'         => 'nullable|email|max:191',
        ]);
        if ($v->fails()) {
            $this->recordFailure($import, $rowNum, $label, $v->errors()->first());
            return;
        }

        $hasAnything = $payload['display_name'] || $payload['given_name'] || $payload['family_name']
            || !empty($payload['phones']) || !empty($payload['emails']);
        if (!$hasAnything) {
            $this->recordFailure($import, $rowNum, $label, 'No name, phone, or email found.');
            return;
        }

        try {
            $contact = DB::transaction(function () use ($import, $payload) {
                $c = Contact::create([
                    'user_id'      => $import->user_id,
                    'display_name' => $payload['display_name'] ?: trim(($payload['given_name'] ?? '') . ' ' . ($payload['family_name'] ?? '')),
                    'given_name'   => $payload['given_name'],
                    'family_name'  => $payload['family_name'],
                    'organization' => $payload['organization'],
                    'locally_modified_at' => now(),
                ]);
                $this->syncRows($c, $payload['phones'], $payload['emails']);
                return $c;
            });

            $resolver->resolveFor($contact->fresh('phones'));
            if ($googleAccount) {
                try { $sync->pushContact($googleAccount, $contact); }
                catch (\Throwable $e) { \Log::warning('Push contact failed (import job)', ['err' => $e->getMessage()]); }
            }

            $import->created_count++;
            $existingCount++;
        } catch (\Throwable $e) {
            $this->recordFailure($import, $rowNum, $label, Str::limit($e->getMessage(), 200));
        }
    }

    private function recordFailure(ContactImport $import, int $row, string $name, string $reason): void
    {
        $failed = $import->failed ?? [];
        // Cap failure log to keep the JSON column manageable on huge imports.
        if (count($failed) < 500) {
            $failed[] = ['row' => $row, 'name' => $name, 'reason' => $reason];
            $import->failed = $failed;
        }
    }

    private function syncRows(Contact $contact, array $phones, array $emails): void
    {
        foreach ($phones as $p) {
            $val = trim((string) ($p['value'] ?? ''));
            if ($val === '') continue;
            $contact->phones()->create([
                'label'      => $p['label'] ?? null,
                'value'      => $val,
                'value_e164' => ContactPhone::normalize($val),
                'is_primary' => false,
            ]);
        }
        foreach ($emails as $e) {
            $val = trim((string) ($e['value'] ?? ''));
            if ($val === '') continue;
            $contact->emails()->create([
                'label'      => $e['label'] ?? null,
                'value'      => ContactEmail::normalize($val),
                'is_primary' => false,
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        $import = ContactImport::find($this->importId);
        if ($import && $import->status !== 'completed') {
            $import->forceFill([
                'status'        => 'failed',
                'error'         => Str::limit($e->getMessage(), 500),
                'completed_at'  => now(),
            ])->save();
        }
    }
}
