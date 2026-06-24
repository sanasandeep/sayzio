<?php

namespace App\Jobs;

use App\Modules\Common\Models\PrivacyRequest;
use App\Modules\Common\Services\PrivacyRequestNotifier;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fulfil an approved data-export privacy request. Gathers a structured
 * JSON copy of every data domain the account owns plus the user's uploaded
 * files into a single .zip on a NON-public disk, then emails the requester
 * a secure, time-limited download link. Runs on the queue so a big vault
 * never blocks an HTTP request.
 */
class ProcessPrivacyExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries = 1;

    /**
     * Curated map of archive filename => [table, user-id column]. Each is
     * dumped as data/<file>.json. Wrapped in try/catch at run time so a
     * table that doesn't exist in a given environment is skipped, never
     * fatal.
     *
     * @var array<string, array{0:string,1:string}>
     */
    private const DOMAINS = [
        'linked_identifiers'       => ['linked_identifiers', 'user_id'],
        'links'                    => ['links', 'user_id'],
        'qr_codes'                 => ['qr_codes', 'user_id'],
        'forms'                    => ['forms', 'user_id'],
        'files'                    => ['user_files', 'user_id'],
        'contacts'                 => ['contacts', 'user_id'],
        'resumes'                  => ['resumes', 'user_id'],
        'subscribers'              => ['subscribers', 'user_id'],
        'reviews'                  => ['reviews', 'user_id'],
        'creator_posts'            => ['creator_posts', 'user_id'],
        'wallet_transactions'      => ['wallet_transactions', 'user_id'],
        'notifications'            => ['user_notifications', 'user_id'],
        'notification_preferences' => ['notification_preferences', 'user_id'],
        'following'                => ['follows', 'follower_id'],
        'followers'                => ['follows', 'creator_id'],
    ];

    public function __construct(public int $requestId) {}

    public function handle(PrivacyRequestNotifier $notifier): void
    {
        /** @var PrivacyRequest|null $pr */
        $pr = PrivacyRequest::find($this->requestId);
        if (!$pr || !$pr->isExport()) {
            return;
        }
        if (!in_array($pr->status, [PrivacyRequest::STATUS_APPROVED, PrivacyRequest::STATUS_PROCESSING], true)) {
            return;
        }

        $pr->forceFill(['status' => PrivacyRequest::STATUS_PROCESSING])->save();

        $user = $pr->user_id ? User::find($pr->user_id) : PrivacyRequest::matchUser($pr->email);
        if (!$user) {
            $pr->forceFill([
                'status'         => PrivacyRequest::STATUS_FAILED,
                'failure_reason' => 'No matching account found at fulfillment time.',
            ])->save();
            $pr->recordAudit('failed', 'system', 'No matching account found.');
            return;
        }

        try {
            $token = PrivacyRequest::newToken();
            $relPath = 'privacy-exports/' . $token . '.zip';
            $disk = Storage::disk('local');
            // Ensure the parent dir exists, then resolve the absolute path
            // ZipArchive needs to write to.
            $disk->makeDirectory('privacy-exports');
            $absPath = $disk->path($relPath);

            $this->buildArchive($absPath, $user);

            $pr->forceFill([
                'status'              => PrivacyRequest::STATUS_COMPLETED,
                'completed_at'        => now(),
                'download_token'      => $token,
                'archive_path'        => $relPath,
                'download_expires_at' => now()->addDays(PrivacyRequest::DOWNLOAD_TTL_DAYS),
            ])->save();
            $pr->recordAudit('completed', 'system', 'Data archive generated.');

            $url = route('privacy.download', ['token' => $token]);
            $notifier->notifyExportReady($pr, $url);
        } catch (\Throwable $e) {
            $pr->forceFill([
                'status'         => PrivacyRequest::STATUS_FAILED,
                'failure_reason' => Str::limit($e->getMessage(), 500),
            ])->save();
            $pr->recordAudit('failed', 'system', 'Export error: ' . Str::limit($e->getMessage(), 200));
            throw $e;
        }
    }

    private function buildArchive(string $absPath, User $user): void
    {
        if (is_file($absPath)) {
            @unlink($absPath);
        }

        $zip = new \ZipArchive();
        if ($zip->open($absPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create export archive.');
        }

        // Account profile (password + remember token are $hidden).
        $account = $user->toArray();
        $zip->addFromString('data/account.json', $this->json($account));

        $manifest = [
            'generated_at' => now()->toIso8601String(),
            'account_id'   => $user->id,
            'email'        => $user->email,
            'app'          => config('app.name'),
            'domains'      => [],
        ];

        foreach (self::DOMAINS as $file => [$table, $column]) {
            try {
                $rows = DB::table($table)->where($column, $user->id)->get();
                $manifest['domains'][$file] = $rows->count();
                $zip->addFromString('data/' . $file . '.json', $this->json($rows));
            } catch (\Throwable $e) {
                // Table missing in this environment — skip it silently.
                $manifest['domains'][$file] = 'unavailable';
            }
        }

        // Uploaded files: copy each stored object into files/<id>-<name>.
        $fileCount = 0;
        try {
            foreach (UserFile::where('user_id', $user->id)->cursor() as $f) {
                try {
                    $storageDisk = $f->disk === 'public'
                        ? 'public'
                        : ($f->disk === 's3' ? 's3' : 'user_files');
                    $d = Storage::disk($storageDisk);
                    if (!$f->path || !$d->exists($f->path)) {
                        continue;
                    }
                    $name = $f->name ?: basename($f->path);
                    $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);
                    $zip->addFromString('files/' . $f->id . '-' . $safe, $d->get($f->path));
                    $fileCount++;
                } catch (\Throwable $e) {
                    // One unreadable file shouldn't abort the whole export.
                }
            }
        } catch (\Throwable $e) {
            // user_files table missing — ignore.
        }
        $manifest['files_included'] = $fileCount;

        $zip->addFromString('manifest.json', $this->json($manifest));
        $zip->addFromString('README.txt', $this->readme($user));
        $zip->close();
    }

    private function json($data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    private function readme(User $user): string
    {
        return implode("\n", [
            config('app.name') . ' — personal data export',
            'Account: ' . $user->email . ' (id ' . $user->id . ')',
            'Generated: ' . now()->toDayDateTimeString() . ' UTC',
            '',
            'This archive contains a structured copy of your data:',
            '  data/account.json   — your account profile',
            '  data/*.json         — your records per data domain',
            '  files/              — your uploaded files',
            '  manifest.json       — a summary of what was included',
        ]);
    }

    public function failed(\Throwable $e): void
    {
        $pr = PrivacyRequest::find($this->requestId);
        if ($pr && $pr->status === PrivacyRequest::STATUS_PROCESSING) {
            $pr->forceFill([
                'status'         => PrivacyRequest::STATUS_FAILED,
                'failure_reason' => Str::limit($e->getMessage(), 500),
            ])->save();
        }
    }
}
