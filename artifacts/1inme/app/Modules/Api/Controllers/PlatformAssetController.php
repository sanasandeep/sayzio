<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Support\PlatformAssetCatalog;
use Illuminate\Routing\Controller;

/**
 * REST catalog of platform-provided image assets (mobile parity for the
 * web galleries: biolink backgrounds, stock images, avatar galleries).
 * No plan gating — assets are platform-owned and available to everyone.
 */
class PlatformAssetController extends Controller
{
    use ApiResponses;

    public function index(string $folder)
    {
        if (!PlatformAssetCatalog::isFolder($folder)) {
            return $this->notFound('Unknown asset folder');
        }

        return $this->ok([
            'folder' => $folder,
            'assets' => PlatformAssetCatalog::list($folder),
        ]);
    }
}
