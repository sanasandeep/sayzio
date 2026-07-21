<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Concerns\RespondsWithUploadErrors;
use App\Modules\User\Models\UserFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserFileController extends Controller
{
    use RespondsWithUploadErrors;

    public function index(Request $request)
    {
        $user = $request->user();
        $type = $request->get('type', 'all');

        // System-generated files (Brand Kit assets etc.) live outside the
        // vault UI and the max_files count; they still count toward storage.
        $query = $user->files()->whereNull('context')->orderByDesc('created_at');
        if ($type !== 'all' && in_array($type, ['image', 'video', 'audio', 'document'])) {
            $query->where('type', $type);
        }

        $files = $query->paginate(24);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'files' => $files->items(),
                'pagination' => [
                    'current_page' => $files->currentPage(),
                    'last_page' => $files->lastPage(),
                    'total' => $files->total(),
                ],
                'quota' => $this->getQuotaInfo($user),
            ]);
        }

        return view('user.files.index', [
            'quota' => $this->getQuotaInfo($user),
            'reoptimizeNotice' => $this->getReoptimizeNotice($user),
        ]);
    }

    /**
     * Dismiss the one-time "we shrunk old images and recovered X" banner.
     * Stamps a timestamp on the user row; subsequent backfill runs that
     * record more savings clear it again so creators see the new totals.
     */
    public function dismissReoptimizeNotice(Request $request)
    {
        $user = $request->user();
        $user->forceFill(['image_reoptimize_notice_dismissed_at' => now()])->save();

        return response()->json(['success' => true]);
    }

    /**
     * Build the one-time vault-cleanup banner payload, or null when there
     * is nothing to show (no savings recorded yet, or already dismissed
     * since the most recent backfill run).
     */
    private function getReoptimizeNotice($user): ?array
    {
        $count = (int) ($user->image_reoptimize_files_count ?? 0);
        $bytes = (int) ($user->image_reoptimize_bytes_freed ?? 0);
        if ($count <= 0 || $bytes <= 0) return null;
        if (!empty($user->image_reoptimize_notice_dismissed_at)) return null;

        $mb = $bytes / 1048576;
        if ($mb >= 1) {
            $human = number_format($mb, $mb >= 10 ? 0 : 1) . ' MB';
        } else {
            $human = max(1, (int) round($bytes / 1024)) . ' KB';
        }

        return [
            'files_count' => $count,
            'bytes_freed' => $bytes,
            'bytes_human' => $human,
        ];
    }

    public function upload(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'file' => 'required|file',
        ]);

        try {
            $userFile = UserFile::createFromUpload($request->file('file'), $user);
        } catch (\RuntimeException $e) {
            return $this->uploadError($request, $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'file' => $userFile,
            'quota' => $this->getQuotaInfo($user),
        ]);
    }

    public function serve(Request $request, $id, $filename)
    {
        $file = UserFile::findOrFail($id);

        if ($file->filename !== $filename) {
            abort(404);
        }

        $user = $request->user();
        $ownerId = (int) $file->user_id;

        $authorized = false;

        if ($user && (int) $user->id === $ownerId) {
            $authorized = true;
        }

        if (!$authorized && $user) {
            $owner = \App\Modules\User\Models\User::find($ownerId);
            if ($owner && method_exists($owner, 'teams') && method_exists($user, 'teams')) {
                $ownerTeamIds = $owner->teams()->pluck('teams.id')->toArray();
                $userTeamIds = $user->teams()->pluck('teams.id')->toArray();
                if (!empty(array_intersect($ownerTeamIds, $userTeamIds))) {
                    $authorized = true;
                }
            }
        }

        if (!$authorized) {
            $authorized = $this->isReferencedByPublicRecord($file);
        }

        if (!$authorized) {
            abort(403, 'Access denied.');
        }

        // Scan gate — files coming through the inbox / form-submission
        // pipeline must clear virus + phishing checks before we serve
        // them. Owners can still pull a flagged file with explicit
        // ?confirm=1 (the inbox UI surfaces a warning + form); everyone
        // else gets a hard 403.
        if ($file->isPendingScan()) {
            return response()->view('user.files.scan-pending', [
                'file' => $file,
            ], 423);
        }
        if ($file->isFlagged()) {
            $isOwner = $user && (int) $user->id === $ownerId;
            if (!$isOwner || $request->query('confirm') !== '1') {
                return response()->view('user.files.scan-flagged', [
                    'file'    => $file,
                    'isOwner' => $isOwner,
                ], 451);
            }
        }

        // Resolve the actual disk this file lives on, then decide how to serve
        // it by the disk's *driver* (not its name): S3-backed disks have no
        // local ->path(), so we redirect to a short-lived signed URL instead.
        $storageDisk = $file->disk === 'public' ? 'public' : ($file->disk === 's3' ? 's3' : 'user_files');
        if (config("filesystems.disks.{$storageDisk}.driver") === 's3') {
            return redirect(Storage::disk($storageDisk)->temporaryUrl($file->path, now()->addMinutes(30)));
        }

        $fullPath = Storage::disk($storageDisk)->path($file->path);

        if (!file_exists($fullPath)) {
            abort(404, 'File not found.');
        }

        $headers = [
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => 'inline; filename="' . addslashes($file->original_name) . '"',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ];

        return response()->file($fullPath, $headers);
    }

    public function destroy(Request $request, UserFile $file)
    {
        $user = $request->user();
        if ((int) $file->user_id !== (int) $user->id) {
            return response()->json(['success' => false, 'error' => 'Unauthorized.'], 403);
        }

        $file->deleteFile();

        return response()->json([
            'success' => true,
            'quota' => $this->getQuotaInfo($user->fresh()),
        ]);
    }

    public function quota(Request $request)
    {
        return response()->json([
            'success' => true,
            'quota' => $this->getQuotaInfo($request->user()),
        ]);
    }

    /**
     * Download a remote URL into the user's vault. Used by the dropzone
     * partial when the user picks the "URL" mode so an arbitrary remote
     * asset becomes a first-class vault file (subject to quota / mime rules).
     */
    public function importUrl(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'url' => 'required|url|max:2048',
        ]);

        $url = $request->input('url');
        $parsed = parse_url($url);
        if (!$parsed || !in_array(strtolower($parsed['scheme'] ?? ''), ['http', 'https'], true)) {
            return $this->uploadError($request, 'Only http(s) URLs are supported.');
        }

        // SSRF guard: resolve the host ONCE, validate every resolved IP is
        // public, then pin curl to those IPs via CURLOPT_RESOLVE to defeat
        // DNS rebinding. Redirects are disallowed so an attacker cannot
        // bounce us to a fresh hostname that re-resolves to a private IP.
        $host = strtolower($parsed['host'] ?? '');
        $port = (int) ($parsed['port'] ?? (strtolower($parsed['scheme']) === 'https' ? 443 : 80));
        $isOwnHost = $host === strtolower($request->getHost());

        $resolveOpt = null;
        if (!$isOwnHost) {
            $ips = [];
            $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
            foreach ($records as $rec) {
                if (!empty($rec['ip'])) $ips[] = $rec['ip'];
                if (!empty($rec['ipv6'])) $ips[] = $rec['ipv6'];
            }
            if (!$ips && filter_var($host, FILTER_VALIDATE_IP)) $ips[] = $host;
            if (!$ips) {
                $resolved = @gethostbyname($host);
                if ($resolved && $resolved !== $host) $ips[] = $resolved;
            }
            if (!$ips) {
                return $this->uploadError($request, 'Could not resolve host.');
            }
            foreach ($ips as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                    return $this->uploadError($request, 'URL points to a disallowed network.');
                }
            }
            // Pin curl to these IPs so a later DNS swap cannot redirect us
            // to a private address mid-request.
            $resolveOpt = $host . ':' . $port . ':' . implode(',', $ips);
        }

        $maxMb = (int) (method_exists($user, 'getPlanFeature') ? $user->getPlanFeature('max_file_size_mb', 5) : 5);
        $maxBytes = $maxMb * 1024 * 1024;

        try {
            $ch = curl_init($url);
            $opts = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_USERAGENT      => 'Sayzio-VaultImporter/1.0',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_BUFFERSIZE     => 65536,
                CURLOPT_NOPROGRESS     => false,
                CURLOPT_PROGRESSFUNCTION => function ($ch, $dlTotal, $dlNow) use ($maxBytes) {
                    return ($maxBytes > 0 && $dlNow > $maxBytes) ? 1 : 0;
                },
            ];
            if ($resolveOpt !== null) {
                $opts[CURLOPT_RESOLVE] = [$resolveOpt];
            }
            curl_setopt_array($ch, $opts);
            $bytes = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            $err = curl_error($ch);
            curl_close($ch);

            if ($bytes === false || $status >= 400) {
                return $this->uploadError($request, 'Could not fetch URL' . ($err ? ': ' . $err : '') . ($status ? " (HTTP {$status})" : ''));
            }
            if ($maxBytes > 0 && strlen($bytes) > $maxBytes) {
                return $this->uploadError($request, "File exceeds maximum size of {$maxMb}MB.");
            }

            $mime = strtolower(trim(explode(';', $contentType)[0] ?? '')) ?: 'application/octet-stream';
            $name = basename(parse_url($finalUrl ?: $url, PHP_URL_PATH) ?: 'download');
            if ($name === '' || $name === '/') $name = 'download';
            // Ensure name has a sensible extension based on mime when missing.
            if (!pathinfo($name, PATHINFO_EXTENSION)) {
                $extMap = [
                    'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif',
                    'image/webp' => 'webp', 'image/svg+xml' => 'svg',
                    'video/mp4' => 'mp4', 'video/webm' => 'webm',
                    'audio/mpeg' => 'mp3', 'audio/wav' => 'wav',
                    'application/pdf' => 'pdf',
                ];
                if (isset($extMap[$mime])) $name .= '.' . $extMap[$mime];
            }

            // Enforce the same mime/extension allowlist as direct uploads.
            // Skip for holders of `user.files.access_any` (matches the
            // createFromUpload bypass behaviour).
            $skipAllowlist = method_exists($user, 'hasPermission') && $user->hasPermission('user.files.access_any');
            if (!$skipAllowlist) {
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION) ?: '');
                if (!in_array($mime, UserFile::getAllAllowedMimes(), true)) {
                    return $this->uploadError($request, 'File type not allowed.');
                }
                if (!$ext || !in_array($ext, UserFile::getAllAllowedExtensions(), true)) {
                    return $this->uploadError($request, 'File extension not allowed.');
                }
            }

            $userFile = UserFile::createFromBytes($bytes, $name, $mime, $user);

            return response()->json([
                'success' => true,
                'file' => $userFile,
                'quota' => $this->getQuotaInfo($user->fresh()),
            ]);
        } catch (\RuntimeException $e) {
            return $this->uploadError($request, $e->getMessage());
        } catch (\Throwable $e) {
            return $this->uploadError($request, 'Import failed.', 500);
        }
    }

    /**
     * A vault file is publicly viewable when its URL is referenced from any
     * record that is itself rendered publicly: an active link, a splash page
     * attached to an active link, an approved verification request, an active
     * form (design assets) or a form submission stored on an active form.
     *
     * We match by the canonical /f/{id}/{filename} url_path so renames don't
     * accidentally widen the match.
     */
    private function isReferencedByPublicRecord(UserFile $file): bool
    {
        $needle = $file->url_path;
        $like   = '%' . $needle . '%';
        $db     = \Illuminate\Support\Facades\DB::class;

        // Biolink blocks on an active link — must be owned by the same user as the file
        if (\Illuminate\Support\Facades\DB::table('biolink_blocks')
            ->join('links', 'biolink_blocks.link_id', '=', 'links.id')
            ->where('links.is_active', true)
            ->where('links.user_id', $file->user_id)
            ->where('biolink_blocks.settings', 'like', $like)
            ->exists()) {
            return true;
        }

        // File Share — vault file referenced from file_links.stored_path on an active link
        if (\Illuminate\Support\Facades\DB::table('file_links')
            ->join('links', 'file_links.link_id', '=', 'links.id')
            ->where('links.is_active', true)
            ->where(function ($q) use ($file) {
                $q->where('file_links.stored_path', $file->path)
                  ->orWhere('file_links.stored_path', $file->url_path);
            })
            ->exists()) {
            return true;
        }

        // Link assets (seo_image, favicon, verified_logo) on an active link — must be owned by the same user as the file
        if (\Illuminate\Support\Facades\DB::table('links')
            ->where('is_active', true)
            ->where('user_id', $file->user_id)
            ->where(function ($q) use ($like) {
                $q->where('seo_image', 'like', $like)
                  ->orWhere('favicon', 'like', $like)
                  ->orWhere('verified_logo', 'like', $like);
            })
            ->exists()) {
            return true;
        }

        // Splash page assets attached to an active link — must be owned by the same user as the file
        if (\Illuminate\Support\Facades\Schema::hasTable('splash_pages')) {
            $exists = \Illuminate\Support\Facades\DB::table('splash_pages')
                ->join('links', 'links.splash_page_id', '=', 'splash_pages.id')
                ->where('links.is_active', true)
                ->where('links.user_id', $file->user_id)
                ->where(function ($q) use ($like) {
                    $q->where('splash_pages.logo', 'like', $like)
                      ->orWhere('splash_pages.favicon', 'like', $like)
                      ->orWhere('splash_pages.og_image', 'like', $like);
                })
                ->exists();
            if ($exists) return true;
        }

        // Approved verification requests — only logo_path is public-facing;
        // proof_files are private evidence documents meant for admin review only,
        // so they must never be served anonymously even after approval.
        if (\Illuminate\Support\Facades\Schema::hasTable('verification_requests')) {
            $exists = \Illuminate\Support\Facades\DB::table('verification_requests')
                ->where('status', 'approved')
                ->where('logo_path', 'like', $like)
                ->exists();
            if ($exists) return true;
        }

        // Active forms (design assets in JSON)
        if (\Illuminate\Support\Facades\Schema::hasTable('forms')) {
            $exists = \Illuminate\Support\Facades\DB::table('forms')
                ->where('is_active', true)
                ->where('design', 'like', $like)
                ->exists();
            if ($exists) return true;
        }

        // Submission attachments — only authorize anonymous serve when the
        // submission belongs to a form whose OWNER also owns the file. This
        // prevents a leaked vault URL from a different user being served just
        // because someone pasted it into a submission body somewhere.
        if (\Illuminate\Support\Facades\Schema::hasTable('form_submissions')
            && \Illuminate\Support\Facades\Schema::hasTable('forms')) {
            $exists = \Illuminate\Support\Facades\DB::table('form_submissions')
                ->join('forms', 'form_submissions.form_id', '=', 'forms.id')
                ->where('forms.user_id', $file->user_id)
                ->where('forms.is_active', true)
                ->where('form_submissions.data', 'like', $like)
                ->exists();
            if ($exists) return true;
        }

        // Event documents (Task #5023) — files attached to a public event page.
        if (\Illuminate\Support\Facades\Schema::hasTable('ics_data')) {
            $exists = \Illuminate\Support\Facades\DB::table('ics_data')
                ->join('links', 'ics_data.link_id', '=', 'links.id')
                ->where('links.is_active', true)
                ->whereRaw("ics_data.documents @> ?::jsonb", [json_encode([['file_id' => $file->id]])])
                ->exists();
            if ($exists) return true;
        }

        return false;
    }

    private function getQuotaInfo($user): array
    {
        $usedBytes = $user->getStorageUsedBytes();
        $limitBytes = $user->getStorageLimitBytes();
        $maxFileMb = (int) $user->getPlanFeature('max_file_size_mb', 5);

        return [
            'used_bytes' => $usedBytes,
            'limit_bytes' => $limitBytes,
            'used_mb' => round($usedBytes / 1048576, 1),
            'limit_mb' => round($limitBytes / 1048576),
            'percent' => $limitBytes > 0 ? round(($usedBytes / $limitBytes) * 100, 1) : 0,
            'max_file_size_mb' => $maxFileMb,
            'file_count' => $user->files()->whereNull('context')->count(),
        ];
    }
}
