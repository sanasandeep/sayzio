<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\QrCode as QrCodeModel;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Support\QrCodeCatalog;
use App\Modules\User\Support\QrCodeDesignSanitizer;
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
        $presets       = QrCodeCatalog::presets();
        return view('user.qr-codes.builder', compact('qrCode', 'types', 'projects', 'links', 'defaultDesign', 'presets'));
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
        return QrCodeDesignSanitizer::sanitize($d);
    }

    private function defaultDesign(): array
    {
        return QrCodeDesignSanitizer::defaultDesign();
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
