<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactEmail;
use App\Modules\User\Models\ContactPhone;
use Illuminate\Support\Facades\Cache;

/**
 * Validate + normalize a contact candidate coming from the browser
 * extension (or any other client). Emits per-field errors, normalized
 * values, and a duplicate match (if any) against the signed-in user's
 * existing contacts. Used as a hard gate on every save so a one-click
 * action can't pollute the address book.
 */
class ContactCandidateValidator
{
    /** Per-field error bag keyed by dotted path (e.g. "emails.0.value"). */
    public array $errors = [];

    /** Normalized payload safe to persist. */
    public array $normalized = [];

    /** Existing contact id matched by email or phone, or null. */
    public ?int $duplicateOf = null;

    public function __construct(
        protected int $userId,
    ) {}

    public function validate(array $input): self
    {
        $this->errors = [];
        $this->normalized = [];
        $this->duplicateOf = null;

        $out = [];
        $out['display_name'] = $this->trimOrNull($input['display_name'] ?? null, 191);
        $out['given_name']   = $this->trimOrNull($input['given_name']   ?? null, 191);
        $out['family_name']  = $this->trimOrNull($input['family_name']  ?? null, 191);
        $out['organization'] = $this->trimOrNull($input['organization'] ?? null, 191);
        $out['job_title']    = $this->trimOrNull($input['job_title']    ?? null, 191);
        $out['notes']        = $this->trimOrNull($input['notes']        ?? null, 2000);

        $out['website'] = $this->canonicalizeUrl($input['website'] ?? null, 'website');

        $out['emails'] = $this->validateEmails($input['emails'] ?? []);
        $out['phones'] = $this->validatePhones($input['phones'] ?? []);
        $out['socials'] = $this->validateSocials($input['socials'] ?? []);

        $tags = [];
        foreach ((array) ($input['tags'] ?? []) as $t) {
            $t = trim((string) $t);
            if ($t === '') continue;
            if (mb_strlen($t) > 40) {
                $this->errors['tags'][] = "Tag '{$t}' is too long.";
                continue;
            }
            if (!in_array($t, $tags, true)) $tags[] = $t;
        }
        $out['tags'] = $tags;

        $sourceUrl = $this->canonicalizeUrl($input['source_url'] ?? null, 'source_url');
        $out['source_url'] = $sourceUrl;

        // Brand/Personal classification — only emit the key when the client
        // sent a valid value, so partial updates never clobber an existing
        // classification with null.
        $ct = strtolower(trim((string) ($input['contact_type'] ?? '')));
        if (in_array($ct, ['personal', 'brand'], true)) {
            $out['contact_type'] = $ct;
        }

        // Require at least one of name / email / phone — otherwise it's noise.
        $hasName  = $out['display_name'] || $out['given_name'] || $out['family_name'] || $out['organization'];
        $hasEmail = !empty($out['emails']);
        $hasPhone = !empty($out['phones']);
        if (!$hasName && !$hasEmail && !$hasPhone) {
            $this->errors['_form'][] = 'Need at least a name, email, or phone.';
        }

        if (!$out['display_name']) {
            $name = trim(($out['given_name'] ?? '') . ' ' . ($out['family_name'] ?? ''));
            if ($name === '') $name = $out['organization'] ?? '';
            $out['display_name'] = $name !== '' ? $name : null;
        }

        // Dedupe lookup (only if we have something to match on).
        $this->duplicateOf = $this->findDuplicate($out['emails'], $out['phones']);

        $this->normalized = $out;
        return $this;
    }

    public function ok(): bool
    {
        return empty($this->errors);
    }

    public function toArray(): array
    {
        return [
            'ok'           => $this->ok(),
            'errors'       => (object) $this->errors,
            'normalized'   => $this->normalized,
            'duplicate_of' => $this->duplicateOf,
        ];
    }

    // ─── Field validators ───────────────────────────────────────────

