<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Controllers\CloudFileAttachmentController;
use App\Modules\User\Models\CloudConnection;
use App\Modules\User\Models\CloudFile;
use App\Modules\User\Models\CloudFileAttachment;
use App\Modules\User\Models\CloudProviderApp;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\InboxReply;
use App\Modules\User\Models\TaskCard;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\CloudFiles\CloudProviderRegistry;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Mobile + REST parity for the Cloud File Library (Google Drive / Dropbox /
 * OneDrive). Mirrors the web controllers (CloudOAuthController,
 * CloudConnectionController, CloudFileController, CloudFilePickerController,
 * CloudFileAttachmentController) under the standard `{data} / {error}` API
 * envelope.
 *
 * Workspace permissions are honoured exactly like the web `workspace.can:*`
 * middleware (RequireWorkspacePermission): owner bypass, the
 * `user.workspaces.access_any` super-permission bypass, otherwise the member's
 * role permission for files.view / files.create / files.delete.
 *
 * OAuth on mobile is stateless (no web session). {@see connect} mints an
 * APP_KEY-encrypted state token carrying the provider + user + workspace +
 * return deep-link, and returns the provider authorize URL for the app to open
 * in an in-app browser. The provider redirects to the PUBLIC, unauthenticated
 * {@see oauthCallback} (registered outside the auth:sanctum group), which
 * decrypts the token, exchanges the code and bounces back to the app's deep
 * link with `?status=connected` or `?error=…`.
 *
 * NOTE for operators: the mobile flow uses its own redirect URI
 * (`/api/v1/cloud-files/oauth/callback`). The workspace owner must whitelist
 * that URI in each provider's OAuth console in addition to the web callback —
 * the same external onboarding constraint the web flow already has. This
 * controller deliberately ignores the per-app stored `redirect_uri` so the
 * authorize + token-exchange requests always use the same mobile URI.
 */
class CloudFilesApiController extends Controller
{
    use ApiResponses;

    /** Polymorphic attach targets; map UI keys to fully-qualified models. */
    protected const TARGETS = [
        'post'        => CreatorPost::class,
        'task_card'   => TaskCard::class,
        'inbox_reply' => InboxReply::class,
    ];

    /** Deep links the mobile app may ask us to bounce back to after OAuth. */
    protected const MOBILE_RETURN_ALLOWLIST = ['sayzio://cloud-oauth'];

    /** Minutes the encrypted OAuth state token stays valid. */
    protected const STATE_TTL_MINUTES = 15;

    public function __construct(
        protected WorkspaceContext $ctx,
        protected CloudProviderRegistry $registry,
    ) {}

    // ── Connections ────────────────────────────────────────────────

    /**
     * List the caller's cloud connections in the active workspace plus the
     * provider catalog (with per-provider "configured" status so the app can
     * disable connect buttons for providers the owner hasn't set up).
     */
    public function connections(Request $request): JsonResponse
    {
        [$ws, $err] = $this->gate($request, 'files.view');
        if ($err) return $err;

        $connections = CloudConnection::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('provider')
            ->get();

        $apps = CloudProviderApp::query()->get()->keyBy('provider');

        $providers = collect(CloudProviderApp::PROVIDERS)->map(fn (string $p) => [
            'provider'   => $p,
            'label'      => CloudProviderApp::PROVIDER_LABELS[$p] ?? $p,
            'icon'       => CloudProviderApp::PROVIDER_ICONS[$p] ?? 'fa-cloud',
            'configured' => (bool) ($apps[$p]?->isConfigured() ?? false),
        ])->all();

        return $this->ok([
            'providers'   => $providers,
            'connections' => $connections->map(fn (CloudConnection $c) => $this->serializeConnection($c))->all(),
        ]);
    }

    /**
     * Begin the OAuth connect flow. Returns the provider authorize URL for the
     * app to open in an in-app browser. State is a self-contained, encrypted,
     * short-lived token — no server session is used.
     */
    public function connect(Request $request, string $provider): JsonResponse
    {
        [$ws, $err] = $this->gate($request, 'files.view');
        if ($err) return $err;

        if (!CloudProviderApp::isKnownProvider($provider)) {
            return $this->notFound('Unknown cloud provider.');
        }

        $return = (string) $request->input('return', self::MOBILE_RETURN_ALLOWLIST[0]);
        if (!in_array($return, self::MOBILE_RETURN_ALLOWLIST, true)) {
            return $this->fail('Unsupported return URL.', 422, 'invalid_return');
        }

        $app = CloudProviderApp::where('provider', $provider)->first();
        if (!$app || !$app->isConfigured()) {
            return $this->fail(
                (CloudProviderApp::PROVIDER_LABELS[$provider] ?? $provider)
                    . ' is not configured for this workspace yet. Ask the workspace owner to add OAuth credentials.',
                422,
                'provider_not_configured',
            );
        }

        $state = Crypt::encryptString(json_encode([
            'p'   => $provider,
            'u'   => (int) $request->user()->id,
            'w'   => (int) $ws->id,
            'r'   => $return,
            'n'   => Str::random(16),
            'exp' => now()->addMinutes(self::STATE_TTL_MINUTES)->timestamp,
        ]));

        $url = $this->registry->get($provider)
            ->authorizeUrl($app, $state, $this->mobileRedirectUri());

        return $this->ok(['authorize_url' => $url]);
    }

