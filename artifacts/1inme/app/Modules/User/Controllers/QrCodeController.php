<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\QrCode as QrCodeModel;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Support\QrCodeCatalog;
use App\Modules\User\Support\QrCodeTypeRegistry;
use App\Services\UploadPolicy;
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
        $query = workspace_owner()->qrCodes()->with(['project', 'link']);
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
        $projects = workspace_owner()->projects()->orderBy('name')->get();
        $types = QrCodeTypeRegistry::types();
        return view('user.qr-codes.index', compact('qrCodes', 'projects', 'types'));
    }

    public function builder(Request $request, ?QrCodeModel $qrCode = null)
    {
        if ($qrCode && $qrCode->exists) {
            abort_unless($qrCode->user_id === workspace_owner_id(), 403);
        }
        $types = QrCodeTypeRegistry::types();
        $projects = workspace_owner()->projects()->orderBy('name')->get();
        $links = workspace_owner()->links()->where('is_active', true)
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
        $qrCode->user_id = workspace_owner_id();
        $qrCode->save();
        return redirect()->route('user.qr-codes.edit', $qrCode)->with('success', 'QR code saved.');
    }

    public function update(Request $request, QrCodeModel $qrCode)
    {
        abort_unless($qrCode->user_id === workspace_owner_id(), 403);
        $qrCode->fill($this->validateRequest($request))->save();
        return redirect()->route('user.qr-codes.edit', $qrCode)->with('success', 'QR code updated.');
    }

    public function destroy(Request $request, QrCodeModel $qrCode)
    {
        abort_unless($qrCode->user_id === workspace_owner_id(), 403);
        $qrCode->delete();
        return redirect()->route('user.qr-codes.index')->with('success', 'QR code deleted.');
    }

    public function duplicate(Request $request, QrCodeModel $qrCode)
    {
        abort_unless($qrCode->user_id === workspace_owner_id(), 403);
        $copy = $qrCode->replicate(['preview_url', 'downloads']);
        $copy->name = $qrCode->name . ' (copy)';
        $copy->save();
        return redirect()->route('user.qr-codes.edit', $copy)->with('success', 'QR code duplicated.');
    }

    /** Returns the encoded payload string for a given type+payload — used for live preview. */
    /** Logo upload endpoint for any of the 3 QR builder logo slots. */
    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => UploadPolicy::rule('qr.logo', $request->user(), true),
            'slot' => ['nullable', Rule::in(['center', 'background', 'foreground'])],
        ]);
        $cap = UploadPolicy::for('qr.logo', $request->user());
        try {
            $file = UserFile::createFromUpload($request->file('logo'), $request->user(), [
                'max_size_mb' => $cap['max_mb'],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
        return response()->json(['url' => $file->url]);
    }

    public function resolvePayload(Request $request)
    {
        $request->validate([
            'type'      => ['required', Rule::in(array_keys(QrCodeTypeRegistry::types())), 'string'],
            'link_id'   => ['nullable', Rule::exists('links', 'id')->where('user_id', workspace_owner_id())],
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
        // Builder posts the entire design tree as a JSON blob in `design_json`
        // for round-trip fidelity. Decode it back into a `design` array so the
        // rest of the validation/sanitization pipeline keeps working unchanged.
        if ($request->filled('design_json') && !$request->has('design')) {
            $decoded = json_decode((string) $request->input('design_json'), true);
            if (is_array($decoded)) $request->merge(['design' => $decoded]);
        }
        if ($request->filled('payload_json') && !$request->has('payload')) {
            $decoded = json_decode((string) $request->input('payload_json'), true);
            if (is_array($decoded)) $request->merge(['payload' => $decoded]);
        }
        $userId = workspace_owner_id();
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
        $oneOf = fn($v, array $allowed, $fb) => in_array($v, $allowed, true) ? $v : $fb;

        $dotIds   = QrCodeCatalog::dotIds();
        $outerIds = QrCodeCatalog::outerEyeIds();
        $innerIds = QrCodeCatalog::innerEyeIds();

        return [
            'size'                => (int) $clamp($d['size'] ?? null, 100, 2000, $defaults['size']),
            'margin'              => (int) $clamp($d['margin'] ?? null, 0, 80, $defaults['margin']),
            'error_correction'    => $oneOf($d['error_correction'] ?? null, ['L','M','Q','H'], $defaults['error_correction']),
            'fg_color'            => $hex($d['fg_color']            ?? null, $defaults['fg_color']),
            'bg_color'            => $hex($d['bg_color']            ?? null, $defaults['bg_color']),
            'transparent_bg'      => (bool) ($d['transparent_bg'] ?? false),
            'dot_style'           => $oneOf($d['dot_style']           ?? null, $dotIds,   $defaults['dot_style']),
            'corner_square_style' => $oneOf($d['corner_square_style'] ?? null, $outerIds, $defaults['corner_square_style']),
            'corner_square_color' => $hex($d['corner_square_color'] ?? null, $defaults['corner_square_color']),
            'corner_dot_style'    => $oneOf($d['corner_dot_style']    ?? null, $innerIds, $defaults['corner_dot_style']),
            'corner_dot_color'    => $hex($d['corner_dot_color']    ?? null, $defaults['corner_dot_color']),
            'qr_rotation'         => (int) $oneOf((int) ($d['qr_rotation'] ?? 0), [0,90,180,270], 0),
            'drop_shadow'         => (bool) ($d['drop_shadow'] ?? false),
            'gradient'            => $this->sanitizeGradient($d['gradient'] ?? [], $hex),
            'eye_outer_gradient'  => $this->sanitizeGradient($d['eye_outer_gradient'] ?? [], $hex),
            'eye_inner_gradient'  => $this->sanitizeGradient($d['eye_inner_gradient'] ?? [], $hex),
            'bg_gradient'         => $this->sanitizeGradient($d['bg_gradient'] ?? [], $hex),
            'hide_dots_behind_logo' => (bool) ($d['hide_dots_behind_logo'] ?? true),
            'logo_center'         => $this->sanitizeLogo($d['logo_center']     ?? [], $defaults['logo_center'], $clamp),
            'logo_background'     => $this->sanitizeLogo($d['logo_background'] ?? [], $defaults['logo_background'], $clamp),
            'logo_foreground'     => $this->sanitizeLogo($d['logo_foreground'] ?? [], $defaults['logo_foreground'], $clamp),
            'frame'               => $this->sanitizeFrame($d['frame'] ?? [], $defaults['frame'], $hex),
        ];
    }

    private function sanitizeLogo(array $l, array $defaults, callable $clamp): array
    {
        return [
            'url'      => is_string($l['url'] ?? null) && $l['url'] !== '' ? mb_substr($l['url'], 0, 2000) : null,
            'show'     => (bool) ($l['show'] ?? false),
            'size'     => (float) $clamp($l['size']     ?? null, 0.02, 1.0, $defaults['size']),
            'x'        => (float) $clamp($l['x']        ?? null, 0,    100, $defaults['x']),
            'y'        => (float) $clamp($l['y']        ?? null, 0,    100, $defaults['y']),
            'opacity'  => (float) $clamp($l['opacity']  ?? null, 0,    1,   $defaults['opacity']),
            'rotation' => (int)   $clamp($l['rotation'] ?? null, -360, 360, $defaults['rotation']),
        ];
    }

    private function sanitizeGradient(array $g, callable $hex): array
    {
        return [
            'enabled' => (bool) ($g['enabled'] ?? false),
            'type'    => in_array($g['type'] ?? null, ['linear','radial'], true) ? $g['type'] : 'linear',
            'from'    => $hex($g['from'] ?? null, '#000000'),
            'to'      => $hex($g['to']   ?? null, '#5b8def'),
            'angle'   => (int) max(0, min(360, (int) ($g['angle'] ?? 0))),
        ];
    }

    private function sanitizeFrame(array $f, array $defaults, callable $hex): array
    {
        $frameIds = QrCodeCatalog::frameIds();
        $fonts    = QrCodeCatalog::fonts();
        return [
            'template'   => in_array($f['template'] ?? null, $frameIds, true) ? $f['template'] : 'none',
            'text'       => is_string($f['text'] ?? null) ? mb_substr($f['text'], 0, 60) : 'SCAN ME',
            'font'       => in_array($f['font'] ?? null, $fonts, true) ? $f['font'] : 'Inter',
            'bg_color'   => $hex($f['bg_color']   ?? null, '#000000'),
            'text_color' => $hex($f['text_color'] ?? null, '#ffffff'),
        ];
    }

    private function defaultDesign(): array
    {
        $logoDefault = ['url' => null, 'show' => false, 'size' => 0.25, 'x' => 50, 'y' => 50, 'opacity' => 1.0, 'rotation' => 0];
        return [
            'size' => 400, 'margin' => 4, 'error_correction' => 'M',
            'fg_color' => '#071437', 'bg_color' => '#ffffff', 'transparent_bg' => false,
            'dot_style' => 'rounded',
            'corner_square_style' => 'extra-rounded', 'corner_square_color' => '#071437',
            'corner_dot_style' => 'dot',              'corner_dot_color' => '#071437',
            'qr_rotation' => 0, 'drop_shadow' => false,
            'gradient'            => ['enabled' => false, 'type' => 'linear', 'from' => '#071437', 'to' => '#5b8def', 'angle' => 45],
            'eye_outer_gradient'  => ['enabled' => false, 'type' => 'linear', 'from' => '#071437', 'to' => '#5b8def', 'angle' => 45],
            'eye_inner_gradient'  => ['enabled' => false, 'type' => 'linear', 'from' => '#071437', 'to' => '#5b8def', 'angle' => 45],
            'bg_gradient'         => ['enabled' => false, 'type' => 'linear', 'from' => '#ffffff', 'to' => '#e2e8f0', 'angle' => 180],
            'hide_dots_behind_logo' => true,
            'logo_center'     => ['url' => null, 'show' => false, 'size' => 0.25, 'x' => 50, 'y' => 50, 'opacity' => 1.0, 'rotation' => 0],
            'logo_background' => ['url' => null, 'show' => false, 'size' => 1.0,  'x' => 50, 'y' => 50, 'opacity' => 0.3, 'rotation' => 0],
            'logo_foreground' => ['url' => null, 'show' => false, 'size' => 0.2,  'x' => 80, 'y' => 80, 'opacity' => 1.0, 'rotation' => 0],
            'frame' => [
                'template' => 'none', 'text' => 'SCAN ME', 'font' => 'Inter',
                'bg_color' => '#071437', 'text_color' => '#ffffff',
            ],
        ];
    }


    public function show(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);

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
        abort_if($link->user_id !== workspace_owner_id(), 403);

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
        abort_if($link->user_id !== workspace_owner_id(), 403);

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
