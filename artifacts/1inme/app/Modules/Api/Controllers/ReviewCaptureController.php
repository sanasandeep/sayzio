<?php

namespace App\Modules\Api\Controllers;

use App\Modules\User\Models\ReviewProvider;
use App\Services\ReviewProviders\ReviewProviderRegistry;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Extension-initiated review-source capture.
 *
 * The browser extension detects when the user is on a Google Maps or
 * Trustpilot business page and offers a one-click "Capture reviews for
 * this business" action. This endpoint registers (or re-activates) the
 * review_providers row and queues a single-connection sync, reusing all
 * existing adapter logic (preview mode when platform keys are absent,
 * plan checks inside the adapter, etc.).
 */
class ReviewCaptureController extends Controller
{
    private const ALLOWED_PROVIDERS = ['google', 'trustpilot'];

    public function capture(Request $request)
    {
        $data = $request->validate([
            'provider'     => ['required', 'string', 'in:google,trustpilot'],
            'external_ref' => ['required', 'string', 'max:500'],
            'name'         => ['nullable', 'string', 'max:255'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        // Honest preview signal: the capture is only "connected" when the
        // provider is registered AND its platform API keys are actually
        // configured (adapter-level check). Otherwise the queued sync would
        // downgrade the row to preview shortly after, so reflect that
        // immediately instead of briefly showing a misleading "connected".
        $isPreview = ! ReviewProviderRegistry::exists($data['provider'])
            || ! ReviewProviderRegistry::adapter($data['provider'])->credentialsConfigured();

        $connection = ReviewProvider::updateOrCreate(
            [
                'user_id'      => $user->id,
                'provider'     => $data['provider'],
                'external_ref' => $data['external_ref'],
            ],
            [
                'status'   => $isPreview
                    ? ReviewProvider::STATUS_PREVIEW
                    : ReviewProvider::STATUS_CONNECTED,
                'settings' => array_filter([
                    'name' => $data['name'] ?? null,
                ]),
            ],
        );

        // Queue a sync for this specific connection so it runs
        // asynchronously after the response is returned to the user.
        // Falls through to preview-mode logic if platform API keys are
        // absent — identical to the scheduled reviews:sync behaviour.
        if (ReviewProviderRegistry::exists($data['provider'])) {
            $connectionId = $connection->id;
            dispatch(static function () use ($connectionId): void {
                $conn = ReviewProvider::find($connectionId);
                if (! $conn) {
                    return;
                }
                if (! ReviewProviderRegistry::exists($conn->provider)) {
                    return;
                }
                ReviewProviderRegistry::adapter($conn->provider)->sync($conn);
            })->afterResponse();
        }

        return response()->json([
            'data' => [
                'connection_id' => $connection->id,
                'provider'      => $connection->provider,
                'status'        => $connection->status,
                'preview'       => $isPreview,
            ],
        ]);
    }
}
