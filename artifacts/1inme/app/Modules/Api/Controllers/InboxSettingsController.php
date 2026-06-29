<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\User;
use App\Modules\User\Services\SpamChecker;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Inbox spam-settings API: mobile parity for the web Spam Settings page
 * (User\InboxController@settings/updateSettings/disableKeyword/
 * enableDefaultKeyword/importTrustedCsv). State lives in
 * user.settings['spam'] and is evaluated by SpamChecker at intake.
 *
 * The web controller is the source of truth for normalization and the
 * disabled-default audit trail; this exposes the same logic over the
 * unified {data}/{error} envelope.
 */
class InboxSettingsController extends Controller
{
    use ApiResponses;

    public function show(Request $request)
    {
        $user = $request->user();

        return $this->ok($this->buildPayload($user));
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'blocked_keywords'            => 'nullable|array',
            'blocked_keywords.*'          => 'string|max:200',
            'disabled_default_keywords'   => 'nullable|array',
            'disabled_default_keywords.*' => 'string|max:200',
            'trusted_emails'              => 'nullable|array',
            'trusted_emails.*'            => 'string|max:320',
            'trusted_phones'              => 'nullable|array',
            'trusted_phones.*'            => 'string|max:40',
        ]);

        $checker = app(SpamChecker::class);
        $defaultLowerSet = array_map('mb_strtolower', SpamChecker::BLOCKED_KEYWORDS);

        $blocked = $this->cleanStrings($validated['blocked_keywords'] ?? []);

        $disabledRaw = array_map('mb_strtolower', $this->cleanStrings($validated['disabled_default_keywords'] ?? []));
        $disabled = array_values(array_intersect($disabledRaw, $defaultLowerSet));

        // Drop user keywords that duplicate a default they didn't disable.
        $blocked = array_values(array_filter(
            $blocked,
            fn ($kw) => !in_array(mb_strtolower($kw), $defaultLowerSet, true)
                   || in_array(mb_strtolower($kw), $disabled, true)
        ));

        $emails = array_values(array_filter(array_map(
            fn ($e) => $checker->normalizeEmail($e),
            $this->cleanStrings($validated['trusted_emails'] ?? [])
        ), fn ($e) => $e !== null && filter_var($e, FILTER_VALIDATE_EMAIL)));

        $phones = array_values(array_filter(array_map(
            fn ($p) => $checker->normalizePhone($p),
            $this->cleanStrings($validated['trusted_phones'] ?? [])
        )));

        $settings = $user->settings ?? [];
        $existingSpam = $settings['spam'] ?? [];
        $existingMeta = (array) ($existingSpam['disabled_default_keywords_meta'] ?? []);

        // Stash the canonical default casing for each disabled keyword and
        // maintain the disabled-at audit trail (keep existing timestamps,
        // stamp new ones with now()).
        $defaultsLowerToCanonical = [];
        foreach (SpamChecker::BLOCKED_KEYWORDS as $kw) {
            $defaultsLowerToCanonical[mb_strtolower($kw)] = $kw;
        }
        $disabledCanonical = [];
        $newMeta = [];
        foreach (array_values(array_unique($disabled)) as $lower) {
            $canonical = $defaultsLowerToCanonical[$lower] ?? $lower;
            $disabledCanonical[] = $canonical;
            $newMeta[$lower] = isset($existingMeta[$lower]) && is_string($existingMeta[$lower])
                ? $existingMeta[$lower]
                : now()->toIso8601String();
        }

        $settings['spam'] = [
            'blocked_keywords'               => array_values(array_unique($blocked)),
            'disabled_default_keywords'      => $disabledCanonical,
            'disabled_default_keywords_meta' => $newMeta,
            'trusted_emails'                 => array_values(array_unique($emails)),
            'trusted_phones'                 => array_values(array_unique($phones)),
        ];
        $user->update(['settings' => $settings]);

        return $this->ok($this->buildPayload($user->fresh()));
    }

    /**
     * One-click "stop blocking this keyword": disables a default or removes
     * a custom keyword. Unknown keywords are a no-op error.
     */
    public function disableKeyword(Request $request)
    {
        $validated = $request->validate(['keyword' => 'required|string|max:200']);
        $kwRaw = trim($validated['keyword']);
        if ($kwRaw === '') {
            return $this->fail('No keyword provided.', 422, 'no_keyword');
        }
        $kwLower = mb_strtolower($kwRaw);

        $user = $request->user();
        $settings = $user->settings ?? [];
        $spam = $settings['spam'] ?? [];

        $defaultsLower = array_map('mb_strtolower', SpamChecker::BLOCKED_KEYWORDS);
        $blocked  = array_values(array_filter((array) ($spam['blocked_keywords'] ?? []), 'is_string'));
        $disabled = array_values(array_filter((array) ($spam['disabled_default_keywords'] ?? []), 'is_string'));
        $meta     = (array) ($spam['disabled_default_keywords_meta'] ?? []);

        $changed = false;
        if (in_array($kwLower, $defaultsLower, true)) {
            $disabledLower = array_map('mb_strtolower', $disabled);
            if (!in_array($kwLower, $disabledLower, true)) {
                $idx = array_search($kwLower, $defaultsLower, true);
                $disabled[] = SpamChecker::BLOCKED_KEYWORDS[$idx];
                $meta[$kwLower] = now()->toIso8601String();
                $changed = true;
            }
        }

        $newBlocked = array_values(array_filter($blocked, fn ($kw) => mb_strtolower($kw) !== $kwLower));
        if (count($newBlocked) !== count($blocked)) {
            $blocked = $newBlocked;
            $changed = true;
        }

        if (!$changed) {
            return $this->fail('“' . $kwRaw . '” isn\'t in your blocked keyword list.', 422, 'not_blocked');
        }

        $disabledLowerSet = array_flip(array_map('mb_strtolower', $disabled));
        $meta = array_intersect_key($meta, $disabledLowerSet);

        $spam['blocked_keywords']               = array_values(array_unique($blocked));
        $spam['disabled_default_keywords']      = array_values(array_unique($disabled));
        $spam['disabled_default_keywords_meta'] = $meta;
        $settings['spam'] = $spam;
        $user->update(['settings' => $settings]);

        return $this->ok([
            'message' => 'Stopped blocking “' . $kwRaw . '”.',
            'spam'    => $this->normalizedSpam($user->fresh()),
        ]);
    }

    /**
     * Undo a previously-disabled default keyword so the protection takes
     * effect again on new submissions.
     */
    public function enableDefaultKeyword(Request $request)
    {
        $validated = $request->validate(['keyword' => 'required|string|max:200']);
        $kwRaw = trim($validated['keyword']);
        if ($kwRaw === '') {
            return $this->fail('No keyword provided.', 422, 'no_keyword');
        }
        $kwLower = mb_strtolower($kwRaw);

        $defaultsLower = array_map('mb_strtolower', SpamChecker::BLOCKED_KEYWORDS);
        if (!in_array($kwLower, $defaultsLower, true)) {
            return $this->fail('“' . $kwRaw . '” isn\'t a default keyword.', 422, 'not_default');
        }

        $user = $request->user();
        $settings = $user->settings ?? [];
        $spam = $settings['spam'] ?? [];
        $disabled = array_values(array_filter((array) ($spam['disabled_default_keywords'] ?? []), 'is_string'));
        $meta     = (array) ($spam['disabled_default_keywords_meta'] ?? []);

        $newDisabled = array_values(array_filter($disabled, fn ($kw) => mb_strtolower($kw) !== $kwLower));
        if (count($newDisabled) === count($disabled)) {
            return $this->fail('“' . $kwRaw . '” wasn\'t disabled.', 422, 'not_disabled');
        }
        unset($meta[$kwLower]);

        $spam['disabled_default_keywords']      = $newDisabled;
        $spam['disabled_default_keywords_meta'] = $meta;
        $settings['spam'] = $spam;
        $user->update(['settings' => $settings]);

        return $this->ok([
            'message' => 'Re-enabled the default keyword “' . $kwRaw . '”.',
            'spam'    => $this->normalizedSpam($user->fresh()),
        ]);
    }

    /**
     * Import trusted emails/phones from an uploaded CSV/text file. Mirrors
     * the web header-detection + guess-by-shape logic.
     */
    public function importTrustedCsv(Request $request)
    {
        $request->validate([
            'csv' => 'required|file|max:5120|mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel|mimes:csv,txt',
        ]);

        $user = $request->user();
        $checker = app(SpamChecker::class);

        $settings = $user->settings ?? [];
        $spam = $settings['spam'] ?? [];

        $trustedEmails = [];
        foreach ((array) ($spam['trusted_emails'] ?? []) as $e) {
            if (!is_string($e)) continue;
            $n = $checker->normalizeEmail($e);
            if ($n !== null) $trustedEmails[] = $n;
        }
        $trustedPhones = [];
        foreach ((array) ($spam['trusted_phones'] ?? []) as $p) {
            if (!is_string($p)) continue;
            $n = $checker->normalizePhone($p);
            if ($n !== null) $trustedPhones[] = $n;
        }
        $trustedEmails = array_values(array_unique($trustedEmails));
        $trustedPhones = array_values(array_unique($trustedPhones));
        $existingEmails = array_flip($trustedEmails);
        $existingPhones = array_flip($trustedPhones);

        $emailsAdded = 0; $phonesAdded = 0; $duplicates = 0;
        $invalidValues = 0; $invalidRows = 0; $rowsRead = 0;

        $emailKeys = ['email', 'e_mail', 'email_address', 'mail'];
        $phoneKeys = ['phone', 'tel', 'telephone', 'mobile', 'phone_number', 'cell'];

        $h = fopen($request->file('csv')->getRealPath(), 'r');
        if ($h === false) {
            return $this->fail('Could not read uploaded file.', 422, 'unreadable');
        }

        $first = fgetcsv($h);
        if ($first === false) {
            fclose($h);
            return $this->fail('CSV is empty.', 422, 'empty');
        }
        if (isset($first[0])) {
            $first[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $first[0]);
        }

        $emailIdx = null; $phoneIdx = null; $hasHeader = false;
        foreach ($first as $i => $cell) {
            $key = mb_strtolower(trim((string) $cell));
            if (in_array($key, $emailKeys, true)) { $emailIdx = $i; $hasHeader = true; }
            if (in_array($key, $phoneKeys, true)) { $phoneIdx = $i; $hasHeader = true; }
        }

        $process = function (array $row) use (
            &$emailsAdded, &$phonesAdded, &$duplicates, &$invalidValues, &$invalidRows, &$rowsRead,
            &$existingEmails, &$existingPhones, &$trustedEmails, &$trustedPhones,
            $emailIdx, $phoneIdx, $checker
        ) {
            $rowsRead++;
            $rowEmails = [];
            $rowPhones = [];

            if ($emailIdx !== null && isset($row[$emailIdx])) $rowEmails[] = $row[$emailIdx];
            if ($phoneIdx !== null && isset($row[$phoneIdx])) $rowPhones[] = $row[$phoneIdx];
            if ($emailIdx === null && $phoneIdx === null) {
                foreach ($row as $cell) {
                    $cell = trim((string) $cell);
                    if ($cell === '') continue;
                    if (filter_var($cell, FILTER_VALIDATE_EMAIL)) {
                        $rowEmails[] = $cell;
                    } elseif (preg_match('/^\+?[\d().\-\s]{7,}$/', $cell)) {
                        $rowPhones[] = $cell;
                    }
                }
            }

            $usedAny = false;
            foreach ($rowEmails as $raw) {
                $raw = trim((string) $raw);
                if ($raw === '') continue;
                $usedAny = true;
                $norm = $checker->normalizeEmail($raw);
                if ($norm === null || !filter_var($norm, FILTER_VALIDATE_EMAIL)) { $invalidValues++; continue; }
                if (isset($existingEmails[$norm])) { $duplicates++; continue; }
                $existingEmails[$norm] = true;
                $trustedEmails[] = $norm;
                $emailsAdded++;
            }
            foreach ($rowPhones as $raw) {
                $raw = trim((string) $raw);
                if ($raw === '') continue;
                $usedAny = true;
                $norm = $checker->normalizePhone($raw);
                if ($norm === null || strlen($norm) < 7) { $invalidValues++; continue; }
                if (isset($existingPhones[$norm])) { $duplicates++; continue; }
                $existingPhones[$norm] = true;
                $trustedPhones[] = $norm;
                $phonesAdded++;
            }
            if (!$usedAny) $invalidRows++;
        };

        if (!$hasHeader) $process($first);
        while (($row = fgetcsv($h)) !== false) {
            if (count($row) === 1 && trim((string) $row[0]) === '') continue;
            $process($row);
        }
        fclose($h);

        $settings['spam'] = array_merge($spam, [
            'trusted_emails' => array_values(array_unique($trustedEmails)),
            'trusted_phones' => array_values(array_unique($trustedPhones)),
        ]);
        $user->update(['settings' => $settings]);

        return $this->ok([
            'stats' => [
                'rows_read'      => $rowsRead,
                'emails_added'   => $emailsAdded,
                'phones_added'   => $phonesAdded,
                'duplicates'     => $duplicates,
                'invalid_values' => $invalidValues,
                'invalid_rows'   => $invalidRows,
            ],
            'spam' => $this->normalizedSpam($user->fresh()),
        ]);
    }

    // ---- helpers --------------------------------------------------------

    /** @return array<int,string> */
    private function cleanStrings($list): array
    {
        if (!is_array($list)) return [];
        $out = [];
        foreach ($list as $item) {
            if (!is_string($item)) continue;
            $v = trim($item);
            if ($v !== '') $out[] = $v;
        }
        return array_values(array_unique($out));
    }

    /** Normalized spam settings for response payloads. */
    private function normalizedSpam(User $user): array
    {
        $checker = app(SpamChecker::class);
        $spam = $checker->loadUserSpamSettings($user->id);

        // Re-canonicalize disabled defaults to their default casing so the
        // mobile checkboxes match by exact value.
        $defaultsLowerToCanonical = [];
        foreach (SpamChecker::BLOCKED_KEYWORDS as $kw) {
            $defaultsLowerToCanonical[mb_strtolower($kw)] = $kw;
        }
        $spam['disabled_default_keywords'] = array_values(array_map(
            fn ($kw) => $defaultsLowerToCanonical[mb_strtolower($kw)] ?? $kw,
            (array) ($spam['disabled_default_keywords'] ?? [])
        ));

        return $spam;
    }

    /** Full settings payload incl. defaults + disabled-default audit list. */
    private function buildPayload(User $user): array
    {
        $spam = $this->normalizedSpam($user);

        $defaultsLowerToCanonical = [];
        foreach (SpamChecker::BLOCKED_KEYWORDS as $kw) {
            $defaultsLowerToCanonical[mb_strtolower($kw)] = $kw;
        }

        $rawSpam = ($user->settings ?? [])['spam'] ?? [];
        $meta = (array) ($rawSpam['disabled_default_keywords_meta'] ?? []);
        $disabledDefaults = [];
        foreach ((array) ($spam['disabled_default_keywords'] ?? []) as $kw) {
            if (!is_string($kw)) continue;
            $lower = mb_strtolower($kw);
            if (!isset($defaultsLowerToCanonical[$lower])) continue;
            $disabledDefaults[] = [
                'keyword'     => $defaultsLowerToCanonical[$lower],
                'disabled_at' => isset($meta[$lower]) && is_string($meta[$lower]) ? $meta[$lower] : null,
            ];
        }
        usort($disabledDefaults, function ($a, $b) {
            if ($a['disabled_at'] === null && $b['disabled_at'] === null) return 0;
            if ($a['disabled_at'] === null) return 1;
            if ($b['disabled_at'] === null) return -1;
            return strcmp($b['disabled_at'], $a['disabled_at']);
        });

        return [
            'spam'              => $spam,
            'defaults'          => array_values(SpamChecker::BLOCKED_KEYWORDS),
            'disabled_defaults' => $disabledDefaults,
        ];
    }
}