    /**
     * PUBLIC OAuth landing (no auth:sanctum). Decrypts the state token,
     * exchanges the code, upserts the connection in the token's workspace and
     * redirects back to the app's deep link. Errors are conveyed as
     * `?error=<code>` so the mobile callback screen can render friendly copy.
     */
    public function oauthCallback(Request $request)
    {
        $fallback = self::MOBILE_RETURN_ALLOWLIST[0];

        $state = (string) $request->query('state', '');
        try {
            $data = json_decode(Crypt::decryptString($state), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException | \JsonException $e) {
            return $this->bounce($fallback, ['error' => 'invalid_state']);
        }
        if (!is_array($data)) {
            return $this->bounce($fallback, ['error' => 'invalid_state']);
        }

        $return   = is_string($data['r'] ?? null) && in_array($data['r'], self::MOBILE_RETURN_ALLOWLIST, true)
            ? $data['r'] : $fallback;
        $provider = (string) ($data['p'] ?? '');
        $userId   = (int) ($data['u'] ?? 0);
        $wsId     = (int) ($data['w'] ?? 0);

        if (!CloudProviderApp::isKnownProvider($provider)) {
            return $this->bounce($return, ['error' => 'invalid_state']);
        }
        if (($data['exp'] ?? 0) < now()->timestamp) {
            return $this->bounce($return, ['error' => 'expired', 'provider' => $provider]);
        }
        if ($request->has('error') || ($request->query('code', '') === '')) {
            return $this->bounce($return, ['error' => 'access_denied', 'provider' => $provider]);
        }

        $user = User::find($userId);
        $ws   = Workspace::find($wsId);
        if (!$user || !$ws) {
            return $this->bounce($return, ['error' => 'invalid_state', 'provider' => $provider]);
        }
        // Defense-in-depth: the user must still be able to manage files in
        // this workspace at exchange time (role could have changed mid-flow).
        if (!$this->userCan($user, $ws, 'files.view')) {
            return $this->bounce($return, ['error' => 'forbidden', 'provider' => $provider]);
        }

        // Public path: no current_workspace is bound, so the BelongsToWorkspace
        // global scope is skipped. Constrain the provider-app lookup to the
        // state token's workspace to avoid selecting another workspace's row.
        $app = CloudProviderApp::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $ws->id)
            ->where('provider', $provider)
            ->first();
        if (!$app || !$app->isConfigured()) {
            return $this->bounce($return, ['error' => 'provider_not_configured', 'provider' => $provider]);
        }

        try {
            [$access, $refresh, $expires, $email, $label, $scopes] =
                $this->registry->get($provider)->exchangeCode($app, (string) $request->query('code'), $this->mobileRedirectUri());
        } catch (\RuntimeException $e) {
            return $this->bounce($return, ['error' => 'exchange_failed', 'provider' => $provider]);
        }

        // No current_workspace bound on this public path, so set workspace_id
        // explicitly (it is intentionally not mass-assignable).
        $conn = CloudConnection::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $ws->id)
            ->where('user_id', $user->id)
            ->where('provider', $provider)
            ->first() ?? new CloudConnection();

        $conn->workspace_id           = $ws->id;
        $conn->user_id                = $user->id;
        $conn->provider               = $provider;
        $conn->account_label          = $label;
        $conn->account_email          = $email;
        $conn->access_token_encrypted = $access;
        if ($refresh) $conn->refresh_token_encrypted = $refresh;
        $conn->expires_at             = $expires;
        $conn->scopes                 = $scopes;
        $conn->last_error             = null;
        $conn->last_error_at          = null;
        $conn->last_synced_at         = now();
        $conn->save();

