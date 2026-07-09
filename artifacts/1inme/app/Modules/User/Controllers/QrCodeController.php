<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\QrCode as QrCodeModel;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Support\QrCodeCatalog;
use App\Modules\User\Support\QrCodeDesignSanitizer;
use App\Modules\User\Support\QrCodeTypeRegistry;
use App\Services\AI\AiActionCooldown;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\InsufficientCoinsForAiException;
use App\Services\AI\QrArtGenerationException;
use App\Services\AI\QrArtService;
use App\Services\AI\QrArtUnavailableException;
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

        // Deep-link prefill: link pages ("QR Code" actions) open the studio
        // in create mode pre-bound to that link (?link_id=N). Ownership is
        // enforced; the link is injected into the picker if it fell outside
        // the recent-200 window or is inactive.
        $prefillLinkId = null;
        $prefillName   = null;
        if (!($qrCode && $qrCode->exists) && $request->filled('link_id')) {
            $prefillLink = Link::where('id', (int) $request->query('link_id'))
                ->where('user_id', workspace_owner_id())
                ->first(['id', 'alias', 'title']);
            if ($prefillLink) {
                $prefillLinkId = $prefillLink->id;
                $prefillName   = 'QR — ' . ($prefillLink->title ?: $prefillLink->alias);
                if (!$links->contains('id', $prefillLink->id)) {
                    $links->prepend($prefillLink);
                }
            }
        }

        $defaultDesign = $this->defaultDesign();
        $presets       = QrCodeCatalog::presets();

        // AI Artistic QR availability for this user — drives the "AI Artistic"
        // tab between preview/disabled and live states. Cost is the admin coin
        // rate scaled by the plan provider multiplier.
        $art           = app(QrArtService::class);
        $owner         = workspace_owner();
        $qrArtEnabled  = $art->enabled();
        $qrArtAllowed  = AiPlanAccess::featureAllowed($owner, 'qr_art');
        $qrArtCost     = $art->coinCost($owner);
        $qrArtBalance  = app(AiUsageCharger::class)->getBalance($owner);
        $qrArtPresets  = QrCodeCatalog::aiArtStylePresets();

        return view('user.qr-codes.builder', compact(
            'qrCode', 'types', 'projects', 'links', 'defaultDesign', 'presets',
            'qrArtEnabled', 'qrArtAllowed', 'qrArtCost', 'qrArtBalance', 'qrArtPresets',
            'prefillLinkId', 'prefillName'
        ));
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

    /**
     * Generate a scannable AI Artistic QR for the given destination + prompt
     * via the Replicate QR-ControlNet model. Charges the coin wallet (with
     * auto-refund on failure) and returns the stored artwork URL for the
     * builder to drop into design.ai_art. Gated on feature availability,
     * plan access, and a coin balance.
     */
    public function generateArt(Request $request, QrArtService $art)
    {
        // Charge + gate against the workspace OWNER, matching builder() and
        // store() ownership semantics. In a team workspace the collaborator
        // triggers the call but the owner's plan/wallet is authoritative, so
        // billing the request user would mischarge and show wrong balances.
        $user = workspace_owner();

        if (!$art->enabled()) {
            return response()->json([
                'error' => "AI Artistic QR isn't available yet — an administrator needs to add a Replicate API key.",
                'code'  => 'disabled',
            ], 422);
        }

        if (!AiPlanAccess::featureAllowed($user, 'qr_art')) {
            $plan = $user->planThatUnlocks('qr_art');
            $msg  = "Your plan doesn't include AI Artistic QR."
                . ($plan ? " Upgrade to {$plan->name} to unlock it." : '');
            return response()->json(['error' => $msg, 'code' => 'plan'], 403);
        }

        $validated = $request->validate([
            'data'            => 'required|string|max:2048',
            'prompt'          => 'required|string|max:600',
            'style'           => 'nullable|string|max:60',
            'negative_prompt' => 'nullable|string|max:600',
            'strength'        => 'nullable|integer|min:0|max:100',
        ]);

        // Double-charge guard: an identical regeneration inside the cooldown
        // window re-serves the stored artwork without a new coin charge; a
        // concurrent identical request 429s instead of double-charging.
        $cooldownKey = AiActionCooldown::key('qr_art', $user->id, [
            'data'            => $validated['data'],
            'prompt'          => $validated['prompt'],
            'negative_prompt' => $validated['negative_prompt'] ?? null,
            'strength'        => $validated['strength'] ?? null,
        ]);
        if ($hit = AiActionCooldown::fresh($cooldownKey)) {
            return response()->json($hit['result'] + [
                'cost'         => 0,
                'balance'      => app(\App\Services\AI\AiUsageCharger::class)->getBalance($user),
                'cached'       => true,
                'generated_at' => $hit['generated_at'],
            ]);
        }
        if (!AiActionCooldown::begin($cooldownKey)) {
            return response()->json([
                'error' => 'This artwork is already generating — give it a moment.',
                'code'  => 'in_progress',
            ], 429);
        }

        try {
            $result = $art->generate($user, $validated['data'], $validated['prompt'], [
                'negative_prompt' => $validated['negative_prompt'] ?? null,
                'strength'        => $validated['strength'] ?? null,
            ]);
        } catch (InsufficientCoinsForAiException $e) {
            return response()->json([
                'error'    => "Not enough coins — this needs {$e->required}, your balance is {$e->balance}.",
                'code'     => 'coins',
                'required' => $e->required,
                'balance'  => $e->balance,
            ], 402);
        } catch (QrArtUnavailableException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => 'disabled'], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => 'invalid'], 422);
        } catch (QrArtGenerationException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => 'failed'], 422);
        } finally {
            AiActionCooldown::end($cooldownKey);
        }

        AiActionCooldown::remember($cooldownKey, [
            'image_url' => $result['image_url'],
            'file_id'   => $result['file_id'],
        ]);

        return response()->json($result);
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
        $out = [
            'name'       => $base['name'],
            'type'       => $base['type'],
            'project_id' => $base['project_id'] ?? null,
            'link_id'    => $base['link_id'] ?? null,
            'payload'    => $payload,
            'design'     => $design,
        ];
        // Persist the AI artwork as the saved preview so library thumbnails and
        // PNG re-export use the generated image rather than a re-rendered SVG.
        if (!empty($design['ai_art']['image_url'])) {
            $out['preview_url'] = $design['ai_art']['image_url'];
        }
        return $out;
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
