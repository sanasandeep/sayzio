<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\ClientPortal;
use App\Modules\User\Models\ClientPortalAction;
use App\Modules\User\Models\ClientPortalLink;
use App\Modules\User\Models\ClientPortalShare;
use App\Modules\User\Models\CloudFile;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\TaskBoard;
use App\Modules\User\Models\VaultClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ClientPortalController extends Controller
{
    public function index()
    {
        $portals = ClientPortal::query()
            ->with(['vaultClient', 'shares'])
            ->withCount(['shares', 'links', 'actions'])
            ->orderByDesc('id')
            ->get();

        return view('user.client-portals.index', compact('portals'));
    }

    public function create()
    {
        $clients = VaultClient::query()->orderBy('name')->get(['id', 'name', 'company', 'primary_email']);
        return view('user.client-portals.create', compact('clients'));
    }

    public function store(Request $request)
    {
        $data = $this->validatePortal($request);

        $portal = ClientPortal::create([
            'workspace_id'        => app('current_workspace')->id,
            'vault_client_id'     => $data['vault_client_id'] ?? null,
            'created_by_user_id'  => auth()->id(),
            'name'                => $data['name'],
            'brand_name'          => $data['brand_name'] ?? null,
            'brand_color'         => $data['brand_color'] ?? null,
            'brand_logo_url'      => $data['brand_logo_url'] ?? null,
            'welcome_message'     => $data['welcome_message'] ?? null,
            'is_enabled'          => true,
        ]);

        return redirect()->route('user.client-portals.edit', $portal)
            ->with('success', 'Portal created. Add shares and send a magic link.');
    }

    public function edit(ClientPortal $clientPortal)
    {
        $portal = $clientPortal;
        $portal->load(['shares', 'links']);
        $clients = VaultClient::query()->orderBy('name')->get(['id', 'name', 'company']);

        $boards = TaskBoard::query()->whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'scope']);
        $invoices = Invoice::query()
            ->where('user_id', $portal->workspace->owner_user_id)
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'number', 'currency', 'grand_total_minor', 'status', 'issued_at']);
        $drafts = CreatorPost::query()
            ->whereNull('published_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'title', 'body', 'scheduled_at']);
        $cloudFolders = CloudFile::query()
            ->select('provider', 'parent_folder_path', DB::raw('count(*) as files_count'))
            ->whereNotNull('parent_folder_path')
            ->groupBy('provider', 'parent_folder_path')
            ->orderBy('provider')->orderBy('parent_folder_path')
            ->get();
        $links = Link::query()->orderByDesc('id')->limit(50)->get(['id', 'title', 'slug']);

        $recentActions = $portal->actions()->limit(50)->get();

        return view('user.client-portals.edit', compact(
            'portal', 'clients', 'boards', 'invoices', 'drafts', 'cloudFolders', 'links', 'recentActions'
        ));
    }

    public function update(Request $request, ClientPortal $clientPortal)
    {
        $data = $this->validatePortal($request);
        $clientPortal->update([
            'vault_client_id'  => $data['vault_client_id'] ?? null,
            'name'             => $data['name'],
            'brand_name'       => $data['brand_name'] ?? null,
            'brand_color'      => $data['brand_color'] ?? null,
            'brand_logo_url'   => $data['brand_logo_url'] ?? null,
            'welcome_message'  => $data['welcome_message'] ?? null,
            // Use has() instead of boolean(..., true) — an unchecked checkbox
            // omits the field entirely, and boolean()'s default would lock the
            // toggle to "on" forever, making the portal impossible to disable.
            'is_enabled'       => $request->has('is_enabled'),
        ]);
        return back()->with('success', 'Portal updated.');
    }

    public function destroy(ClientPortal $clientPortal)
    {
        DB::transaction(function () use ($clientPortal) {
            $clientPortal->shares()->delete();
            $clientPortal->links()->delete();
            $clientPortal->actions()->delete();
            $clientPortal->delete();
        });
        return redirect()->route('user.client-portals.index')->with('success', 'Portal deleted.');
    }

    /* ─── Shares ─────────────────────────────────────────────── */

    public function storeShare(Request $request, ClientPortal $clientPortal)
    {
        $data = $request->validate([
            'shareable_type'    => 'required|in:' . implode(',', array_keys(ClientPortalShare::TYPES)),
            'shareable_id'      => 'nullable|integer',
            'label'             => 'nullable|string|max:160',
            'requires_approval' => 'nullable|boolean',
            'settings'          => 'nullable|array',
        ]);

        ClientPortalShare::create([
            'portal_id'         => $clientPortal->id,
            'workspace_id'      => $clientPortal->workspace_id,
            'shareable_type'    => $data['shareable_type'],
            'shareable_id'      => $data['shareable_id'] ?? null,
            'label'             => $data['label'] ?? null,
            'settings'          => $data['settings'] ?? null,
            'position'          => (int) ($clientPortal->shares()->max('position') ?? 0) + 1,
            'requires_approval' => (bool) ($data['requires_approval'] ?? false),
            'approval_status'   => !empty($data['requires_approval']) ? 'pending' : null,
        ]);

        return back()->with('success', 'Share added.');
    }

    public function destroyShare(ClientPortal $clientPortal, ClientPortalShare $share)
    {
        abort_unless($share->portal_id === $clientPortal->id, 404);
        $share->delete();
        return back()->with('success', 'Share removed.');
    }

    /* ─── Magic links ─────────────────────────────────────────── */

    public function sendLink(Request $request, ClientPortal $clientPortal)
    {
        $data = $request->validate([
            'email'      => 'required|email',
            'expires_in' => 'nullable|integer|min:1|max:365',
        ]);
        $expiresIn = (int) ($data['expires_in'] ?? 30);

        $link = ClientPortalLink::create([
            'portal_id'    => $clientPortal->id,
            'workspace_id' => $clientPortal->workspace_id,
            'email'        => $data['email'],
            'token'        => ClientPortalLink::newToken(),
            'expires_at'   => now()->addDays($expiresIn),
            'sent_at'      => now(),
        ]);

        $url = route('portal.start', ['token' => $link->token]);
        try {
            Mail::raw(
                "You've been given access to the {$clientPortal->brandingName()} client portal.\n\n"
                . "Open: {$url}\n\nThis link expires in {$expiresIn} day(s).",
                fn ($m) => $m->to($data['email'])->subject($clientPortal->brandingName() . ' — your portal access')
            );
        } catch (\Throwable $e) {
            // Best-effort — link is still usable from the owner UI.
        }

        return back()->with('success', 'Magic link sent to ' . $data['email'] . '.')
            ->with('portal_link_url', $url);
    }

    public function revokeLink(ClientPortal $clientPortal, ClientPortalLink $link)
    {
        abort_unless($link->portal_id === $clientPortal->id, 404);
        $link->update(['revoked_at' => now()]);
        return back()->with('success', 'Link revoked.');
    }

    public function rotateLink(ClientPortal $clientPortal, ClientPortalLink $link)
    {
        abort_unless($link->portal_id === $clientPortal->id, 404);
        $link->update([
            'token'      => ClientPortalLink::newToken(),
            'expires_at' => now()->addDays(30),
            'revoked_at' => null,
        ]);
        return back()->with('success', 'Link rotated. New URL: ' . route('portal.start', ['token' => $link->token]));
    }

    /* ─── Validation helper ─────────────────────────────────── */

    private function validatePortal(Request $request): array
    {
        return $request->validate([
            'name'             => 'required|string|max:160',
            'vault_client_id'  => 'nullable|integer|exists:vault_clients,id',
            'brand_name'       => 'nullable|string|max:160',
            'brand_color'      => 'nullable|string|max:16',
            'brand_logo_url'   => 'nullable|url|max:1024',
            'welcome_message'  => 'nullable|string|max:2000',
        ]);
    }
}
