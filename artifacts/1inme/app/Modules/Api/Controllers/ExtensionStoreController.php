<?php

namespace App\Modules\Api\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Support\ExtensionStoreLinks;

/**
 * Public browser-extension store links — the mobile Browser extension info
 * page reads these so it shares the same source of truth (ExtensionStoreLinks
 * over app_settings) as the web card on Settings → Connected Accounts & Apps.
 */
class ExtensionStoreController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => [
                'stores' => ExtensionStoreLinks::stores(),
            ],
        ]);
    }
}
