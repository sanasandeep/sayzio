<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeController extends Controller
{
    public function show(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);

        return view('user.links.qrcode', compact('link'));
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
