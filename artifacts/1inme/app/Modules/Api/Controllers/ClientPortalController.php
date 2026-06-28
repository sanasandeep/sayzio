<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Services\Billing\CompanyMailSettings;
use App\Modules\User\Models\ClientPortal;
use App\Modules\User\Models\ClientPortalLink;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Mobile API surface for the Client Portals feature. Mirrors the web
 * ClientPortalController but returns JSON. The active workspace is resolved
 * per-request from the signed-in user (their accessible workspaces) and
 * bound as `current_workspace` so the ClientPortal model's BelongsToWorkspace
 * scope filters automatically.
 */
class ClientPortalController extends Controller
{
    use ApiResponses;

    public function __construct(protected WorkspaceContext $ctx) {}

    /** Resolve + bind the active workspace for this request. */
    protected function workspace(Request $request): ?Workspace
    {
        $ws = $this->ctx->resolve($request->user());
        if (!$ws) return null;
        app()->instance('current_workspace', $ws);
        return $ws;
    }

    public function index(Request $request)
    {
        $ws = $this->workspace($request);
        if (!$ws) return $this->forbidden('No workspace');

        $items = ClientPortal::query()
            ->with('vaultClient:id,name')
            ->withCount(['shares', 'links', 'actions'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (ClientPortal $p) => $this->transform($p))
            ->all();

        return $this->ok(['items' => $items]);
    }

    public function show(Request $request, int $id)
    {
        $ws = $this->workspace($request);
        if (!$ws) return $this->forbidden('No workspace');

        $portal = ClientPortal::with('vaultClient:id,name')
            ->withCount(['shares', 'links', 'actions'])
            ->find($id);
        if (!$portal) return $this->notFound('Portal not found');

        $shares = $portal->shares()->get()->map(fn ($s) => [
            'id'             => $s->id,
            'shareable_type' => $s->shareable_type,
            'shareable_id'   => $s->shareable_id,
            'label'          => $s->label,
            'type_label'     => $s->typeLabel(),
            'position'       => (int) $s->position,
        ])->all();

        $links = $portal->links()->get()->map(fn (ClientPortalLink $l) => [
            'id'           => $l->id,
            'email'        => $l->email,
            'status'       => $l->statusLabel(),
            'expires_at'   => optional($l->expires_at)->toIso8601String(),
            'sent_at'      => optional($l->sent_at)->toIso8601String(),
            'last_used_at' => optional($l->last_used_at)->toIso8601String(),
        ])->all();

        return $this->ok([
            'portal' => array_merge($this->transform($portal), [
                'welcome_message' => $portal->welcome_message,
                'brand_logo_url'  => $portal->brand_logo_url,
                'shares'          => $shares,
                'links'           => $links,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $ws = $this->workspace($request);
        if (!$ws) return $this->forbidden('No workspace');

        $data = $request->validate([
            'name'             => ['required', 'string', 'max:160'],
            'vault_client_id'  => ['nullable', 'integer'],
            'brand_name'       => ['nullable', 'string', 'max:160'],
            'brand_color'      => ['nullable', 'string', 'max:16'],
            'welcome_message'  => ['nullable', 'string', 'max:2000'],
        ]);

        $portal = ClientPortal::create([
            'workspace_id'       => $ws->id,
            'created_by_user_id' => $request->user()->id,
            'name'               => $data['name'],
            'vault_client_id'    => $data['vault_client_id'] ?? null,
            'brand_name'         => $data['brand_name'] ?? null,
            'brand_color'        => $data['brand_color'] ?? null,
            'welcome_message'    => $data['welcome_message'] ?? null,
            'is_enabled'         => true,
        ]);

        $portal->loadCount(['shares', 'links', 'actions']);
        return $this->created(['portal' => $this->transform($portal)]);
    }

    public function update(Request $request, int $id)
    {
        $ws = $this->workspace($request);
        if (!$ws) return $this->forbidden('No workspace');

        $portal = ClientPortal::find($id);
        if (!$portal) return $this->notFound('Portal not found');

        $data = $request->validate([
            'name'             => ['sometimes', 'string', 'max:160'],
            'vault_client_id'  => ['sometimes', 'nullable', 'integer'],
            'brand_name'       => ['sometimes', 'nullable', 'string', 'max:160'],
            'brand_color'      => ['sometimes', 'nullable', 'string', 'max:16'],
            'welcome_message'  => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_enabled'       => ['sometimes', 'boolean'],
        ]);

        $portal->update($data);
        $portal->loadCount(['shares', 'links', 'actions']);
        return $this->ok(['portal' => $this->transform($portal)]);
    }

    public function destroy(Request $request, int $id)
    {
        $ws = $this->workspace($request);
        if (!$ws) return $this->forbidden('No workspace');

        $portal = ClientPortal::find($id);
        if (!$portal) return $this->notFound('Portal not found');

        DB::transaction(function () use ($portal) {
            $portal->shares()->delete();
            $portal->links()->delete();
            $portal->actions()->delete();
            $portal->delete();
        });

        return $this->noContent();
    }

    /**
     * Issue a magic-link to give a teammate / client access to the portal.
     * Mirrors the web ClientPortalController::sendLink — best-effort email,
     * URL is also returned so the mobile UI can copy/share it directly.
     */
    public function sendLink(Request $request, int $id)
    {
        $ws = $this->workspace($request);
        if (!$ws) return $this->forbidden('No workspace');

        $portal = ClientPortal::find($id);
        if (!$portal) return $this->notFound('Portal not found');

        $data = $request->validate([
            'email'      => ['required', 'email'],
            'expires_in' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);
        $expiresIn = (int) ($data['expires_in'] ?? 30);

        $link = ClientPortalLink::create([
            'portal_id'    => $portal->id,
            'workspace_id' => $portal->workspace_id,
            'email'        => $data['email'],
            'token'        => ClientPortalLink::newToken(),
            'expires_at'   => now()->addDays($expiresIn),
            'sent_at'      => now(),
        ]);

        $url     = route('portal.start', ['token' => $link->token]);
        $subject = $portal->brandingName() . ' — your portal access';
        $body    = "You've been given access to the {$portal->brandingName()} client portal.\n\n"
            . "Open: {$url}\n\nThis link expires in {$expiresIn} day(s).";
        try {
            // Client-facing send: route through the workspace's default billing
            // company SMTP when configured, otherwise the platform mailer.
            $companyMail = CompanyMailSettings::forWorkspaceDefault($portal->workspace_id);
            if ($companyMail) {
                $companyMail->sendRaw($data['email'], $subject, $body);
            } else {
                Mail::raw($body, fn ($m) => $m->to($data['email'])->subject($subject));
            }
        } catch (\Throwable $e) {
            // best effort
        }

        return $this->created(['link' => [
            'id'         => $link->id,
            'email'      => $link->email,
            'url'        => $url,
            'expires_at' => optional($link->expires_at)->toIso8601String(),
        ]]);
    }

    protected function transform(ClientPortal $p): array
    {
        return [
            'id'             => $p->id,
            'name'           => $p->name,
            'brand_name'     => $p->brand_name,
            'brand_color'    => $p->brand_color,
            'is_enabled'     => (bool) $p->is_enabled,
            'client_name'    => optional($p->vaultClient)->name,
            'shares_count'   => (int) ($p->shares_count ?? 0),
            'links_count'    => (int) ($p->links_count ?? 0),
            'actions_count'  => (int) ($p->actions_count ?? 0),
            'last_seen_at'   => optional($p->last_seen_at)->toIso8601String(),
            'created_at'     => optional($p->created_at)->toIso8601String(),
        ];
    }
}
