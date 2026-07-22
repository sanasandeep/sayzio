<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\AssetTransferService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Web endpoints for the admin-granted "transfer to another user" actions.
 * All authorization (grant check, ownership, self-transfer) is enforced
 * server-side in AssetTransferService — the blade visibility checks are
 * cosmetic only.
 */
class AssetTransferController extends Controller
{
    public function __construct(protected AssetTransferService $transfers)
    {
    }

    public function transferLink(Request $request, Link $link)
    {
        $data = $request->validate([
            'recipient_email' => 'required|email',
        ]);

        $sender    = $request->user();
        $recipient = $this->findRecipient($data['recipient_email']);
        if (!$recipient) {
            return back()->with('error', 'No account exists with that email. The recipient must already have a Sayzio account.');
        }

        try {
            $this->transfers->transferLink($link, $sender, $recipient, 'web');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('user.links.index')
            ->with('success', 'Link transferred to ' . ($recipient->name ?: $recipient->email) . '.');
    }

    public function transferWorkspace(Request $request, Workspace $workspace)
    {
        $data = $request->validate([
            'recipient_email' => 'required|email',
        ]);

        $sender    = $request->user();
        $recipient = $this->findRecipient($data['recipient_email']);
        if (!$recipient) {
            return back()->with('error', 'No account exists with that email. The recipient must already have a Sayzio account.');
        }

        try {
            $this->transfers->transferWorkspace($workspace, $sender, $recipient, 'web');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('user.dashboard')
            ->with('success', 'Workspace transferred to ' . ($recipient->name ?: $recipient->email) . '.');
    }

    protected function findRecipient(string $email): ?User
    {
        return User::query()
            ->whereRaw('lower(email) = ?', [strtolower(trim($email))])
            ->first();
    }
}
