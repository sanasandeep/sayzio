<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AndroidApkRelease;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Public-facing Android APK download surface.
 *
 * GET /android          — landing page (version, size, download button)
 * GET /android/download — APK delivery endpoint
 *
 * Delivery strategy:
 *  - S3-backed disk: redirect to a short-lived signed URL so the client
 *    downloads directly from S3/CloudFront, picking up range/resume support
 *    for free from the CDN.
 *  - Local disk: stream with correct headers and honour Range requests so
 *    Android Chrome can resume a stalled download.
 */
class AndroidApkPublicController extends Controller
{
    public function show()
    {
        $release = AndroidApkRelease::live();

        return view('public.android-download', [
            'release' => $release,
            'seoKey'  => 'android-download',
        ]);
    }

    /**
     * Small public JSON descriptor of the current live APK release, consumed
     * by clients like the Zio Browser dialer pane to show version/size next
     * to the download QR. Returns 404 when no live release exists so callers
     * can hide the offer instead of pointing users at a dead download.
     */
    public function info()
    {
        $release = AndroidApkRelease::live();

        if (!$release) {
            return response()->json([
                'error' => ['message' => 'No APK is currently available.', 'code' => 'no_live_release'],
            ], 404);
        }

        return response()->json([
            'data' => [
                'version_name'    => $release->version_name,
                'build_number'    => $release->build_number,
                'file_size_bytes' => (int) $release->file_size_bytes,
                'size_human'      => $release->size_human,
                'published_at'    => optional($release->created_at)->toIso8601String(),
            ],
        ], 200, ['Cache-Control' => 'public, max-age=300']);
    }

    public function download(Request $request)
    {
        $release = AndroidApkRelease::live();

        if (!$release) {
            abort(404, 'No APK is currently available for download.');
        }

        // S3-backed disk: redirect to a signed URL (S3/CloudFront natively
        // handles Content-Disposition, Content-Length, and Range requests).
        if ($release->isS3()) {
            try {
                $url = Storage::disk($release->disk)->temporaryUrl(
                    $release->path,
                    now()->addMinutes(30),
                    [
                        'ResponseContentType'        => 'application/vnd.android.package-archive',
                        'ResponseContentDisposition' => 'attachment; filename="sayzio.apk"',
                    ]
                );
            } catch (\Throwable $e) {
                // Signing unavailable — fall back to a plain public URL and let
                // the client deal with it.
                $url = Storage::disk($release->disk)->url($release->path);
            }

            return redirect()->away($url);
        }

        // Local disk: stream with proper attachment headers + range support.
        $disk = Storage::disk($release->disk);

        if (!$disk->exists($release->path)) {
            abort(404, 'APK file not found in storage.');
        }

        $localPath = $disk->path($release->path);
        $fileSize  = filesize($localPath);
        $mimeType  = 'application/vnd.android.package-archive';

        // Parse Range header if present (e.g. Android Chrome resume).
        $rangeHeader = $request->headers->get('Range');
        [$start, $end] = $this->parseRange($rangeHeader, $fileSize);

        $isPartial     = $rangeHeader !== null;
        $contentLength = $end - $start + 1;

        $headers = [
            'Content-Type'           => $mimeType,
            'Content-Disposition'    => 'attachment; filename="sayzio.apk"',
            'Content-Length'         => (string) $contentLength,
            'Accept-Ranges'          => 'bytes',
            'Cache-Control'          => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($isPartial) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$fileSize}";
        }

        $status = $isPartial ? 206 : 200;

        return new StreamedResponse(function () use ($localPath, $start, $contentLength) {
            $fp = fopen($localPath, 'rb');
            if ($start > 0) {
                fseek($fp, $start);
            }
            $remaining = $contentLength;
            while (!feof($fp) && $remaining > 0) {
                $chunk = fread($fp, min(65536, $remaining));
                if ($chunk === false) {
                    break;
                }
                echo $chunk;
                $remaining -= strlen($chunk);
                flush();
            }
            fclose($fp);
        }, $status, $headers);
    }

    /**
     * Parse an HTTP Range header and return [start, end] bytes (inclusive).
     * Returns [0, fileSize - 1] when no valid Range header is present.
     */
    private function parseRange(?string $header, int $fileSize): array
    {
        if ($header === null || $fileSize === 0) {
            return [0, $fileSize - 1];
        }

        if (!preg_match('/^bytes=(\d*)-(\d*)$/', trim($header), $m)) {
            return [0, $fileSize - 1];
        }

        $start = $m[1] !== '' ? (int) $m[1] : null;
        $end   = $m[2] !== '' ? (int) $m[2] : null;

        if ($start === null) {
            // Suffix range: bytes=-500 means last 500 bytes.
            $start = max(0, $fileSize - ($end ?? 0));
            $end   = $fileSize - 1;
        } else {
            $end = $end ?? ($fileSize - 1);
        }

        $start = max(0, min($start, $fileSize - 1));
        $end   = max($start, min($end, $fileSize - 1));

        return [$start, $end];
    }
}
