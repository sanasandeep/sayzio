<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\ProfileVerificationRequest;
use App\Modules\User\Models\VerificationTickType;
use App\Modules\User\Support\ProfileVerificationModeration;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Reviewer (moderation) API for profile-level verification requests —
 * mobile parity for the web `/user/profile-verification-admin` screens.
 *
 * Routes are gated by the same web-pool permission the web screens use
 * (`user.can:user.verifications.review`), so the same reviewer accounts
 * work from the app with their normal bearer token. Approve/reject
 * delegate to {@see ProfileVerificationModeration} — the exact cores the
 * web controller runs.
 */
class ProfileVerificationAdminApiController extends Controller
{
    use ApiResponses;

    /** List requests: `queue` = new|reverification, optional `status`. */
    public function index(Request $request)
    {
        $queue = $request->query('queue', 'new');

        $query = ProfileVerificationRequest::with(['user:id,name,email,handle', 'tickType'])
            ->where('kind', $queue === 'reverification' ? 'reverification' : 'new');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->orderByDesc('created_at')
            ->paginate(min(100, max(1, (int) $request->query('per_page', 20))));

        return $this->ok([
            'requests' => collect($requests->items())->map(fn ($r) => $this->requestPayload($r))->all(),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page'    => $requests->lastPage(),
                'total'        => $requests->total(),
            ],
            'pending_new_count'            => ProfileVerificationRequest::where('kind', 'new')->where('status', 'pending')->count(),
            'pending_reverification_count' => ProfileVerificationRequest::where('kind', 'reverification')->where('status', 'pending')->count(),
        ]);
    }

    /** Full detail for one request (incl. proof files + user updates). */
    public function show(ProfileVerificationRequest $profileVerificationRequest)
    {
        $profileVerificationRequest->load(['user:id,name,email,handle', 'tickType', 'reviewer:id,name']);
        return $this->ok(['request' => $this->requestPayload($profileVerificationRequest, detailed: true)]);
    }

    public function approve(Request $request, ProfileVerificationRequest $profileVerificationRequest)
    {
        if ($profileVerificationRequest->status !== 'pending') {
            return $this->fail('This request has already been reviewed.', 409, 'already_reviewed');
        }

        $data = $request->validate([
            'admin_notes'  => 'nullable|string|max:2000',
            'tick_type_id' => 'nullable|integer|exists:verification_tick_types,id',
        ]);

        ProfileVerificationModeration::approve($profileVerificationRequest, $data, (int) $request->user()->id);

        return $this->ok(['request' => $this->requestPayload($profileVerificationRequest->fresh(['user:id,name,email,handle', 'tickType']))]);
    }

    public function reject(Request $request, ProfileVerificationRequest $profileVerificationRequest)
    {
        if ($profileVerificationRequest->status !== 'pending') {
            return $this->fail('This request has already been reviewed.', 409, 'already_reviewed');
        }

        $data = $request->validate([
            'admin_notes' => 'required|string|max:2000',
        ]);

        ProfileVerificationModeration::reject($profileVerificationRequest, $data, (int) $request->user()->id);

        return $this->ok(['request' => $this->requestPayload($profileVerificationRequest->fresh(['user:id,name,email,handle', 'tickType']))]);
    }

    /** Tick-type catalog (all types, incl. inactive/admin-only). */
    public function tickTypes()
    {
        return $this->ok([
            'tick_types' => VerificationTickType::orderBy('sort_order')->get()->map(fn ($t) => [
                'id'                  => $t->id,
                'name'                => $t->name,
                'color'               => $t->color,
                'is_active'           => (bool) $t->is_active,
                'admin_assigned_only' => (bool) $t->admin_assigned_only,
                'sort_order'          => (int) $t->sort_order,
            ])->all(),
        ]);
    }

    public function updateTickType(Request $request, VerificationTickType $verificationTickType)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:80',
            'color'      => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_active'  => 'boolean',
            'sort_order' => 'integer|min:0|max:999',
        ]);

        $verificationTickType->update($data);

        return $this->ok(['tick_type' => $verificationTickType->only(['id', 'name', 'color', 'is_active', 'sort_order'])]);
    }

    private function requestPayload(ProfileVerificationRequest $r, bool $detailed = false): array
    {
        $base = [
            'id'            => $r->id,
            'kind'          => $r->kind,
            'status'        => $r->status,
            'official_name' => $r->official_name,
            'purpose'       => $r->purpose,
            'created_at'    => optional($r->created_at)->toIso8601String(),
            'reviewed_at'   => optional($r->reviewed_at)->toIso8601String(),
            'admin_notes'   => $r->admin_notes,
            'tick_type'     => $r->tickType ? ['id' => $r->tickType->id, 'name' => $r->tickType->name, 'color' => $r->tickType->color] : null,
            'user'          => $r->user ? ['id' => $r->user->id, 'name' => $r->user->name, 'email' => $r->user->email, 'handle' => $r->user->handle] : null,
        ];

        if ($detailed) {
            $base += [
                'logo_path'   => $r->logo_path,
                'proof_files' => $r->proof_files ?? [],
                'new_name'    => $r->new_name,
                'new_avatar'  => $r->new_avatar,
                'updates'     => $r->updates ?? [],
                'reviewer'    => $r->reviewer ? ['id' => $r->reviewer->id, 'name' => $r->reviewer->name] : null,
            ];
        }

        return $base;
    }
}
