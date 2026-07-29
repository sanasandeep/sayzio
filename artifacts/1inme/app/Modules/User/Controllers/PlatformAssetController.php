<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Support\PlatformAssetCatalog;
use Illuminate\Routing\Controller;

/**
 * Web AJAX endpoint for the platform-provided asset galleries
 * (backgrounds, stock images, avatars). Available on every plan — the
 * assets are platform-owned and public, so there is deliberately no
 * plan/feature gating here.
 */
class PlatformAssetController extends Controller
{
    public function index(string $folder)
    {
        if (!PlatformAssetCatalog::isFolder($folder)) {
            return response()->json([
                'success' => false,
                'error'   => 'Unknown asset folder.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'folder'  => $folder,
            'assets'  => PlatformAssetCatalog::list($folder),
        ]);
    }
}
