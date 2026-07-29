<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class AdminAsset extends Model
{
    protected $fillable = [
        'admin_id', 'original_name', 'filename', 'mime_type',
        'size_bytes', 'type', 'width', 'height', 'disk', 'path', 'folder',
        'label', 'description', 'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'size_bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    protected $appends = ['url', 'url_path', 'size_human', 'dimensions'];

    /**
     * Read pixel dimensions from an image file on local disk.
     * Rasters go through getimagesize; SVGs fall back to parsing the
     * width/height attributes or the viewBox. Returns [null, null] when
     * the dimensions cannot be determined.
     *
     * @return array{0: int|null, 1: int|null}
     */
    public static function probeImageDimensions(string $path, ?string $ext = null): array
    {
        try {
            $ext = strtolower((string) ($ext ?? pathinfo($path, PATHINFO_EXTENSION)));
            if ($ext === 'svg') {
                $head = (string) @file_get_contents($path, false, null, 0, 8192);
                if ($head === '' || !preg_match('/<svg\b[^>]*>/is', $head, $tag)) {
                    return [null, null];
                }
                $svg = $tag[0];
                $w = preg_match('/\bwidth\s*=\s*["\']?([0-9.]+)(?:px)?["\']?/i', $svg, $m) ? (float) $m[1] : null;
                $h = preg_match('/\bheight\s*=\s*["\']?([0-9.]+)(?:px)?["\']?/i', $svg, $m) ? (float) $m[1] : null;
                if (($w === null || $h === null)
                    && preg_match('/\bviewBox\s*=\s*["\']\s*[0-9.+-]+[\s,]+[0-9.+-]+[\s,]+([0-9.]+)[\s,]+([0-9.]+)/i', $svg, $m)) {
                    $w = $w ?? (float) $m[1];
                    $h = $h ?? (float) $m[2];
                }
                return [
                    $w !== null && $w > 0 ? (int) round($w) : null,
                    $h !== null && $h > 0 ? (int) round($h) : null,
                ];
            }
            $info = @getimagesize($path);
            if (is_array($info) && (int) $info[0] > 0 && (int) $info[1] > 0) {
                return [(int) $info[0], (int) $info[1]];
            }
        } catch (\Throwable $e) {
            // fall through
        }
        return [null, null];
    }

    public function getDimensionsAttribute(): ?string
    {
        if (!$this->width || !$this->height) {
            return null;
        }
        return $this->width . '×' . $this->height;
    }

    /**
     * Detect a coarse file family from the mime so the UI can group / filter.
     */
    public static function detectType(string $mime): string
    {
        if (str_starts_with($mime, 'image/')) return 'image';
        if (str_starts_with($mime, 'video/')) return 'video';
        if (str_starts_with($mime, 'audio/')) return 'audio';
        if (in_array($mime, ['application/zip', 'application/x-zip-compressed', 'application/x-rar-compressed', 'application/x-7z-compressed', 'application/x-tar', 'application/gzip'], true)) {
            return 'archive';
        }
        if ($mime === 'application/pdf' || str_contains($mime, 'word') || str_contains($mime, 'excel')
            || str_contains($mime, 'spreadsheet') || str_contains($mime, 'presentation') || str_contains($mime, 'opendocument')
            || str_starts_with($mime, 'text/')) {
            return 'document';
        }
        return 'other';
    }

    public static function diskName(): string
    {
        return (string) config('filesystems.admin_assets_disk', 'admin_assets');
    }

    public function getUrlAttribute(): string
    {
        return url($this->getUrlPathAttribute());
    }

    public function getUrlPathAttribute(): string
    {
        return '/admin-assets/' . $this->id . '/' . $this->filename;
    }

    public function getSizeHumanAttribute(): string
    {
        $bytes = (int) $this->size_bytes;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 2) . ' GB';
    }

    public function deleteFile(): bool
    {
        try {
            Storage::disk($this->disk)->delete($this->path);
        } catch (\Throwable $e) {
            // Storage may already be gone — still drop the row.
        }
        return $this->delete();
    }

    /**
     * If the upload is a raster image (jpeg/png/webp), resize it to fit within
     * a max width and re-encode at a sensible quality. Returns a path to a
     * temp file with the processed bytes, or null if no processing happened.
     * Falls back to null on any failure (caller stores the original).
     */
    protected static function maybeCompressImage(UploadedFile $file, string $type, string $mime, array $options): ?string
    {
        if ($type !== 'image') return null;
        if (($options['compress'] ?? true) === false) return null;
        if (!function_exists('imagecreatefromstring')) return null;

        $mime = strtolower($mime);
        if (!in_array($mime, ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'], true)) {
            return null;
        }

        $maxWidth  = (int) ($options['resize_max_width']  ?? 1200);
        $maxHeight = (int) ($options['resize_max_height'] ?? 1200);
        $quality   = (int) ($options['resize_quality']    ?? 82);
        if ($maxWidth <= 0 || $maxHeight <= 0) return null;

        $sourcePath = $file->getRealPath();
        if (!$sourcePath || !is_readable($sourcePath)) return null;
        $originalSize = (int) (@filesize($sourcePath) ?: 0);

        try {
            $data = @file_get_contents($sourcePath);
            if ($data === false) return null;
            $img = @imagecreatefromstring($data);
            unset($data);
            if ($img === false) return null;

            // Honor EXIF orientation so phone photos don't end up sideways
            // after re-encoding. Only JPEGs carry meaningful orientation.
            if (($mime === 'image/jpeg' || $mime === 'image/jpg') && function_exists('exif_read_data')) {
                $exif = @exif_read_data($sourcePath);
                $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 0) : 0;
                if ($orientation > 1 && $orientation <= 8) {
                    switch ($orientation) {
                        case 2: imageflip($img, IMG_FLIP_HORIZONTAL); break;
                        case 3: $img = imagerotate($img, 180, 0); break;
                        case 4: imageflip($img, IMG_FLIP_VERTICAL); break;
                        case 5: imageflip($img, IMG_FLIP_VERTICAL); $img = imagerotate($img, -90, 0); break;
                        case 6: $img = imagerotate($img, -90, 0); break;
                        case 7: imageflip($img, IMG_FLIP_HORIZONTAL); $img = imagerotate($img, -90, 0); break;
                        case 8: $img = imagerotate($img, 90, 0); break;
                    }
                }
            }

            $w = imagesx($img);
            $h = imagesy($img);
            if ($w <= 0 || $h <= 0) {
                imagedestroy($img);
                return null;
            }

            $scale = min(1.0, $maxWidth / $w, $maxHeight / $h);
            $newW = max(1, (int) floor($w * $scale));
            $newH = max(1, (int) floor($h * $scale));

            if ($scale < 1.0) {
                $resized = imagecreatetruecolor($newW, $newH);
                if ($mime === 'image/png' || $mime === 'image/webp') {
                    imagealphablending($resized, false);
                    imagesavealpha($resized, true);
                    $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                    imagefilledrectangle($resized, 0, 0, $newW, $newH, $transparent);
                }
                imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
                imagedestroy($img);
                $img = $resized;
            }

            $tmp = tempnam(sys_get_temp_dir(), 'adimg_');
            if ($tmp === false) {
                imagedestroy($img);
                return null;
            }

            $ok = false;
            switch ($mime) {
                case 'image/jpeg':
                case 'image/jpg':
                    $ok = imagejpeg($img, $tmp, max(1, min(100, $quality)));
                    break;
                case 'image/png':
                    imagealphablending($img, false);
                    imagesavealpha($img, true);
                    $pngLevel = (int) max(0, min(9, round((100 - $quality) / 11)));
                    $ok = imagepng($img, $tmp, $pngLevel);
                    break;
                case 'image/webp':
                    if (function_exists('imagewebp')) {
                        $ok = imagewebp($img, $tmp, max(1, min(100, $quality)));
                    }
                    break;
            }
            imagedestroy($img);

            if (!$ok) {
                @unlink($tmp);
                return null;
            }

            $newSize = (int) (@filesize($tmp) ?: 0);
            // Only keep the processed version if it's actually smaller or we
            // resized. Re-encoded files that grew are discarded.
            if ($scale >= 1.0 && $originalSize > 0 && $newSize >= $originalSize) {
                @unlink($tmp);
                return null;
            }

            return $tmp;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function createFromUpload(UploadedFile $file, $admin = null, array $options = []): self
    {
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $ext  = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $size = (int) $file->getSize();

        $maxMb = (int) ($options['max_size_mb'] ?? config('admin_assets.max_size_mb', 256));
        if ($maxMb > 0 && $size > $maxMb * 1048576) {
            throw new RuntimeException("File exceeds the maximum size of {$maxMb} MB.");
        }

        $type = self::detectType($mime);
        $disk = self::diskName();
        $folder = trim((string) ($options['folder'] ?? ''), '/');
        $folderSegment = $folder !== '' ? Str::slug($folder, '-') : '';
        $base = 'admin-assets/' . $type . 's' . ($folderSegment !== '' ? '/' . $folderSegment : '');
        $filename = (string) Str::uuid() . '.' . $ext;

        $processed = self::maybeCompressImage($file, $type, $mime, $options);
        $width = null;
        $height = null;
        if ($processed !== null) {
            if ($type === 'image') {
                [$width, $height] = self::probeImageDimensions($processed, $ext);
            }
            $stream = fopen($processed, 'rb');
            Storage::disk($disk)->put($base . '/' . $filename, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            $size = (int) (@filesize($processed) ?: $size);
            @unlink($processed);
            $storedPath = $base . '/' . $filename;
        } else {
            if ($type === 'image' && ($src = $file->getRealPath())) {
                [$width, $height] = self::probeImageDimensions($src, $ext);
            }
            $storedPath = $file->storeAs($base, $filename, $disk);
        }

        return self::create([
            'admin_id'      => $admin?->id,
            'original_name' => $file->getClientOriginalName(),
            'filename'      => $filename,
            'mime_type'     => $mime,
            'size_bytes'    => $size,
            'type'          => $type,
            'width'         => $width,
            'height'        => $height,
            'disk'          => $disk,
            'path'          => $storedPath,
            'folder'        => $folderSegment ?: null,
            'label'         => $options['label']       ?? null,
            'description'   => $options['description'] ?? null,
            'is_public'     => (bool) ($options['is_public'] ?? false),
        ]);
    }
}
