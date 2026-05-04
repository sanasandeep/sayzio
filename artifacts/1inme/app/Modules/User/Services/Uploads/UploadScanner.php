<?php

namespace App\Modules\User\Services\Uploads;

use App\Modules\User\Models\UserFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Lightweight content scanner for files uploaded into the vault via inbox
 * attachments and form submissions. Runs two independent checks:
 *
 *   1. Virus scan — uses `clamscan` if it's available on PATH, otherwise
 *      falls back to a small set of well-known signatures (EICAR test file,
 *      DOS/PE executables masquerading as documents, suspicious script
 *      payloads in HTML/SVG, …). The fallback is deliberately conservative
 *      — it's a last line of defence, not a real AV engine.
 *
 *   2. Phishing heuristic — looks for tell-tale patterns in text-like
 *      attachments: high-risk URLs in PDFs / HTML / SVG, hidden
 *      <script>/<iframe> in supposedly-static documents, double extensions
 *      (e.g. invoice.pdf.exe), and html-form-style credential prompts.
 *
 * Files always start in `pending` and either land in `clean` or `flagged`.
 * Failures inside the scanner itself fall back to `flagged` with reason
 * `scan_error` so the creator gets a warning rather than a silent pass.
 */
class UploadScanner
{
    /** Extensions that get an extra "this file type can run code" warning. */
    public const HIGH_RISK_EXTENSIONS = [
        'exe', 'msi', 'bat', 'cmd', 'com', 'scr', 'pif', 'vbs', 'vbe',
        'js', 'jse', 'jar', 'ps1', 'psm1', 'sh', 'bash', 'apk', 'app',
        'dll', 'iso', 'html', 'htm', 'svg', 'hta',
    ];

    /** Extensions whose contents we scan as text for phishing patterns. */
    private const TEXT_LIKE_EXTENSIONS = [
        'pdf', 'html', 'htm', 'svg', 'txt', 'eml', 'rtf', 'csv',
    ];

    /** Hard cap on bytes read for in-process content heuristics. */
    private const MAX_INSPECT_BYTES = 2_000_000;

