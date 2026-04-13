<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\LinkTrackingService;
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
            $visitorCountry = null;
            $countries = array_map('strtoupper', $settings['country_restrictions']);
            if ($visitorCountry && !in_array(strtoupper($visitorCountry), $countries)) {
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

        if ($link->is_password_protected) {
            if (!$request->has('password')) {
                return view('common.link-password', compact('link'));
            }

            if (!Hash::check($request->input('password'), $link->password)) {
                return view('common.link-password', [
                    'link' => $link,
                    'error' => 'Incorrect password.',
                ]);
            }
        }

        $this->trackingService->track($link, $request);

        return match ($link->type) {
            'url' => redirect()->away($link->getDestinationUrl(), 301),
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

        $fileLink->increment('download_count');

        $disk = $fileLink->disk ?? 'public';
        if (Storage::disk($disk)->exists($fileLink->stored_path)) {
            return Storage::disk($disk)->download(
                $fileLink->stored_path,
                $fileLink->original_name
            );
        }

        abort(404, 'File not found.');
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
