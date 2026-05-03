<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\ArCardBuilder;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Public AR Business Card surface:
 *   GET /ar/{alias}            — capability-detecting renderer (model-viewer)
 *   GET /ar/{alias}/model.glb  — generated GLB
 *   GET /ar/{alias}/model.usdz — generated USDZ
 *   GET /ar/{alias}/texture.png — preview texture
 *   GET /ar/{alias}/kit        — printable QR + NFC kit (HTML)
 *   GET /ar/{alias}/kit.pdf    — same kit as a downloadable PDF
 */
class ArCardController extends Controller
{
    public function __construct(protected ArCardBuilder $builder) {}

    /** Resolve the link or 404. AR must be enabled to render. */
    protected function resolve(Request $request, string $alias, bool $requireEnabled = true): Link
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if (!$link) abort(404, 'Short link not found.');
        if ($requireEnabled && !$link->ar_enabled) {
            abort(404, 'AR is not enabled for this biolink.');
        }
        return $link;
    }

    public function view(Request $request, string $alias)
    {
        $link = $this->resolve($request, $alias);
        $cfg  = $this->builder->settings($link);

        // Pick the configured blocks (cap at 6, scoped to this link).
        $picked = collect($cfg['block_ids'])->take(6)->all();
        $blocks = collect();
        if (!empty($picked)) {
            // Mirror the settings-controller filter at render time so a
            // non-tappable type that slipped in from older data (or a
            // type that was tappable when saved but later disabled) never
            // dead-ends an AR tap.
            $blocks = BiolinkBlock::where('link_id', $link->id)
                ->whereIn('id', $picked)
                ->whereIn('type', \App\Modules\User\Controllers\ArSettingsController::TAPPABLE_TYPES)
                ->where('is_active', true)
                ->orderByRaw('array_position(ARRAY[' . implode(',', array_map('intval', $picked)) . ']::int[], id)')
                ->get();
        }

        // Public path to the link itself, used as the no-AR fallback.
        $biolinkUrl = url('/' . $link->alias);

        return response()
            ->view('common.ar.card', [
                'link' => $link,
                'cfg' => $cfg,
                'blocks' => $blocks,
                'glbUrl'  => route('ar.card.glb', $alias),
                'usdzUrl' => route('ar.card.usdz', $alias),
                'biolinkUrl' => $biolinkUrl,
            ])
            ->header('X-Frame-Options', 'SAMEORIGIN');
    }

    public function glb(Request $request, string $alias)
    {
        $link = $this->resolve($request, $alias);
        $bin = $this->builder->glb($link);

        return response($bin, 200, [
            'Content-Type'  => 'model/gltf-binary',
            'Content-Length'=> (string) strlen($bin),
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function usdz(Request $request, string $alias)
    {
        $link = $this->resolve($request, $alias);
        $bin = $this->builder->usdz($link);

        return response($bin, 200, [
            'Content-Type'  => 'model/vnd.usdz+zip',
            'Content-Length'=> (string) strlen($bin),
            'Content-Disposition' => 'inline; filename="' . $link->alias . '.usdz"',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function texture(Request $request, string $alias)
    {
        $link = $this->resolve($request, $alias, requireEnabled: false);
        $png = $this->builder->texturePng($link);

        return response($png, 200, [
            'Content-Type'  => 'image/png',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function kit(Request $request, string $alias)
    {
        return $this->renderKit($request, $alias, isPdf: false);
    }

    public function kitPdf(Request $request, string $alias)
    {
        return $this->renderKit($request, $alias, isPdf: true);
    }

    protected function renderKit(Request $request, string $alias, bool $isPdf)
    {
        $link = $this->resolve($request, $alias);

        $arUrl = route('ar.card.view', $alias);
        // Each medium gets a distinct utm-style tag so creators can split
        // print vs sticker vs banner traffic.
        $mediums = [
            'card'    => ['label' => 'Business card', 'size_mm' => 25, 'tag' => 'card'],
            'sticker' => ['label' => 'Sticker',       'size_mm' => 35, 'tag' => 'sticker'],
            'tent'    => ['label' => 'Table tent',    'size_mm' => 55, 'tag' => 'tent'],
            'poster'  => ['label' => 'Poster',        'size_mm' => 90, 'tag' => 'poster'],
        ];

        $qrCodes = [];
        foreach ($mediums as $key => $m) {
            $url = $arUrl . '?utm_source=ar_kit&utm_medium=' . $m['tag'];
            $qrCodes[$key] = [
                'label'  => $m['label'],
                'size_mm'=> $m['size_mm'],
                'url'    => $url,
                'svg'    => (string) QrCode::format('svg')->size(220)->margin(0)->errorCorrection('M')->generate($url),
            ];
        }

        $nfcUrl = $arUrl . '?utm_source=ar_kit&utm_medium=nfc';

        $view = view('common.ar.kit', [
            'link'      => $link,
            'cfg'       => $this->builder->settings($link),
            'arUrl'     => $arUrl,
            'qrCodes'   => $qrCodes,
            'nfcUrl'    => $nfcUrl,
            'isPdf'     => $isPdf,
        ])->render();

        if (!$isPdf) {
            return response($view)->header('Content-Type', 'text/html; charset=utf-8');
        }

        $opts = new Options();
        $opts->set('isRemoteEnabled', true);
        $opts->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($opts);
        $dompdf->loadHtml($view, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="ar-kit-' . $link->alias . '.pdf"',
        ]);
    }
}