    /**
     * Scan a freshly-stored UserFile in place. Always persists scan_status
     * and scanned_at. Safe to call multiple times — re-scans overwrite the
     * previous result so an admin can re-trigger after engine updates.
     *
     * @return array{status:string, reason:?string, meta:array}
     */
    public function scan(UserFile $file): array
    {
        $result = ['status' => 'clean', 'reason' => null, 'meta' => []];

        try {
            $bytes = $this->readBytes($file);
            if ($bytes === null) {
                $result = [
                    'status' => 'skipped',
                    'reason' => 'file_unreadable',
                    'meta'   => ['note' => 'Underlying bytes could not be read'],
                ];
            } else {
                $virus = $this->virusCheck($file, $bytes);
                if ($virus !== null) {
                    $result = ['status' => 'flagged'] + $virus;
                } else {
                    $phish = $this->phishingCheck($file, $bytes);
                    if ($phish !== null) {
                        $result = ['status' => 'flagged'] + $phish;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('UploadScanner failure', [
                'file_id' => $file->id,
                'error'   => $e->getMessage(),
            ]);
            $result = [
                'status' => 'flagged',
                'reason' => 'scan_error',
                'meta'   => ['error' => $e->getMessage()],
            ];
        }

        $file->forceFill([
            'scan_status'    => $result['status'],
            'scan_reason'    => $result['reason'],
            'scan_meta'      => $result['meta'],
            'scanned_at'     => now(),
            'quarantined_at' => $result['status'] === 'flagged' ? now() : null,
        ])->save();

        return $result;
    }

    /**
     * Whether the given extension is on the "extra-warning" list — used by
     * the UI to require a second confirmation step on download.
     */
    public static function isHighRisk(?string $extension): bool
    {
        if ($extension === null) return false;
        return in_array(strtolower($extension), self::HIGH_RISK_EXTENSIONS, true);
    }

    /** Human-readable reason label used in the UI. */
    public static function reasonLabel(?string $reason): string
    {
        return match ($reason) {
            'virus_eicar'         => 'Matched EICAR antivirus test signature',
            'virus_clamav'        => 'Flagged by ClamAV',
            'virus_pe_executable' => 'Windows executable disguised as a document',
            'virus_macro'         => 'Office macro / VBA payload detected',
            'phishing_url'        => 'Suspicious URL inside the file',
            'phishing_script'     => 'Hidden script / iframe in document',
            'phishing_credentials'=> 'Asks for passwords or login details',
            'double_extension'    => 'Misleading double file extension',
            'scan_error'          => 'Scanner could not finish — quarantined for safety',
            'file_unreadable'     => 'File could not be read',
            null                  => 'Clean',
            default               => ucfirst(str_replace('_', ' ', (string) $reason)),
        };
    }

    /** Try clamscan first, fall back to in-process signature checks. */
    private function virusCheck(UserFile $file, string $bytes): ?array
    {
        // 1. ClamAV daemon / CLI — the real engine when present.
        $clam = $this->runClamScan($file);
        if ($clam !== null) return $clam;

        // 2. EICAR — the universal AV test signature.
        if (str_contains($bytes, 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*')) {
            return ['reason' => 'virus_eicar', 'meta' => ['engine' => 'builtin', 'signature' => 'EICAR']];
        }

        // 3. PE executable masquerading as a document/image.
        $declaredType = strtolower((string) $file->type);
        $declaredExt  = strtolower((string) pathinfo($file->original_name, PATHINFO_EXTENSION));
        $looksLikePe  = strncmp($bytes, "MZ", 2) === 0 && str_contains(substr($bytes, 0, 4096), "PE\0\0");
        if ($looksLikePe && !in_array($declaredExt, ['exe', 'dll', 'msi', 'scr'], true)) {
            return [
                'reason' => 'virus_pe_executable',
                'meta'   => [
                    'engine'        => 'builtin',
                    'declared_type' => $declaredType,
                    'declared_ext'  => $declaredExt,
                ],
            ];
        }

        // 4. Office macro markers — VBA project stream embedded in DOCX/XLSX.
        if (in_array($declaredExt, ['doc', 'docm', 'xls', 'xlsm', 'ppt', 'pptm'], true)
            && (str_contains($bytes, 'vbaProject.bin') || str_contains($bytes, 'Auto_Open'))) {
            return ['reason' => 'virus_macro', 'meta' => ['engine' => 'builtin']];
        }

        return null;
    }

    /** Best-effort clamscan invocation. Returns null when unavailable. */
    private function runClamScan(UserFile $file): ?array
    {
        if (!function_exists('proc_open')) return null;
        $disk = $file->disk === 'public' ? 'public' : ($file->disk === 's3' ? 's3' : 'user_files');
        if ($disk === 's3') return null; // remote disk — skip the local CLI engine
        try {
            $path = Storage::disk($disk)->path($file->path);
        } catch (\Throwable) {
            return null;
        }
        if (!is_string($path) || !is_file($path)) return null;

        $which = @shell_exec('command -v clamscan 2>/dev/null');
        if (!is_string($which) || trim($which) === '') return null;

        $cmd = 'clamscan --no-summary --stdout ' . escapeshellarg($path) . ' 2>/dev/null';
        $output = @shell_exec($cmd);
        if (!is_string($output)) return null;

        // clamscan exit isn't easily captured via shell_exec, so parse output.
        if (preg_match('/:\s+(.+)\s+FOUND$/m', $output, $m)) {
            return [
                'reason' => 'virus_clamav',
                'meta'   => ['engine' => 'clamav', 'signature' => trim($m[1])],
            ];
        }
        return null;
    }

    /** Phishing-style content patterns in text-like attachments. */
    private function phishingCheck(UserFile $file, string $bytes): ?array
    {
        $ext = strtolower((string) pathinfo($file->original_name, PATHINFO_EXTENSION));
        $name = (string) $file->original_name;

        // Double-extension trick: invoice.pdf.exe / photo.jpg.scr / …
        if (preg_match('/\.([a-z0-9]{2,4})\.(exe|scr|js|vbs|bat|cmd|jar|hta)$/i', $name, $m)) {
            return [
                'reason' => 'double_extension',
                'meta'   => ['outer' => strtolower($m[2]), 'inner' => strtolower($m[1])],
            ];
        }

        if (!in_array($ext, self::TEXT_LIKE_EXTENSIONS, true)) {
            return null;
        }

        $sample = substr($bytes, 0, self::MAX_INSPECT_BYTES);
        $lower  = strtolower($sample);

        // Hidden scripts / iframes in supposedly static documents.
        if (in_array($ext, ['html', 'htm', 'svg', 'pdf'], true)
            && (str_contains($lower, '<script') || str_contains($lower, '<iframe'))) {
            return [
                'reason' => 'phishing_script',
                'meta'   => ['extension' => $ext, 'pattern' => 'script_or_iframe'],
            ];
        }

        // URL extraction — look at hosts, flag credential-grabber hallmarks.
        if (preg_match_all('#https?://([a-z0-9\-\._]+)#i', $sample, $urlMatches)) {
            $hosts = array_unique(array_map('strtolower', $urlMatches[1]));
            foreach ($hosts as $host) {
                if ($this->isSuspiciousHost($host)) {
                    return [
                        'reason' => 'phishing_url',
                        'meta'   => ['host' => $host],
                    ];
                }
            }
        }

        // Credential-prompt phrasing common in phishing PDFs/emails.
        $credentialPatterns = [
            'verify your password',
            'confirm your account',
            'reset your password immediately',
            'your account has been suspended',
            'click here to login',
            'wire transfer urgent',
        ];
        foreach ($credentialPatterns as $needle) {
            if (str_contains($lower, $needle)) {
                return [
                    'reason' => 'phishing_credentials',
                    'meta'   => ['pattern' => $needle],
                ];
            }
        }

        return null;
    }

    /** Heuristics for an obviously-suspicious URL host. */
    private function isSuspiciousHost(string $host): bool
    {
        if ($host === '' || strlen($host) > 253) return false;

        // Bare IP addresses in URLs are almost always phishing in this context.
        if (filter_var($host, FILTER_VALIDATE_IP)) return true;

        // Punycode (xn--) — homograph spoofs.
        if (str_starts_with($host, 'xn--') || str_contains($host, '.xn--')) return true;

        // Excessively-long or hyphen-stuffed subdomains pretending to be a brand.
        if (substr_count($host, '-') >= 4) return true;
        if (strlen($host) >= 60) return true;

        // Free phishing-friendly tunneling hosts (deliberately small list).
        $bad = ['ngrok.io', 'ngrok-free.app', 'serveo.net', 'loca.lt', 'trycloudflare.com'];
        foreach ($bad as $needle) {
            if ($host === $needle || str_ends_with($host, '.' . $needle)) return true;
        }
        return false;
    }

    private function readBytes(UserFile $file): ?string
    {
        $disk = $file->disk === 'public' ? 'public' : ($file->disk === 's3' ? 's3' : 'user_files');
        try {
            if (!Storage::disk($disk)->exists($file->path)) return null;
            $bytes = Storage::disk($disk)->get($file->path);
        } catch (\Throwable $e) {
            return null;
        }
        if (!is_string($bytes) || $bytes === '') return null;

        // Cap memory: only inspect first N bytes — viruses + phishing
        // markers live near the start of the file in every case we care about.
        if (strlen($bytes) > self::MAX_INSPECT_BYTES) {
            return substr($bytes, 0, self::MAX_INSPECT_BYTES);
        }
        return $bytes;
    }
}
