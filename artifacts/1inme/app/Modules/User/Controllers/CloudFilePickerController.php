<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\CloudConnection;
use App\Modules\User\Models\CloudProviderApp;
use App\Modules\User\Services\CloudFiles\CloudProviderRegistry;
use Illuminate\Http\Request;

class CloudFilePickerController extends Controller
{
    public function __construct(protected CloudProviderRegistry $registry) {}

    public function browse(Request $request, CloudConnection $connection)
    {
        abort_unless($connection->user_id === $request->user()->id, 403);

        $app = CloudProviderApp::where('provider', $connection->provider)->first();
        if (!$app || !$app->isConfigured()) {
            return response()->json(['error' => 'app_not_configured'], 422);
        }

        $connection = $this->registry->refreshIfExpiring($connection, $app);
        if ($connection->isBroken()) {
            return response()->json(['error' => 'reconnect_required', 'message' => $connection->last_error], 422);
        }

        try {
            $listing = $this->registry->get($connection->provider)->listFolder(
                $connection,
                $request->query('folder') ?: null,
                $request->query('cursor') ?: null,
            );
        } catch (\RuntimeException $e) {
            $connection->update(['last_error' => substr($e->getMessage(), 0, 240)]);
            return response()->json(['error' => 'list_failed', 'message' => $e->getMessage()], 422);
        }

        return response()->json($listing);
    }
}
