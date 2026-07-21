<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\AssetTransfer;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\AssetTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * REST parity for the admin-granted asset-transfer feature:
 *   GET  /me/transfer-capability          — can the caller transfer?
 *   POST /links/{id}/transfer             — transfer an owned link
 *   POST /workspaces/{id}/transfer        — transfer an owned workspace
 *
 * All authorization is enforced in AssetTransferService; controllers only
 * translate outcomes into the unified {data}/{error} envelope.
 */
class AssetTransferController extends Controller
{
    use ApiResponses;

    public function __construct(protected AssetTransferService $transfers)
    {
    }

    public function capability(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->ok([
            'can_transfer' => $user->canTransferAssets(),
            'granted_at'   => optional($user->transfer_capability_granted_at)?->toIso8601String(),
        ]);
    }

    public function transferLink(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['recipient_email' => 'required|email']);

        $link = Link::find($id);
        if (!$link) {
            return $this->notFound('Link not found');
        }

        $sender    = $request->user();
        $recipient = $this->findRecipient($data['recipient_email']);
        if (!$recipient) {
            return $this->fail('No account exists with that email. The recipient must already have a Sayzio account.', 422, 'recipient_not_found');
        }

        try {
            $transfer = $this->transfers->transferLink($link, $sender, $recipient, 'api');
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422, 'invalid_transfer');
        } catch (\RuntimeException $e) {
            return $this->forbidden($e->getMessage());
        }

        return $this->ok($this->present($transfer));
    }

    public function transferWorkspace(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['recipient_email' => 'required|email']);

        $workspace = Workspace::find($id);
        if (!$workspace) {
            return $this->notFound('Workspace not found');
        }

        $sender    = $request->user();
        $recipient = $this->findRecipient($data['recipient_email']);
        if (!$recipient) {
            return $this->fail('No account exists with that email. The recipient must already have a Sayzio account.', 422, 'recipient_not_found');
        }

        try {
            $transfer = $this->transfers->transferWorkspace($workspace, $sender, $recipient, 'api');
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422, 'invalid_transfer');
        } catch (\RuntimeException $e) {
            return $this->forbidden($e->getMessage());
        }

        return $this->ok($this->present($transfer));
    }

    protected function present(AssetTransfer $t): array
    {
        return [
            'id'          => $t->id,
            'kind'        => $t->kind,
            'asset_id'    => $t->asset_id,
            'asset_label' => $t->asset_label,
            'to_email'    => $t->to_email,
            'created_at'  => optional($t->created_at)?->toIso8601String(),
        ];
    }

    protected function findRecipient(string $email): ?User
    {
        return User::query()
            ->whereRaw('lower(email) = ?', [strtolower(trim($email))])
            ->first();
    }
}
