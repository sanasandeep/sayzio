<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\ProfileVerificationRequest;
use App\Modules\User\Models\VerificationTickType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * REST API for account-level creator profile verification (Task #5439).
 *
 *   GET  /api/v1/profile-verification        → current status + request history
 *   POST /api/v1/profile-verification        → submit a new verification request
 *   POST /api/v1/profile-verification/reverify → trigger a re-verification
 */
class ProfileVerificationApiController extends Controller
{
    use ApiResponses;

    public function show(Request $request)
    {
        $user = $request->user();
        $profile = $this->workspaceProfile($request);
        $target = $profile ?? $user;
        if ($profile) $user = $profile->applyToUser(clone $user);
        $user->load('verificationTickType');

        $requests = ProfileVerificationRequest::where('user_id', $user->id)->where('creator_profile_id', $profile?->id)
            ->with('tickType')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($r) => $this->transformRequest($r));

        $tickTypes = VerificationTickType::publicRequestable()->get()->map(fn ($t) => $this->transformTickType($t));

        return $this->ok([
            'status'           => $user->profile_verification_status,
            'tick_type'        => $user->verificationTickType ? $this->transformTickType($user->verificationTickType) : null,
            'verified_name'    => $user->profile_verified_name,
            'verified_avatar'  => $user->profile_verified_avatar,
            'verified_at'      => optional($user->profile_verified_at)->toIso8601String(),
            'requests'         => $requests->all(),
            'tick_types'       => $tickTypes->all(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $profile = $this->workspaceProfile($request);
        $target = $profile ?? $user;
        if ($profile) $user = $profile->applyToUser(clone $user);

        if (in_array($user->profile_verification_status, ['pending', 'pending_reverification'], true)) {
            return $this->fail('You already have a pending verification request.', 400, 'bad_request');
        }

        $tickTypes = VerificationTickType::publicRequestable()->pluck('id')->all();

        $data = $request->validate([
            ...\App\Modules\User\Support\VerificationEvidence::rules(),
            'tick_type_id'  => 'required|integer|in:' . implode(',', $tickTypes),
            'official_name' => 'required|string|max:200',
            'purpose'       => 'required|string|max:3000',
            'message' => 'nullable|string|max:2000',
            'proof_files' => 'nullable|array|max:5',
            'proof_files.*' => \App\Services\UploadPolicy::rule('verification.proof', $user),
        ]);

        $proofPaths = [];
        foreach ($request->file('proof_files', []) as $file) {
            try {
                $proof = \App\Modules\User\Models\UserFile::createFromUpload($file, $user, ['enforce_allowlist'=>false,'upload_key'=>'verification.proof']);
                $proofPaths[] = $proof->url_path;
            } catch (\RuntimeException) {
                return $this->fail('File upload failed. Please try again.', 400, 'bad_request');
            }
        }

        $req = ProfileVerificationRequest::create([
            ...\App\Modules\User\Support\VerificationEvidence::fields($data),
            'user_id'       => $user->id,
            'creator_profile_id' => $profile?->id,
            'tick_type_id'  => $data['tick_type_id'],
            'official_name' => $data['official_name'],
            'purpose'       => $data['purpose'],
            'status'        => 'pending',
            'kind'          => 'new',
        ]);

        $req->update(['proof_files'=>$proofPaths]);
        if (trim((string) ($data['message'] ?? '')) !== '') $req->appendUpdate(trim($data['message']));

        $target->update(['profile_verification_status' => 'pending']);

        return $this->created(['request' => $this->transformRequest($req->fresh('tickType'))]);
    }

    /**
     * POST /api/v1/profile-verification/updates — append a follow-up
     * message and/or attachments to the caller's pending request.
     */
    public function addUpdate(Request $request)
    {
        $user = $request->user();
        $profile = $this->workspaceProfile($request);
        $target = $profile ?? $user;
        if ($profile) $user = $profile->applyToUser(clone $user);

        $req = ProfileVerificationRequest::where('user_id', $user->id)->where('creator_profile_id', $profile?->id)
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->first();

        if (!$req) {
            return $this->fail('You have no pending verification request to update.', 400, 'bad_request');
        }

        if (count($req->updates ?? []) >= ProfileVerificationRequest::MAX_UPDATES) {
            return $this->fail('You have reached the maximum number of updates for this request.', 400, 'bad_request');
        }

        $data = $request->validate([
            'message'       => 'nullable|string|max:2000',
            'attachments.*' => \App\Services\UploadPolicy::rule('verification.proof', $user),
        ]);

        $message = trim((string) ($data['message'] ?? ''));
        if ($message === '' && !$request->hasFile('attachments')) {
            return $this->fail('Provide a message or at least one attachment.', 400, 'bad_request');
        }

        $paths = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                try {
                    $pf = \App\Modules\User\Models\UserFile::createFromUpload($file, $user, [
                        'enforce_allowlist' => false,
                        'upload_key'        => 'verification.proof',
                    ]);
                    $paths[] = $pf->url_path;
                } catch (\RuntimeException) {
                    return $this->fail('File upload failed. Please try again.', 400, 'bad_request');
                }
            }
        }

        $entry = $req->appendUpdate($message !== '' ? $message : null, $paths);

        return $this->created([
            'update'  => $entry,
            'request' => $this->transformRequest($req->fresh('tickType')),
        ]);
    }

    public function reVerify(Request $request)
    {
        $user = $request->user();

        if (!$user->isVerified()) {
            return $this->forbidden('You are not currently verified.');
        }

        $data = $request->validate([
            'new_name' => 'nullable|string|max:200',
        ]);

        $newName     = trim((string) ($data['new_name'] ?? $user->profile_verified_name));
        $nameChanged = $newName !== (string) $user->profile_verified_name;

        if (!$nameChanged) {
            return $this->fail('No changes detected.', 400, 'bad_request');
        }

        ProfileVerificationRequest::where('user_id', $user->id)->whereNull('creator_profile_id')
            ->where('status', 'pending')
            ->where('kind', 'reverification')
            ->update(['status' => 'superseded']);

        $req = ProfileVerificationRequest::create([
            'user_id'            => $user->id,
            'tick_type_id'       => $user->profile_verification_type_id,
            'official_name'      => $newName,
            'purpose'            => 'Name change by verified user.',
            'kind'               => 'reverification',
            'status'             => 'pending',
            'prev_verified_name' => $user->profile_verified_name,
            'new_name'           => $nameChanged ? $newName : null,
        ]);

        $user->update(['profile_verification_status' => 'pending_reverification']);

        return $this->ok(['request' => $this->transformRequest($req->fresh('tickType')), 'ok' => true]);
    }

    /** Existing account callers keep their scope; creator editor opts into the active workspace. */
    private function workspaceProfile(Request $request): ?\App\Modules\User\Models\CreatorProfile
    {
        if ($request->input('context') !== 'workspace') return null;
        $user = $request->user();
        $ws = app(\App\Modules\User\Services\WorkspaceContext::class)->resolve($user) ?? $user->ensureDefaultWorkspace();
        abort_unless((int) $ws->owner_user_id === (int) $user->id || $user->hasPermission('user.workspaces.access_any') || $user->membershipFor($ws)?->can('settings.edit'), 403);
        $profile = \App\Modules\User\Models\CreatorProfile::forWorkspace($ws);
        if ($request->filled('creator_profile_id')) abort_unless((int) $request->input('creator_profile_id') === (int) $profile->id, 409, 'Workspace changed. Reload before submitting.');
        return $ws->is_personal ? null : $profile;
    }

    private function transformTickType(VerificationTickType $t): array
    {
        return [
            'id'                 => $t->id,
            'slug'               => $t->slug,
            'name'               => $t->name,
            'color'              => $t->color,
            'icon'               => $t->icon,
            'admin_assigned_only'=> $t->admin_assigned_only,
        ];
    }

    private function transformRequest(ProfileVerificationRequest $r): array
    {
        return [
            'id'           => $r->id,
            'tick_type_id' => $r->tick_type_id,
            'tick_type'    => $r->tickType ? $this->transformTickType($r->tickType) : null,
            'official_name'=> $r->official_name,
            'purpose'      => $r->purpose,
            'additional_info' => $r->additional_info,
            'supporting_urls' => $r->supporting_urls ?? [],
            'status'       => $r->status,
            'kind'         => $r->kind,
            'admin_notes'  => $r->admin_notes,
            'updates'      => $r->updates ?? [],
            'proof_files'  => $r->proof_files ?? [],
            'reviewed_at'  => optional($r->reviewed_at)->toIso8601String(),
            'created_at'   => optional($r->created_at)->toIso8601String(),
        ];
    }
}
