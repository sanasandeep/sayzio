<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\QrCode as QrCodeModel;
use App\Modules\User\Support\QrCodeTypeRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeController extends Controller
{
    // ====================================================================
    //  QR STUDIO — full builder, library, persistence
    // ====================================================================

    public function index(Request $request)
    {
        $query = $request->user()->qrCodes()->with(['project', 'link']);
        if ($search = $request->get('search')) {
            $query->where('name', 'ilike', "%{$search}%");
        }
        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }
        if ($projectId = $request->get('project_id')) {
            $query->where('project_id', $projectId);
        }
        $qrCodes = $query->latest()->paginate(20)->withQueryString();
        $projects = $request->user()->projects()->orderBy('name')->get();
        $types = QrCodeTypeRegistry::types();
        return view('user.qr-codes.index', compact('qrCodes', 'projects', 'types'));
    }

    public function builder(Request $request, ?QrCodeModel $qrCode = null)
    {
        if ($qrCode && $qrCode->exists) {
            abort_unless($qrCode->user_id === $request->user()->id, 403);
        }
        $types = QrCodeTypeRegistry::types();
        $projects = $request->user()->projects()->orderBy('name')->get();
        $links = $request->user()->links()->where('is_active', true)
            ->orderBy('created_at', 'desc')->limit(200)
            ->get(['id', 'alias', 'title']);
        $defaultDesign = $this->defaultDesign();
        return view('user.qr-codes.builder', compact('qrCode', 'types', 'projects', 'links', 'defaultDesign'));
    }

    public function create(Request $request) { return $this->builder($request, null); }
    public function edit(Request $request, QrCodeModel $qrCode) { return $this->builder($request, $qrCode); }

    public function store(Request $request)
    {
        $data = $this->validateRequest($request);
        $qrCode = new QrCodeModel($data);
        $qrCode->user_id = $request->user()->id;
        $qrCode->save();
        return redirect()->route('user.qr-codes.edit', $qrCode)->with('success', 'QR code saved.');
    }

    public function update(Request $request, QrCodeModel $qrCode)
    {
        abort_unless($qrCode->user_id === $request->user()->id, 403);
        $qrCode->fill($this->validateRequest($request))->save();
        return redirect()->route('user.qr-codes.edit', $qrCode)->with('success', 'QR code updated.');
    }

    public function destroy(Request $request, QrCodeModel $qrCode)
    {
        abort_unless($qrCode->user_id === $request->user()->id, 403);
        $qrCode->delete();
        return redirect()->route('user.qr-codes.index')->with('success', 'QR code deleted.');
    }

    public function duplicate(Request $request, QrCodeModel $qrCode)
    {
        abort_unless($qrCode->user_id === $request->user()->id, 403);
        $copy = $qrCode->replicate(['preview_url', 'downloads']);
        $copy->name = $qrCode->name . ' (copy)';
        $copy->save();
        return redirect()->route('user.qr-codes.edit', $copy)->with('success', 'QR code duplicated.');
    }

    /** Returns the encoded payload string for a given type+payload — used for live preview. */
    public function resolvePayload(Request $request)
    {
        $request->validate([
            'type'      => ['required', Rule::in(array_keys(QrCodeTypeRegistry::types())), 'string'],
            'link_id'   => ['nullable', Rule::exists('links', 'id')->where('user_id', $request->user()->id)],
            'payload'   => 'nullable|array',
        ]);
        if ($request->filled('link_id')) {
            $link = Link::find($request->input('link_id'));
            return response()->json(['encoded' => $link ? $link->getShortUrl() : '']);
        }
        $type = $request->input('type');
        $payload = $request->input('payload', []);
        try {
            $encoded = QrCodeTypeRegistry::buildPayloadString($type, $payload);
        } catch (\Throwable $e) {
            $encoded = '';
        }
        return response()->json(['encoded' => $encoded]);
    }

    // -------- internal --------

    private function validateRequest(Request $request): array
    {
        $userId = $request->user()->id;
        $base = $request->validate([
            'name'       => 'required|string|max:160',
            'type'       => ['required', Rule::in(array_keys(QrCodeTypeRegistry::types()))],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('user_id', $userId)],
            'link_id'    => ['nullable', Rule::exists('links', 'id')->where('user_id', $userId)],
            'design'     => 'nullable|array',
        ]);

        $payloadRules = collect(QrCodeTypeRegistry::rulesFor($base['type']))
            ->mapWithKeys(fn($rule, $key) => ["payload.$key" => $rule])
            ->toArray();
        // When a link is attached, payload becomes { url: <short> } and we skip type-rules.
        if (!$request->filled('link_id')) {
            $request->validate($payloadRules);
        }

        $payload = (array) $request->input('payload', []);
        $design  = $this->sanitizeDesign((array) $request->input('design', []));
        return [
            'name'       => $base['name'],
            'type'       => $base['type'],
            'project_id' => $base['project_id'] ?? null,
            'link_id'    => $base['link_id'] ?? null,
            'payload'    => $payload,
            'design'     => $design,
        ];
    }

    private function sanitizeDesign(array $d): array
    {
        $defaults = $this->defaultDesign();
        $hex = fn($v, $fallback) => is_string($v) && preg_match('/^#[0-9A-Fa-f]{6}$/', $v) ? $v : $fallback;
        $clamp = fn($v, $min, $max, $fb) => is_numeric($v) ? max($min, min($max, (float) $v)) : $fb;
        return [
            'size'                => (int) $clamp($d['size'] ?? null, 100, 2000, $defaults['size']),
            'margin'              => (int) $clamp($d['margin'] ?? null, 0, 80, $defaults['margin']),
            'error_correction'    => in_array($d['error_correction'] ?? null, ['L','M','Q','H']) ? $d['error_correction'] : $defaults['error_correction'],
            'fg_color'            => $hex($d['fg_color']            ?? null, $defaults['fg_color']),
            'bg_color'            => $hex($d['bg_color']            ?? null, $defaults['bg_color']),
            'transparent_bg'      => (bool) ($d['transparent_bg'] ?? false),
            'dot_style'           => in_array($d['dot_style'] ?? null, ['square','rounded','dots','classy','classy-rounded','extra-rounded']) ? $d['dot_style'] : $defaults['dot_style'],
            'corner_square_style' => in_array($d['corner_square_style'] ?? null, ['dot','square','extra-rounded']) ? $d['corner_square_style'] : $defaults['corner_square_style'],
            'corner_square_color' => $hex($d['corner_square_color'] ?? null, $defaults['corner_square_color']),
            'corner_dot_style'    => in_array($d['corner_dot_style'] ?? null, ['dot','square']) ? $d['corner_dot_style'] : $defaults['corner_dot_style'],
            'corner_dot_color'    => $hex($d['corner_dot_color']    ?? null, $defaults['corner_dot_color']),
            'logo_url'            => is_string($d['logo_url'] ?? null) ? mb_substr($d['logo_url'], 0, 2000) : null,
            'logo_size'           => $clamp($d['logo_size'] ?? null, 0.05, 0.5, $defaults['logo_size']),
            'logo_margin'         => (int) $clamp($d['logo_margin'] ?? null, 0, 30, $defaults['logo_margin']),
            'hide_dots_behind_logo' => (bool) ($d['hide_dots_behind_logo'] ?? true),
            'frame'               => $this->sanitizeFrame($d['frame'] ?? [], $defaults['frame'], $hex),
        ];
    }

    private function sanitizeFrame(array $f, array $defaults, callable $hex): array
    {
        return [
            'template'   => in_array($f['template'] ?? null, ['none','scan-me','classic','rounded','ribbon','bubble','minimal','arrow']) ? $f['template'] : 'none',
            'text'       => is_string($f['text'] ?? null) ? mb_substr($f['text'], 0, 60) : 'SCAN ME',
            'font'       => in_array($f['font'] ?? null, ['Inter','Roboto','Poppins','Montserrat','Playfair Display','Bebas Neue','Pacifico']) ? $f['font'] : 'Inter',
            'bg_color'   => $hex($f['bg_color']   ?? null, '#000000'),
            'text_color' => $hex($f['text_color'] ?? null, '#ffffff'),
        ];
    }

    private function defaultDesign(): array
    {
        return [
            'size' => 400, 'margin' => 10, 'error_correction' => 'M',
            'fg_color' => '#071437', 'bg_color' => '#ffffff', 'transparent_bg' => false,
            'dot_style' => 'rounded',
            'corner_square_style' => 'extra-rounded', 'corner_square_color' => '#071437',
            'corner_dot_style' => 'dot',                'corner_dot_color' => '#071437',
            'logo_url' => null, 'logo_size' => 0.25, 'logo_margin' => 5, 'hide_dots_behind_logo' => true,
            'frame' => [
                'template' => 'none', 'text' => 'SCAN ME', 'font' => 'Inter',
                'bg_color' => '#071437', 'text_color' => '#ffffff',
            ],
        ];
    }


    public function show(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);

        return view('user.links.qrcode', compact('link'));
    }

    public function standalone()
    {
        return view('user.links.qrcode-standalone');
    }

    public function generateStandalone(Request $request)
    {
        $validated = $request->validate([
            'url' => 'required|url|max:2048',
            'size' => 'nullable|integer|min:100|max:1000',
            'format' => 'nullable|in:png,svg',
            'fg_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'bg_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'error_correction' => 'nullable|in:L,M,Q,H',
            'logo' => \App\Services\UploadPolicy::rule('qr.logo', $request->user()),
        ]);

        $size = (int) ($validated['size'] ?? 300);
        $format = $validated['format'] ?? 'png';
        $fgColor = $validated['fg_color'] ?? '#000000';
        $bgColor = $validated['bg_color'] ?? '#FFFFFF';
        $errorCorrection = $validated['error_correction'] ?? 'M';

        $fgRgb = $this->hexToRgb($fgColor);
        $bgRgb = $this->hexToRgb($bgColor);

        $qr = QrCode::format($format)
            ->size($size)
            ->color($fgRgb[0], $fgRgb[1], $fgRgb[2])
            ->backgroundColor($bgRgb[0], $bgRgb[1], $bgRgb[2])
            ->errorCorrection($errorCorrection)
            ->margin(1);

        if ($format === 'png' && $request->hasFile('logo')) {
            $logoPath = $request->file('logo')->getRealPath();
            $qr->merge($logoPath, .25, true);
        }

        $qrImage = $qr->generate($validated['url']);
        $slug = preg_replace('/[^a-z0-9]+/i', '-', parse_url($validated['url'], PHP_URL_HOST) ?: 'custom');

        if ($format === 'svg') {
            return response($qrImage)
                ->header('Content-Type', 'image/svg+xml')
                ->header('Content-Disposition', "attachment; filename=\"qr-{$slug}.svg\"");
        }

        return response($qrImage)
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', "attachment; filename=\"qr-{$slug}.png\"");
    }

    public function previewStandalone(Request $request)
    {
        $url = $request->get('url', 'https://example.com');
        if (!filter_var($url, FILTER_VALIDATE_URL)) $url = 'https://example.com';

        $size = max(100, min(1000, (int) $request->get('size', 300)));
        $fgColor = $request->get('fg_color', '#000000');
        $bgColor = $request->get('bg_color', '#FFFFFF');
        $errorCorrection = $request->get('error_correction', 'M');

        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $fgColor)) $fgColor = '#000000';
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $bgColor)) $bgColor = '#FFFFFF';
        if (!in_array($errorCorrection, ['L', 'M', 'Q', 'H'])) $errorCorrection = 'M';

        $fgRgb = $this->hexToRgb($fgColor);
        $bgRgb = $this->hexToRgb($bgColor);

        $qr = QrCode::format('svg')
            ->size($size)
            ->color($fgRgb[0], $fgRgb[1], $fgRgb[2])
            ->backgroundColor($bgRgb[0], $bgRgb[1], $bgRgb[2])
            ->errorCorrection($errorCorrection)
            ->margin(1)
            ->generate($url);

        return response($qr)->header('Content-Type', 'image/svg+xml');
    }

    public function generate(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'size' => 'nullable|integer|min:100|max:1000',
            'format' => 'nullable|in:png,svg',
            'fg_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'bg_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'error_correction' => 'nullable|in:L,M,Q,H',
            'logo' => \App\Services\UploadPolicy::rule('qr.logo', $request->user()),
        ]);

        $size = (int) ($validated['size'] ?? 300);
        $format = $validated['format'] ?? 'png';
        $fgColor = $validated['fg_color'] ?? '#000000';
        $bgColor = $validated['bg_color'] ?? '#FFFFFF';
        $errorCorrection = $validated['error_correction'] ?? 'M';

        $fgRgb = $this->hexToRgb($fgColor);
        $bgRgb = $this->hexToRgb($bgColor);

        $url = $link->getShortUrl();

        $qr = QrCode::format($format)
            ->size($size)
            ->color($fgRgb[0], $fgRgb[1], $fgRgb[2])
            ->backgroundColor($bgRgb[0], $bgRgb[1], $bgRgb[2])
            ->errorCorrection($errorCorrection)
            ->margin(1);

        if ($format === 'png' && $request->hasFile('logo')) {
            $logoPath = $request->file('logo')->getRealPath();
            $qr->merge($logoPath, .25, true);
        }

        $qrImage = $qr->generate($url);

        if ($format === 'svg') {
            return response($qrImage)
                ->header('Content-Type', 'image/svg+xml')
                ->header('Content-Disposition', "attachment; filename=\"qr-{$link->alias}.svg\"");
        }

        return response($qrImage)
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', "attachment; filename=\"qr-{$link->alias}.png\"");
    }

    public function preview(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);

        $size = (int) ($request->get('size', 300));
        $fgColor = $request->get('fg_color', '#000000');
        $bgColor = $request->get('bg_color', '#FFFFFF');
        $errorCorrection = $request->get('error_correction', 'M');

        $size = max(100, min(1000, $size));

        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $fgColor)) $fgColor = '#000000';
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $bgColor)) $bgColor = '#FFFFFF';
        if (!in_array($errorCorrection, ['L', 'M', 'Q', 'H'])) $errorCorrection = 'M';

        $fgRgb = $this->hexToRgb($fgColor);
        $bgRgb = $this->hexToRgb($bgColor);

        $url = $link->getShortUrl();

        $qr = QrCode::format('svg')
            ->size($size)
            ->color($fgRgb[0], $fgRgb[1], $fgRgb[2])
            ->backgroundColor($bgRgb[0], $bgRgb[1], $bgRgb[2])
            ->errorCorrection($errorCorrection)
            ->margin(1)
            ->generate($url);

        return response($qr)->header('Content-Type', 'image/svg+xml');
    }

    protected function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
