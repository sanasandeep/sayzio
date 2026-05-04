<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Services\Uploads\UploadScanner;
use Illuminate\Http\Request;

/**
 * Platform-wide review queue for files the upload scanner has flagged
 * (virus signatures + phishing heuristics) on inbox attachments and
 * form submissions. Lets the trust & safety team triage what the
 * automated pipeline quarantined and either acknowledge it or re-scan.
 */
class FileScanQueueController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'flagged');
        if (!in_array($status, ['flagged', 'pending', 'all'], true)) {
            $status = 'flagged';
        }
        $reviewed = $request->query('reviewed', 'pending');
        if (!in_array($reviewed, ['pending', 'reviewed', 'all'], true)) {
            $reviewed = 'pending';
        }

        $query = UserFile::query()->withoutGlobalScope('workspace')->with('user:id,name,email');
        if ($status !== 'all') $query->where('scan_status', $status);
        if ($reviewed === 'pending') $query->where('scan_admin_reviewed', false);
        if ($reviewed === 'reviewed') $query->where('scan_admin_reviewed', true);

        $files = $query->orderByDesc('quarantined_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $counts = [
            'flagged_pending' => UserFile::query()->withoutGlobalScope('workspace')
                ->where('scan_status', 'flagged')
                ->where('scan_admin_reviewed', false)
                ->count(),
            'flagged_total'   => UserFile::query()->withoutGlobalScope('workspace')
                ->where('scan_status', 'flagged')->count(),
            'pending'         => UserFile::query()->withoutGlobalScope('workspace')
                ->where('scan_status', 'pending')->count(),
        ];

        return view('admin.file-scan-queue.index', [
            'files'    => $files,
            'status'   => $status,
            'reviewed' => $reviewed,
            'counts'   => $counts,
            'reasonLabel' => fn(?string $r) => UploadScanner::reasonLabel($r),
        ]);
    }

    /** Mark an item as reviewed (no file mutation). */
    public function acknowledge(Request $request, int $file)
    {
        $userFile = UserFile::query()->withoutGlobalScope('workspace')->findOrFail($file);
        $userFile->forceFill(['scan_admin_reviewed' => true])->save();
        return back()->with('success', 'Marked as reviewed.');
    }

    /** Re-run the scanner against the stored bytes. */
    public function rescan(Request $request, int $file)
    {
        $userFile = UserFile::query()->withoutGlobalScope('workspace')->findOrFail($file);
        $result = app(UploadScanner::class)->scan($userFile);
        return back()->with(
            'success',
            'Re-scanned: ' . UploadScanner::reasonLabel($result['reason']) . ' (' . $result['status'] . ').'
        );
    }
}
