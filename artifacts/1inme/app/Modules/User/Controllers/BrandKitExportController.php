<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\BrandKitAsset;
use App\Modules\User\Models\UserFile;
use App\Services\Brand\BrandKitFields;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class BrandKitExportController extends Controller
{
    public function download(BrandKit $brandKit, string $format)
    {
        abort_unless((int) $brandKit->user_id === (int) workspace_owner_id(), 403);

        return $this->package($brandKit, $format);
    }

    public function package(BrandKit $brandKit, string $format, array $extraAssets = [])
    {
        abort_unless(in_array($format, ['pdf', 'zip'], true), 404);
        $options = new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
        $pdf = new Dompdf($options);
        $logoData = null;
        $logoPath = parse_url($brandKit->logo(), PHP_URL_PATH) ?: '';
        if (preg_match('~^/f/(\d+)/~', $logoPath, $match)) {
            $logo = UserFile::where('user_id', $brandKit->user_id)->find((int) $match[1]);
            if ($logo && $logo->isScanClean() && ! $logo->quarantined_at && in_array($logo->mime_type, ['image/png', 'image/jpeg'], true) && $logo->size_bytes <= 2 * 1024 * 1024) {
                $logoData = 'data:'.$logo->mime_type.';base64,'.base64_encode(Storage::disk($logo->disk ?: 'user_files')->get($logo->path));
            }
        }
        $pdf->loadHtml(view('user.brand-kits.guide', ['kit' => $brandKit, 'logoData' => $logoData])->render());
        $pdf->setPaper('A4');
        $pdf->render();
        $pdf->getCanvas()->page_text(505, 810, '{PAGE_NUM} / {PAGE_COUNT}', $pdf->getFontMetrics()->getFont('DejaVu Sans'), 8, [0.4, 0.46, 0.55]);
        $name = Str::slug($brandKit->name) ?: 'brand-kit';
        if ($format === 'pdf') {
            return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$name.'-brand-guide.pdf"']);
        }

        abort_unless(class_exists(ZipArchive::class), 503, 'ZIP export is unavailable on this server. PDF export is still available.');
        $path = tempnam(sys_get_temp_dir(), 'brand-export-');
        $zip = new ZipArchive;
        abort_unless($zip->open($path, ZipArchive::OVERWRITE) === true, 503);
        try {
            $zip->addFromString('brand-guide.pdf', $pdf->output());
            $zip->addFromString('brand-kit.json', json_encode(['name' => $brandKit->name, 'config' => $brandKit->config], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $manifest = [];
            $total = 0;
            $seen = [];
            $urls = array_merge(array_intersect_key($brandKit->config ?? [], BrandKitFields::MEDIA + array_fill_keys(['logo', 'logo_light', 'logo_dark', 'favicon'], '')), $extraAssets);
            foreach ($brandKit->exists ? BrandKitAsset::where('brand_kit_id', $brandKit->id)->with('file')->get() : [] as $asset) {
                if ($asset->file) {
                    $urls['generated-'.$asset->type] = $asset->file->url;
                }
            }
            foreach ($urls as $label => $url) {
                if (! is_string($url) || ! $url) {
                    continue;
                }
                $entry = ['asset' => $label, 'url' => $url, 'included' => false];
                // Only shipped demo SVGs and owned library files are embedded; remote URLs are never fetched.
                $urlPath = parse_url($url, PHP_URL_PATH) ?: '';
                if (preg_match('~^/brand-demo/([a-z0-9-]+\.svg)$~', $urlPath, $demo)) {
                    $demoPath = public_path('brand-demo/'.$demo[1]);
                    if (is_file($demoPath)) {
                        $assetName = 'assets/'.Str::slug($label).'-demo.svg';
                        $zip->addFromString($assetName, file_get_contents($demoPath));
                        $entry['included'] = true;
                        $entry['file'] = $assetName;
                    }
                }
                if (preg_match('~^/f/(\d+)/~', $urlPath, $m)) {
                    $file = UserFile::where('user_id', $brandKit->user_id)->find((int) $m[1]);
                    if ($file && ! $file->quarantined_at && $file->isScanClean() && ! isset($seen[$file->id])) {
                        $disk = Storage::disk($file->disk ?: 'user_files');
                        $bytes = $disk->size($file->path);
                        abort_if($bytes > 25 * 1024 * 1024 || $total + $bytes > 100 * 1024 * 1024, 422, 'Export exceeds 100 MB total or 25 MB per file. Download large assets individually from My Files.');
                        $contents = $disk->get($file->path);
                        $total += strlen($contents);
                        $ext = strtolower(pathinfo($file->filename, PATHINFO_EXTENSION));
                        $assetName = 'assets/'.Str::slug($label).'-'.$file->id.($ext ? '.'.preg_replace('/[^a-z0-9]/', '', $ext) : '');
                        $zip->addFromString($assetName, $contents);
                        $seen[$file->id] = true;
                        $entry['included'] = true;
                        $entry['file'] = $assetName;
                    } elseif ($file && isset($seen[$file->id])) {
                        $entry['included'] = true;
                    }
                }
                $manifest[] = $entry;
            }
            $zip->addFromString('asset-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $zip->addFromString('README.txt', "Brand package\nIncludes the saved brand guide, specifications and available owned library assets.\nExternal image URLs are listed in asset-manifest.json, not fetched.\nFont files are included only when uploaded to your library; verify redistribution rights.\nAI-created campaign guidance is a draft, not legal compliance verification.\n");
            $zip->close();
        } catch (\Throwable $e) {
            $zip->close();
            @unlink($path);
            throw $e;
        }

        return response()->download($path, $name.'-brand-kit.zip')->deleteFileAfterSend(true);
    }
}
