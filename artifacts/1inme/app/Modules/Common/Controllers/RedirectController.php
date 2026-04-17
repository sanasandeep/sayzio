<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\LinkTrackingService;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Subscriber;
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
        // Resolve to the link via primary alias OR any of its additional aliases.
        $link = Link::resolveByAlias($alias);
        if (!$link) abort(404);
        $link->load('pixels');
        // Stash the alias the visitor actually used so views and tracking can
        // distinguish e.g. "/john" vs "/john-instagram" hits on the SAME page.
        $link->setAttribute('_used_alias', $alias);

        if (!$link->isAccessible()) {
            if ($redirect = $link->getExpiryRedirectUrl()) {
                return redirect()->away($redirect, 302);
            }
            return response()->view('common.link-expired', ['link' => $link], 410);
        }

        $settings = $link->settings ?? [];

        if (!empty($settings['expire_on_first_click']) && (int) $link->total_clicks >= 1) {
            if ($redirect = $link->getExpiryRedirectUrl()) {
                return redirect()->away($redirect, 302);
            }
            return response()->view('common.link-expired', ['link' => $link], 410);
        }

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

        // Splash (intermediate "transition") page — shown once per browser
        // session per link, before the visitor reaches the actual destination.
        // Bypassed when the user clicks the CTA / countdown completes and the
        // page reloads with ?_continue=1.
        if ($link->hasSplashEnabled()
            && !$request->boolean('_continue')
            && !session("link_splash_seen_{$link->id}")) {
            session(["link_splash_seen_{$link->id}" => true]);
            $splash = $link->getSplashConfig();
            $continueUrl = $request->fullUrlWithQuery(['_continue' => 1]);
            $destinationUrl = match ($link->type) {
                'url'     => $link->getDestinationUrl(),
                default   => $continueUrl,
            };
            return response()->view('common.splash', compact('link', 'splash', 'continueUrl', 'destinationUrl'));
        }

        // Optional preview / interstitial page for url/ics/vcf so owners can
        // capture engagement (dwell time) and fire marketing pixels even when
        // the underlying action would otherwise be an immediate redirect or
        // file download. The preview page IS the tracked visitor interaction
        // (one click record + engagement session, mirroring biolink semantics);
        // the follow-up `?_continue=1` request just performs the action and
        // is intentionally NOT tracked again to avoid double-counting.
        $previewableTypes = ['url', 'ics', 'vcf'];
        $previewEnabled   = in_array($link->type, $previewableTypes, true)
            && !empty($settings['show_preview_page']);

        if ($previewEnabled && !$request->boolean('_continue')) {
            $this->trackingService->track($link, $request, $alias);
            return response()->view('common.preview-page', compact('link'));
        }

        if (!$previewEnabled) {
            $this->trackingService->track($link, $request, $alias);
        }

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

    public function manifest(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias);
        if (!$link || $link->type !== 'biolink') abort(404);

        if (!$link->isAccessible()) {
            abort(404);
        }

        $bs = $link->settings['biolink'] ?? [];
        if (empty($bs['manifest']['enabled'])) {
            abort(404);
        }
        $m = $bs['manifest'] ?? [];
        $favicons = $bs['favicons'] ?? [];

        $icons = [];
        if ($link->favicon) {
            $icons[] = ['src' => $link->favicon, 'sizes' => '64x64', 'type' => 'image/png'];
        }
        if (!empty($favicons['apple_touch_icon'])) {
            $icons[] = ['src' => $favicons['apple_touch_icon'], 'sizes' => '180x180', 'type' => 'image/png'];
        }
        if (!empty($favicons['icon_512'])) {
            $icons[] = ['src' => $favicons['icon_512'], 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'];
        }

        $startUrl = !empty($m['start_url']) ? $m['start_url'] : url('/' . $link->alias);
        $categories = !empty($m['categories']) ? array_map('trim', explode(',', $m['categories'])) : [];

        $manifest = [
            'name' => $m['name'] ?? $link->title ?? '1INME Bio',
            'short_name' => $m['short_name'] ?? \Illuminate\Support\Str::limit($link->title ?? 'Bio', 12, ''),
            'description' => $m['description'] ?? '',
            'start_url' => $startUrl,
            'display' => $m['display'] ?? 'standalone',
            'orientation' => $m['orientation'] ?? 'any',
            'theme_color' => $m['theme_color'] ?? '#7c3aed',
            'background_color' => $m['background_color'] ?? '#0a0612',
            'icons' => $icons,
        ];

        if (!empty($categories)) {
            $manifest['categories'] = $categories;
        }

        return response()->json($manifest)->header('Content-Type', 'application/manifest+json');
    }

    public function rawFileDownload(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias);
        if (!$link || $link->type !== 'file') abort(404);

        if (!$link->isAccessible()) {
            if ($redirect = $link->getExpiryRedirectUrl()) {
                return redirect()->away($redirect, 302);
            }
            return response()->view('common.link-expired', ['link' => $link], 410);
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
        $link = Link::resolveByAlias($alias);
        if (!$link || $link->type !== 'biolink') abort(404);

        if (!$link->isAccessible()) {
            if ($redirect = $link->getExpiryRedirectUrl()) {
                return redirect()->away($redirect, 302);
            }
            return response()->view('common.link-expired', ['link' => $link], 410);
        }

        $block = BiolinkBlock::where('id', $blockId)->where('link_id', $link->id)->firstOrFail();
        $s = $block->settings ?? [];
        $linkData = $s['_link'] ?? [];

        $overrideUrl = $request->query('to');
        if ($overrideUrl) {
            $parsed = parse_url($overrideUrl);
            $destinationUrl = (isset($parsed['scheme']) && in_array($parsed['scheme'], ['http', 'https'])) ? $overrideUrl : '#';
        } else {
            $destinationUrl = $linkData['url'] ?? $s['link'] ?? $s['url'] ?? '#';
        }

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

        $this->trackingService->trackBlockClick($link, $block, $destinationUrl, $request, $alias);

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

    public function subscribe(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias);
        if (!$link) abort(404);

        $data = $request->validate([
            'block_id' => 'required|integer',
            'type' => 'required|in:email,whatsapp_channel,whatsapp_number',
            'email' => 'nullable|email|max:200',
            'name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'channel_url' => 'nullable|url|max:500',
        ]);

        $block = BiolinkBlock::where('id', $data['block_id'])->where('link_id', $link->id)->first();
        if (!$block) {
            return response()->json(['success' => false, 'message' => 'Invalid block.'], 400);
        }

        $typeMap = [
            'email_subscribe' => 'email',
            'whatsapp_channel_subscribe' => 'whatsapp_channel',
            'whatsapp_number_subscribe' => 'whatsapp_number',
        ];
        if (($typeMap[$block->type] ?? null) !== $data['type']) {
            return response()->json(['success' => false, 'message' => 'Invalid subscription type.'], 400);
        }

        if ($data['type'] === 'email') {
            if (empty($data['email'])) {
                return response()->json(['success' => false, 'message' => 'Email is required.'], 422);
            }
            $existing = Subscriber::where('user_id', $link->user_id)
                ->where('type', 'email')
                ->where('email', $data['email'])
                ->first();
        } elseif ($data['type'] === 'whatsapp_number') {
            $phone = preg_replace('/[^0-9+]/', '', $data['phone'] ?? '');
            if (empty($phone)) {
                $phone = 'anon_' . substr(md5($request->ip() . $block->id), 0, 12);
            }
            $existing = Subscriber::where('user_id', $link->user_id)
                ->where('type', 'whatsapp_number')
                ->where('phone', $phone)
                ->first();
            $data['phone'] = $phone;
        } else {
            $fingerprint = substr(md5($request->ip() . $request->userAgent()), 0, 16);
            $existing = Subscriber::where('user_id', $link->user_id)
                ->where('type', 'whatsapp_channel')
                ->where('block_id', $block->id)
                ->where('source', $fingerprint)
                ->first();
            $data['_fingerprint'] = $fingerprint;
        }

        if ($existing) {
            if ($existing->status === 'unsubscribed') {
                $existing->update(['status' => 'active', 'unsubscribed_at' => null]);
                return response()->json(['success' => true, 'message' => 'Re-subscribed successfully!']);
            }
            return response()->json(['success' => true, 'message' => 'Already subscribed.']);
        }

        Subscriber::create([
            'user_id' => $link->user_id,
            'link_id' => $link->id,
            'block_id' => $block->id,
            'type' => $data['type'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'name' => $data['name'] ?? null,
            'channel_url' => $data['channel_url'] ?? null,
            'status' => 'active',
            'source' => $data['type'] === 'whatsapp_channel' ? ($data['_fingerprint'] ?? $alias) : $alias,
            'subscribed_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Subscribed successfully!']);
    }
}
