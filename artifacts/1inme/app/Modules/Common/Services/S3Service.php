<?php

namespace App\Modules\Common\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class S3Service
{
    public function upload(UploadedFile $file, string $directory = 'uploads', ?string $filename = null): string
    {
        $filename = $filename ?? Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $directory . '/' . $filename;

        Storage::disk('s3')->put($path, file_get_contents($file), 'public');

        return $path;
    }

    public function delete(string $path): bool
    {
        return Storage::disk('s3')->delete($path);
    }

    public function url(string $path): string
    {
        return Storage::disk('s3')->url($path);
    }

    public function exists(string $path): bool
    {
        return Storage::disk('s3')->exists($path);
    }

    public function uploadFromContent(string $content, string $path, string $visibility = 'public'): string
    {
        Storage::disk('s3')->put($path, $content, $visibility);
        return $path;
    }
}
