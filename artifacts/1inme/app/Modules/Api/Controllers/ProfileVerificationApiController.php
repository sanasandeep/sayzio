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
        $user->load('verificationTickType');

        $requests = ProfileVerificationRequest::where('user_id', $user->id)
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

        if (in_array($user->profile_verification_status, ['pending', 'pending_reverification'], true)) {
            return $this->fail('You already have a pending verification request.', 400, 'bad_request');
        }

        $tickTypes = VerificationTickType::publicRequestable()->pluck('id')->all();

        $data = $request->validate([
            'tick_type_id'  => 'required|integer|in:' . implode(',', $tickTypes),
            'official_name' => 'required|string|max:200',
            'purpose'       => 'required|string|max:3000',
        ]);

        $req = ProfileVerificationRequest::create([
            'user_id'       => $user->id,
            'tick_type_id'  => $data['tick_type_id'],
            'official_name' => $data['official_name'],
            'purpose'       => $data['purpose'],
            'status'        => 'pending',
            'kind'          => 'new',
        ]);

        $user->update(['profile_verification_status' => 'pending']);

        return $this->created(['request' => $this->transformRequest($req->fresh('tickType'))]);
    }

    /**
     * POST /api/v1/profile-verification/updates — append a follow-up
     * message and/or attachments to the caller's pending request.
     */
    public function addUpdate(Request $request)
    {
        $user = $request->user();

        $req = ProfileVerificationRequest::where('user_id', $user->id)
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

        ProfileVerificationRequest::where('user_id', $user->id)
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
            'status'       => $r->status,
            'kind'         => $r->kind,
            'admin_notes'  => $r->admin_notes,
            'updates'      => $r->updates ?? [],
            'reviewed_at'  => optional($r->reviewed_at)->toIso8601String(),
            'created_at'   => optional($r->created_at)->toIso8601String(),
        ];
    }
}
