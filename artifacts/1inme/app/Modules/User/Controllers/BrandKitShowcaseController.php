<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\Link;
use App\Services\Brand\BrandKitShowcase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BrandKitShowcaseController extends Controller
{
    public function preview(Request $request, BrandKitShowcase $showcase)
    {
        $data = $request->validate(['kit_id' => 'nullable|integer', 'form' => 'required|array|max:100', 'form.*' => 'nullable|string|max:4000', 'sections' => 'required|array|min:1|max:6', 'sections.*' => 'in:'.implode(',', array_keys(BrandKitShowcase::SECTIONS))]);
        $saved = null;
        if (! empty($data['kit_id'])) {
            $saved = BrandKit::where('user_id', workspace_owner_id())->findOrFail($data['kit_id']);
        }
        $kit = $showcase->fromForm(array_map(fn ($value) => $value ?? '', $data['form']));
        $snapshot = $showcase->snapshot($kit, $data['sections'], $saved);
        $link = $showcase->previewLink($kit, $snapshot);
        $html = view('common.biolink', ['link' => $link, 'brandShowcasePreview' => true])->render();

        return response()->json(['html' => $html])->header('Cache-Control', 'no-store');
    }

    public function download(Request $request, int $id, string $token, string $format, BrandKitExportController $exports)
    {
        $link = Link::withoutGlobalScope('workspace')->findOrFail($id);
        $record = $link->settings['brand_showcase'] ?? [];
        abort_unless($link->type === 'biolink' && $link->is_active && ($link->visibility ?? 'public') === 'public' && ! $link->is_password_protected && ! $link->isExpired() && (empty($link->settings['start_at']) || now()->gte(Carbon::parse($link->settings['start_at']))) && hash_equals((string) ($record['token'] ?? ''), $token), 404);
        $snapshot = $record['snapshot'] ?? [];
        abort_unless(is_array($snapshot) && is_string($snapshot['name'] ?? null) && is_array($snapshot['config'] ?? null), 404);
        abort_unless(in_array('downloads', $snapshot['sections'] ?? [], true), 404);
        $kit = new BrandKit(['user_id' => $link->user_id, 'name' => $snapshot['name'], 'config' => $snapshot['config']]);
        $assets = [];
        foreach ($snapshot['assets'] ?? [] as $index => $asset) {
            $assets['showcase-'.$index.'-'.$asset['label']] = $asset['url'];
        }

        return $exports->package($kit, $format, $assets);
    }
}