    protected function validateEmails(mixed $emails): array
    {
        if (!is_array($emails)) return [];
        $out = [];
        $seen = [];
        foreach (array_values($emails) as $i => $row) {
            $row = is_array($row) ? $row : ['value' => (string) $row];
            $val = trim((string) ($row['value'] ?? ''));
            if ($val === '') continue;
            $norm = strtolower($val);
            if (mb_strlen($norm) > 191) {
                $this->errors["emails.$i.value"][] = 'Email is too long.';
                continue;
            }
            if (!filter_var($norm, FILTER_VALIDATE_EMAIL)) {
                $this->errors["emails.$i.value"][] = "'$val' is not a valid email address.";
                continue;
            }
            $domain = substr(strrchr($norm, '@') ?: '', 1);
            if ($domain && !$this->domainHasMx($domain)) {
                $this->errors["emails.$i.value"][] = "Could not find a mail server for '$domain'.";
                continue;
            }
            if (isset($seen[$norm])) continue;
            $seen[$norm] = true;
            $out[] = [
                'value'      => $norm,
                'label'      => $this->trimOrNull($row['label'] ?? null, 50),
                'is_primary' => (bool) ($row['is_primary'] ?? (count($out) === 0)),
                'source'     => $this->trimOrNull($row['source'] ?? null, 30),
            ];
        }
        return $out;
    }

    protected function validatePhones(mixed $phones): array
    {
        if (!is_array($phones)) return [];
        $out = [];
        $seen = [];
        foreach (array_values($phones) as $i => $row) {
            $row = is_array($row) ? $row : ['value' => (string) $row];
            $val = trim((string) ($row['value'] ?? ''));
            if ($val === '') continue;

            $defaultCountry = strtoupper(trim((string) ($row['country'] ?? '')));
            $e164 = $this->toE164($val, $defaultCountry !== '' ? $defaultCountry : null);
            if (!$e164) {
                $this->errors["phones.$i.value"][] = "'$val' is not a valid phone number.";
                continue;
            }
            if (mb_strlen($e164) > 32) {
                $this->errors["phones.$i.value"][] = 'Phone number is too long.';
                continue;
            }
            if (isset($seen[$e164])) continue;
            $seen[$e164] = true;
            $out[] = [
                'value'      => $val,
                'value_e164' => $e164,
                'label'      => $this->trimOrNull($row['label'] ?? null, 50),
                'is_primary' => (bool) ($row['is_primary'] ?? (count($out) === 0)),
                'source'     => $this->trimOrNull($row['source'] ?? null, 30),
            ];
        }
        return $out;
    }

    protected function validateSocials(mixed $socials): array
    {
        if (!is_array($socials)) return [];
        $out = [];
        foreach ($socials as $key => $row) {
            // Accept either {platform, url} array or {twitter: "@x", ...} map.
            if (is_array($row) && isset($row['platform'])) {
                $platform = strtolower(trim((string) $row['platform']));
                $value    = trim((string) ($row['value'] ?? $row['url'] ?? ''));
            } else {
                $platform = strtolower(trim((string) $key));
                $value    = trim((string) $row);
            }
            if ($platform === '' || $value === '') continue;
            if (mb_strlen($platform) > 30 || mb_strlen($value) > 191) continue;
            $out[$platform] = $value;
        }
        return $out;
    }

    protected function canonicalizeUrl(mixed $raw, string $field): ?string
    {
        $val = trim((string) ($raw ?? ''));
        if ($val === '') return null;
        if (!preg_match('~^https?://~i', $val)) $val = 'https://' . $val;
        $parts = parse_url($val);
        if (!$parts || empty($parts['host'])) {
            $this->errors[$field][] = "'$raw' is not a valid URL.";
            return null;
        }
        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host   = strtolower($parts['host']);
        $port   = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path   = $parts['path'] ?? '';
        $query  = isset($parts['query']) ? '?' . $parts['query'] : '';
        return "$scheme://$host$port$path$query";
    }

    /**
     * Cached MX/A lookup. Returns true on lookup failure (we don't want
     * a network blip to block a save) but caches the negative result for
     * a short window so repeat clicks don't keep hammering DNS.
     */
    protected function domainHasMx(string $domain): bool
    {
        $domain = strtolower(rtrim($domain, '.'));
        if ($domain === '' || str_contains($domain, ' ')) return false;
        return Cache::remember("mx:$domain", 3600, function () use ($domain) {
            // function_exists check — some hardened PHP builds drop these.
            if (!function_exists('checkdnsrr')) return true;
            try {
                if (@checkdnsrr($domain, 'MX')) return true;
                if (@checkdnsrr($domain, 'A')) return true;
                return false;
            } catch (\Throwable) {
                return true;
            }
        });
    }

