<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use App\Services\Brand\BrandIconPackage;
use Illuminate\Http\Request;
use ZipArchive;

class BrandIconController extends Controller
{
    public function generate(Request $request, BrandKit $brandKit)
    {
        abort_unless((int) $brandKit->user_id === (int) workspace_owner_id(), 403);
        $rules = ['fingerprint' => 'required|string'];
        foreach (['main', 'dark', 'apple', 'android', 'maskable'] as $k) {
            $rules[$k] = 'required|file|mimes:png|max:2048|dimensions:width=512,height=512';
        }
        $data = $request->validate($rules);
        abort_unless(hash_equals(BrandIconPackage::fingerprint($brandKit), $data['fingerprint']), 422, 'Save changes and reload before generating icons.');
        $ids = [];
        foreach (['main', 'dark', 'apple', 'android', 'maskable'] as $k) {
            $file = UserFile::createFromUpload($request->file($k), User::findOrFail(workspace_owner_id()));
            abort_unless($file->fresh()->isScanClean() && ! $file->fresh()->quarantined_at, 422, 'An icon did not pass upload checks. Review it in My Files before trying again.');
            $ids[$k] = $file->id;
        }
        $c = $brandKit->config ?? [];
        $c['icon_package_files'] = $ids;
        $c['icon_package_fingerprint'] = $data['fingerprint'];
        $brandKit->update(['config' => $c]);

        return response()->json(['message' => 'Icon package generated. Download it separately or in the complete Brand Kit ZIP.']);
    }

    public function download(BrandKit $brandKit, BrandIconPackage $icons)
    {
        abort_unless((int) $brandKit->user_id === (int) workspace_owner_id(), 403);
        abort_unless(($brandKit->config['icon_package_fingerprint'] ?? '') === BrandIconPackage::fingerprint($brandKit), 422, 'Generate icons from the saved kit first.');
        $path = tempnam(sys_get_temp_dir(), 'brand-icons-');
        $zip = new ZipArchive;
        abort_unless($zip->open($path, ZipArchive::OVERWRITE) === true, 503);
        try {
            foreach ($icons->files($brandKit) as $name => $bytes) {
                $zip->addFromString($name, $bytes);
            }$zip->close();
        } catch (\Throwable $e) {
            $zip->close();
            @unlink($path);
            throw $e;
        }

        return response()->download($path, 'brand-icons.zip')->deleteFileAfterSend(true);
    }
}