        return $this->bounce($return, ['status' => 'connected', 'provider' => $provider]);
    }

    public function disconnect(Request $request, int $connection): JsonResponse
    {
        [$ws, $err] = $this->gate($request, 'files.view');
        if ($err) return $err;

        $conn = CloudConnection::query()->find($connection);
        if (!$conn || $conn->user_id !== $request->user()->id) {
            return $this->notFound('Connection not found.');
        }
        $conn->delete();
        return $this->ok(['removed' => true]);
    }

    // ── Picker (browse provider folders) ───────────────────────────

    public function browse(Request $request, int $connection): JsonResponse
    {
        [$ws, $err] = $this->gate($request, 'files.view');
        if ($err) return $err;

        $conn = CloudConnection::query()->find($connection);
        if (!$conn || $conn->user_id !== $request->user()->id) {
            return $this->notFound('Connection not found.');
        }

        $app = CloudProviderApp::where('provider', $conn->provider)->first();
        if (!$app || !$app->isConfigured()) {
            return $this->fail('This provider is no longer configured.', 422, 'app_not_configured');
        }

        $conn = $this->registry->refreshIfExpiring($conn, $app);
        if ($conn->isBroken()) {
            return $this->fail($conn->last_error ?: 'Reconnect required.', 422, 'reconnect_required');
        }

        $provider = $this->registry->get($conn->provider);
        $search = trim((string) $request->query('search', ''));
        try {
            $listing = $search !== ''
                ? $provider->search($conn, $search, $request->query('cursor') ?: null)
                : $provider->listFolder($conn, $request->query('folder') ?: null, $request->query('cursor') ?: null);
        } catch (\RuntimeException $e) {
            $conn->update(['last_error' => substr($e->getMessage(), 0, 240)]);
            return $this->fail($e->getMessage(), 422, 'list_failed');
        }

        return $this->ok($listing);
    }

    // ── Library (workspace-shared saved files) ─────────────────────

    public function library(Request $request): JsonResponse
    {
        [$ws, $err] = $this->gate($request, 'files.view');
        if ($err) return $err;

        $q = CloudFile::query()->with('addedBy:id,name');
        if ($p = $request->query('provider')) {
            if (CloudProviderApp::isKnownProvider($p)) $q->where('provider', $p);
        }
        if ($needle = trim((string) $request->query('q', ''))) {
            $q->where('name', 'like', '%' . $needle . '%');
        }

        $page = $q->orderByDesc('added_at')->paginate(min((int) $request->query('per_page', 40) ?: 40, 100));

        return $this->ok([
            'files' => collect($page->items())->map(fn (CloudFile $f) => $this->serializeFile($f))->all(),
            'meta'  => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        [$ws, $err] = $this->gate($request, 'files.create');
        if ($err) return $err;

        $data = $request->validate([
            'connection_id'         => ['required', 'integer'],
            'items'                 => ['required', 'array', 'min:1', 'max:50'],
            'items.*.remote_id'     => ['required', 'string', 'max:255'],
            'items.*.name'          => ['required', 'string', 'max:255'],
            'items.*.mime'          => ['nullable', 'string', 'max:191'],
            'items.*.size'          => ['nullable', 'integer', 'min:0'],
            'items.*.link'          => ['required', 'url', 'max:1024'],
            'items.*.thumbnail_url' => ['nullable', 'url', 'max:1024'],
            'parent_folder_path'    => ['nullable', 'string', 'max:255'],
        ]);

        $conn = CloudConnection::query()->find($data['connection_id']);
        if (!$conn || $conn->user_id !== $request->user()->id) {
            return $this->notFound('Connection not found.');
        }

        $added = 0;
        foreach ($data['items'] as $it) {
            $exists = CloudFile::query()
                ->where('provider', $conn->provider)
                ->where('remote_id', $it['remote_id'])
                ->exists();
            if ($exists) continue;

            CloudFile::create([
                'added_by_user_id'   => Auth::id(),
                'connection_id'      => $conn->id,
                'provider'           => $conn->provider,
                'remote_id'          => $it['remote_id'],
                'name'               => $it['name'],
                'mime'               => $it['mime'] ?? null,
                'size'               => (int) ($it['size'] ?? 0),
                'link'               => $it['link'],
                'thumbnail_url'      => $it['thumbnail_url'] ?? null,
                'parent_folder_path' => $data['parent_folder_path'] ?? null,
                'added_at'           => now(),
            ]);
            $added++;
        }

        return $this->created(['added' => $added]);
    }

    public function destroy(Request $request, int $cloudFile): JsonResponse
    {
        [$ws, $err] = $this->gate($request, 'files.delete');
        if ($err) return $err;

        $file = CloudFile::query()->find($cloudFile);
        if (!$file) return $this->notFound('File not found.');
        $file->delete();
        return $this->ok(['removed' => true]);
    }

    // ── Attachments (post / task_card / inbox_reply) ───────────────

    public function attachments(Request $request): JsonResponse
    {
        [$ws, $err] = $this->gate($request, 'files.view');
        if ($err) return $err;

        $data = $request->validate([
            'target_type' => ['required', 'string'],
            'target_id'   => ['required', 'integer'],
        ]);
        $cls = self::TARGETS[$data['target_type']] ?? null;
        if (!$cls) return $this->fail('Unknown attach target.', 422, 'unknown_target');

        $target = $cls::query()->find($data['target_id']);
        if (!$target) return $this->notFound('Target not found.');

        $atts = CloudFileAttachment::query()
            ->with('cloudFile')
            ->where('attachable_type', $cls)
            ->where('attachable_id', $target->id)
            ->get();

        return $this->ok([
            'attachments' => $atts->map(fn (CloudFileAttachment $a) => CloudFileAttachmentController::serialize($a))->all(),
        ]);
    }

    public function attach(Request $request): JsonResponse
    {
        [$ws, $err] = $this->gate($request, 'files.view');
        if ($err) return $err;

        $data = $request->validate([
            'target_type'      => ['required', 'string'],
            'target_id'        => ['required', 'integer'],
            'cloud_file_ids'   => ['required', 'array', 'min:1', 'max:50'],
            'cloud_file_ids.*' => ['integer'],
        ]);

        $cls = self::TARGETS[$data['target_type']] ?? null;
        if (!$cls) return $this->fail('Unknown attach target.', 422, 'unknown_target');

        $target = $cls::query()->find($data['target_id']);
        if (!$target) return $this->notFound('Target not found.');

        $created = [];
        foreach (array_unique($data['cloud_file_ids']) as $cfId) {
            $file = CloudFile::query()->find($cfId);
            if (!$file) continue;
            $att = CloudFileAttachment::firstOrCreate(
                [
                    'cloud_file_id'   => $file->id,
                    'attachable_type' => $cls,
                    'attachable_id'   => $target->id,
                ],
                [
                    'attached_by_user_id' => $request->user()->id,
                ],
            );
            $created[] = CloudFileAttachmentController::serialize($att, $file);
        }

        return $this->ok(['attachments' => $created]);
    }

    public function detach(Request $request, int $attachment): JsonResponse
    {
        [$ws, $err] = $this->gate($request, 'files.view');
        if ($err) return $err;

        $att = CloudFileAttachment::query()->find($attachment);
        if (!$att) return $this->notFound('Attachment not found.');
        $att->delete();
        return $this->ok(['removed' => true]);
    }

    // ── Helpers ────────────────────────────────────────────────────

    /**
     * Resolve + bind the active workspace and enforce a workspace permission,
     * mirroring the web `workspace.can:<perm>` middleware. Returns
     * `[Workspace, null]` on success or `[null, JsonResponse]` on failure.
     *
     * @return array{0: ?Workspace, 1: ?JsonResponse}
     */
    protected function gate(Request $request, string $perm): array
    {
        $user = $request->user();
        $ws = $this->ctx->resolve($user);
        if (!$ws) {
            return [null, $this->forbidden('No workspace available for this account.')];
        }
        // Binding activates the BelongsToWorkspace global scope so all reads
        // and creates in this request auto-scope to the active workspace.
        app()->instance('current_workspace', $ws);

        if (!$this->userCan($user, $ws, $perm)) {
            return [null, $this->forbidden('You do not have permission to perform this action in this workspace.')];
        }
        return [$ws, null];
    }

    /** Mirror of RequireWorkspacePermission's resolution. */
    protected function userCan(User $user, Workspace $ws, string $perm): bool
    {
        if ((int) $ws->owner_user_id === (int) $user->id) return true;
        if (method_exists($user, 'hasPermission') && $user->hasPermission('user.workspaces.access_any')) return true;
        $member = method_exists($user, 'membershipFor') ? $user->membershipFor($ws) : null;
        return $member ? $member->can($perm) : false;
    }

    protected function mobileRedirectUri(): string
    {
        return url('/api/v1/cloud-files/oauth/callback');
    }

    /** External redirect back to a mobile deep link with query params. */
    protected function bounce(string $base, array $params)
    {
        return redirect()->away($base . '?' . http_build_query($params));
    }

    protected function serializeConnection(CloudConnection $c): array
    {
        return [
            'id'              => $c->id,
            'provider'        => $c->provider,
            'provider_label'  => $c->providerLabel(),
            'account_label'   => $c->account_label,
            'account_email'   => $c->account_email,
            'is_broken'       => $c->isBroken(),
            'last_error'      => $c->last_error,
            'expires_soon'    => $c->expiresSoon(),
            'last_synced_at'  => $c->last_synced_at?->toIso8601String(),
        ];
    }

    protected function serializeFile(CloudFile $f): array
    {
        return [
            'id'             => $f->id,
            'name'           => $f->name,
            'link'           => $f->link,
            'mime'           => $f->mime,
            'size'           => (int) $f->size,
            'human_size'     => $f->humanSize(),
            'provider'       => $f->provider,
            'provider_icon'  => $f->providerIcon(),
            'provider_label' => $f->providerLabel(),
            'thumbnail_url'  => $f->thumbnail_url,
            'connection_id'  => $f->connection_id,
            'added_by'       => $f->addedBy?->name,
            'added_at'       => $f->added_at?->toIso8601String(),
        ];
    }
}