    /**
     * Best-effort E.164 normalizer. Keeps a `+CC...` style intact, accepts
     * `00CC...` as international, and otherwise requires a country hint
     * (`country` field on the candidate) to know how to expand a national
     * number. Returns null when the result isn't a plausible 8-15 digit
     * E.164.
     */
    protected function toE164(string $raw, ?string $country): ?string
    {
        $cleaned = preg_replace('/[\s\-\(\)\.\x{00A0}]+/u', '', $raw);
        if ($cleaned === null || $cleaned === '') return null;

        if (str_starts_with($cleaned, '+')) {
            $digits = preg_replace('/\D+/', '', substr($cleaned, 1));
            return $this->validE164($digits);
        }
        if (str_starts_with($cleaned, '00')) {
            $digits = preg_replace('/\D+/', '', substr($cleaned, 2));
            return $this->validE164($digits);
        }
        $digits = preg_replace('/\D+/', '', $cleaned);
        if ($digits === '') return null;

        $cc = $country ? self::COUNTRY_CALLING_CODES[$country] ?? null : null;
        if (!$cc) return null;

        // Strip a leading trunk '0' for countries that use one.
        if (str_starts_with($digits, '0')) $digits = ltrim($digits, '0');

        return $this->validE164($cc . $digits);
    }

    protected function validE164(string $digits): ?string
    {
        if ($digits === '' || !ctype_digit($digits)) return null;
        $len = strlen($digits);
        if ($len < 8 || $len > 15) return null;
        return '+' . $digits;
    }

    protected function findDuplicate(array $emails, array $phones): ?int
    {
        if (empty($emails) && empty($phones)) return null;

        if (!empty($emails)) {
            $vals = array_map(fn ($e) => $e['value'], $emails);
            $hit = ContactEmail::whereIn('value', $vals)
                ->whereHas('contact', fn ($q) => $q->where('user_id', $this->userId))
                ->orderBy('contact_id')
                ->value('contact_id');
            if ($hit) return (int) $hit;
        }

        if (!empty($phones)) {
            $vals = array_filter(array_map(fn ($p) => $p['value_e164'] ?? null, $phones));
            if (!empty($vals)) {
                $hit = ContactPhone::whereIn('value_e164', $vals)
                    ->whereHas('contact', fn ($q) => $q->where('user_id', $this->userId))
                    ->orderBy('contact_id')
                    ->value('contact_id');
                if ($hit) return (int) $hit;
            }
        }
        return null;
    }

    protected function trimOrNull(mixed $v, int $max): ?string
    {
        $s = trim((string) ($v ?? ''));
        if ($s === '') return null;
        if (mb_strlen($s) > $max) $s = mb_substr($s, 0, $max);
        return $s;
    }

    /**
     * ITU country → calling-code map. Covers the common ones we see in
     * extension extractions; falls back to "country missing" otherwise.
     * Listed sparsely on purpose — we'd rather refuse to normalize than
     * guess wrong.
     */
    protected const COUNTRY_CALLING_CODES = [
        'US' => '1',  'CA' => '1',  'GB' => '44', 'IE' => '353', 'AU' => '61', 'NZ' => '64',
        'DE' => '49', 'FR' => '33', 'ES' => '34', 'IT' => '39', 'PT' => '351', 'NL' => '31',
        'BE' => '32', 'CH' => '41', 'AT' => '43', 'SE' => '46', 'NO' => '47', 'FI' => '358',
        'DK' => '45', 'PL' => '48', 'CZ' => '420','SK' => '421','HU' => '36', 'RO' => '40',
        'GR' => '30', 'BG' => '359','HR' => '385','SI' => '386','RS' => '381','UA' => '380',
        'RU' => '7',  'TR' => '90', 'IL' => '972','AE' => '971','SA' => '966','EG' => '20',
        'ZA' => '27', 'NG' => '234','KE' => '254','GH' => '233','MA' => '212',
        'IN' => '91', 'PK' => '92', 'BD' => '880','LK' => '94', 'SG' => '65', 'MY' => '60',
        'ID' => '62', 'PH' => '63', 'VN' => '84', 'TH' => '66', 'JP' => '81', 'KR' => '82',
        'CN' => '86', 'HK' => '852','TW' => '886','MX' => '52', 'BR' => '55', 'AR' => '54',
        'CL' => '56', 'CO' => '57', 'PE' => '51', 'VE' => '58',
    ];
}
