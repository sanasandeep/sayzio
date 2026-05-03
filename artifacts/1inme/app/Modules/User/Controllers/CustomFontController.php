<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\CustomFont;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Manage user-uploaded fonts that surface in the "My Fonts" section pinned
 * at the top of every font picker (page font, block-theme font, per-block
 * font). Deliberately kept tiny — no quota plumbing here; we cap the count
 * per user and the per-file size at controller-level so a runaway upload
 * loop can't fill the disk.
 */
class CustomFontController extends Controller
{
    /** Hard cap on uploaded fonts per user (UI also enforces). */
    private const MAX_FONTS_PER_USER = 30;

    /** Per-file size cap in MB — fonts are tiny, this is generous. */
    private const MAX_FILE_MB = 5;

    public function index(Request $request)
    {
        $fonts = $request->user()->customFonts()->orderBy('family')->get(
            ['id', 'family', 'original_name', 'format', 'size_bytes', 'created_at']
        )->map(fn ($f) => [
            'id' => $f->id,
            'family' => $f->family,
            'original_name' => $f->original_name,
            'format' => $f->format,
            'size_bytes' => (int) $f->size_bytes,
            'url' => $f->url,
            'token' => $f->settingsToken(),
        ]);

        return response()->json(['success' => true, 'fonts' => $fonts]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'family' => 'required|string|max:60',
            // mimes: rule is unreliable for woff2/otf (driver detection
            // varies); we re-check the extension manually below.
            'file' => ['required', 'file', 'max:' . (self::MAX_FILE_MB * 1024)],
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $format = CustomFont::detectFormat($ext);
        if (!$format) {
            return response()->json(['success' => false, 'error' => 'Only .woff, .woff2, .ttf or .otf files are accepted.'], 422);
        }

        // Sanitize family name to printable letters/numbers/spaces/hyphens —
        // this string is embedded in CSS @font-face and font-family rules so
        // we keep it conservative. Empty after sanitize → reject.
        $family = trim(preg_replace('/[^A-Za-z0-9 \-_]/', '', $request->input('family')));
        $family = substr($family, 0, 60);
        if ($family === '') {
            return response()->json(['success' => false, 'error' => 'Font family must contain letters or numbers.'], 422);
        }

        if ($user->customFonts()->count() >= self::MAX_FONTS_PER_USER) {
            return response()->json([
                'success' => false,
                'error' => 'You\'ve reached the limit of ' . self::MAX_FONTS_PER_USER . ' custom fonts. Delete one to add another.',
            ], 422);
        }

        if ($user->customFonts()->where('family', $family)->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'A font with that family name already exists. Pick a different name.',
            ], 422);
        }

        $filename = Str::random(24) . '.' . $ext;
        $path = 'custom-fonts/' . $user->id . '/' . $filename;
        Storage::disk('public')->putFileAs(dirname($path), $file, basename($path));

        $font = CustomFont::create([
            'user_id' => $user->id,
            'family' => $family,
            'original_name' => substr($file->getClientOriginalName(), 0, 200),
            'disk' => 'public',
            'path' => $path,
            'format' => $format,
            'size_bytes' => $file->getSize() ?: 0,
        ]);

        return response()->json([
            'success' => true,
            'font' => [
                'id' => $font->id,
                'family' => $font->family,
                'original_name' => $font->original_name,
                'format' => $font->format,
                'size_bytes' => (int) $font->size_bytes,
                'url' => $font->url,
                'token' => $font->settingsToken(),
            ],
        ]);
    }

    public function destroy(Request $request, CustomFont $font)
    {
        if ((int) $font->user_id !== (int) $request->user()->id) {
            return response()->json(['success' => false, 'error' => 'Unauthorized.'], 403);
        }
        $font->deleteFile();
        return response()->json(['success' => true]);
    }
}
