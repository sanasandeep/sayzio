<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\LinkTrackingService;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class RedirectController extends Controller
{
    public function __construct(
        protected LinkTrackingService $trackingService
    ) {}

    public function handle(Request $request, string $alias)
    {
        $link = Link::with('pixels')->where('alias', $alias)->firstOrFail();

        if (!$link->isAccessible()) {
            abort(410, 'This link is no longer available.');
        }

        $settings = $link->settings ?? [];

        if (!empty($settings['country_restrictions'])) {
            $visitorCountry = app(\App\Modules\Common\Services\GeoIpService::class)->detectCountry($request->ip());
            $allowedCountries = array_map('strtoupper', $settings['country_restrictions']);
            if ($visitorCountry === null) {
                abort(403, 'This link is restricted by region and your location could not be determined.');
            }
            if (!in_array(strtoupper($visitorCountry), $allowedCountries)) {
                abort(403, 'This link is not available in your region.');
            }
        }

        if (!empty($settings['device_targeting'])) {
            $ua = $request->userAgent() ?? '';
            $deviceType = 'desktop';
            if (preg_match('/Mobile|Android.*Mobile|iPhone/i', $ua)) {
                $deviceType = 'mobile';
            } elseif (preg_match('/iPad|Android(?!.*Mobile)|Tablet/i', $ua)) {
                $deviceType = 'tablet';
            }
            $allowedDevices = array_map('strtolower', $settings['device_targeting']);
            if (!in_array($deviceType, $allowedDevices)) {
                abort(403, 'This link is not available on your device.');
            }
        }

        if ($link->is_password_protected && !session("link_unlocked_{$link->id}")) {
            if (!$request->has('password')) {
                return view('common.link-password', compact('link'));
            }

            if (!Hash::check($request->input('password'), $link->password)) {
                return view('common.link-password', [
                    'link' => $link,
                    'error' => 'Incorrect password.',
                ]);
            }

            session(["link_unlocked_{$link->id}" => true]);
        }

        $this->trackingService->track($link, $request);

        return match ($link->type) {
            'url' => redirect()->away($link->getDestinationUrl(), $link->redirect_type ?: 301),
            'biolink' => view('common.biolink', compact('link')),
            'file' => $this->handleFileDownload($link),
            'ics' => $this->handleIcsDownload($link),
            'vcf' => $this->handleVcfDownload($link),
            default => abort(404),
        };
    }

    protected function handleFileDownload(Link $link)
    {
        $fileLink = $link->fileLink;
        if (!$fileLink) abort(404);

        if (!$fileLink->show_download_page) {
            $disk = $fileLink->disk ?: 'public';
            if (!Storage::disk($disk)->exists($fileLink->stored_path)) {
                abort(404, 'File not found.');
            }
            $fileLink->increment('download_count');
            return Storage::disk($disk)->download($fileLink->stored_path, $fileLink->original_name);
        }

        return view('common.file-download', compact('link', 'fileLink'));
    }

    public function rawFileDownload(Request $request, string $alias)
    {
        $link = Link::where('alias', $alias)->where('type', 'file')->firstOrFail();

        if (!$link->isAccessible()) {
            abort(410, 'This link is no longer available.');
        }

        $settings = $link->settings ?? [];
        if (!empty($settings['country_restrictions'])) {
            $visitorCountry = app(\App\Modules\Common\Services\GeoIpService::class)->detectCountry($request->ip());
            $allowedCountries = array_map('strtoupper', $settings['country_restrictions']);
            if ($visitorCountry === null) {
                abort(403, 'This link is restricted by region and your location could not be determined.');
            }
            if (!in_array(strtoupper($visitorCountry), $allowedCountries)) {
                abort(403, 'This link is not available in your region.');
            }
        }

        if (!empty($settings['device_targeting'])) {
            $ua = $request->userAgent() ?? '';
            $deviceType = 'desktop';
            if (preg_match('/Mobile|Android.*Mobile|iPhone/i', $ua)) {
                $deviceType = 'mobile';
            } elseif (preg_match('/iPad|Android(?!.*Mobile)|Tablet/i', $ua)) {
                $deviceType = 'tablet';
            }
            if (!in_array($deviceType, array_map('strtolower', $settings['device_targeting']))) {
                abort(403, 'This link is not available on your device.');
            }
        }

        if ($link->is_password_protected && !session("link_unlocked_{$link->id}")) {
            abort(403, 'This link is password protected.');
        }

        $fileLink = $link->fileLink;
        if (!$fileLink) abort(404);

        $mode = $request->query('mode', 'download');
        $disk = $fileLink->disk ?? 'public';

        if (!Storage::disk($disk)->exists($fileLink->stored_path)) {
            abort(404, 'File not found.');
        }

        if ($mode === 'preview') {
            $mimeType = $fileLink->mime_type ?: 'application/octet-stream';
            $allowedPreviewMimes = [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
                'application/pdf',
            ];
            if (!in_array($mimeType, $allowedPreviewMimes)) {
                return Storage::disk($disk)->download($fileLink->stored_path, $fileLink->original_name);
            }
            return Storage::disk($disk)->response($fileLink->stored_path, $fileLink->original_name, [
                'Content-Type' => $mimeType,
                'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'none'; script-src 'none'",
            ]);
        }

        $fileLink->increment('download_count');
        return Storage::disk($disk)->download($fileLink->stored_path, $fileLink->original_name);
    }

    public function handleBlockClick(Request $request, string $alias, int $blockId)
    {
        $link = Link::where('alias', $alias)->where('type', 'biolink')->firstOrFail();

        if (!$link->isAccessible()) {
            abort(410, 'This link is no longer available.');
        }

        $block = BiolinkBlock::where('id', $blockId)->where('link_id', $link->id)->firstOrFail();
        $s = $block->settings ?? [];
        $linkData = $s['_link'] ?? [];
        $destinationUrl = $linkData['url'] ?? $s['link'] ?? $s['url'] ?? '#';

        if ($destinationUrl === '#' || empty($destinationUrl)) {
            abort(404, 'No destination URL configured.');
        }

        $utmParams = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $param) {
            if (!empty($linkData[$param])) {
                $utmParams[$param] = $linkData[$param];
            }
        }
        if (!empty($utmParams)) {
            $separator = str_contains($destinationUrl, '?') ? '&' : '?';
            $destinationUrl .= $separator . http_build_query($utmParams);
        }

        $this->trackingService->trackBlockClick($link, $block, $destinationUrl, $request);

        return redirect()->away($destinationUrl, 302);
    }

    protected function handleIcsDownload(Link $link)
    {
        $icsData = $link->icsData;
        if (!$icsData) abort(404);

        $content = $icsData->toIcs();
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $icsData->event_name) . '.ics';

        return response($content, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    protected function handleVcfDownload(Link $link)
    {
        $vcfData = $link->vcfData;
        if (!$vcfData) abort(404);

        $content = $vcfData->toVcf();
        $filename = trim($vcfData->first_name . '_' . $vcfData->last_name, '_ ') . '.vcf';
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);

        return response($content, 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

}
