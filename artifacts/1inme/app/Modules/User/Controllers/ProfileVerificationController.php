<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\ProfileVerificationRequest;
use App\Modules\User\Models\VerificationTickType;
use App\Modules\User\Models\UserFile;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Handles the account-level creator profile verification flow.
 *
 * Routes:
 *  GET  settings/profile-verification            → index (status panel)
 *  GET  settings/profile-verification/request    → create (new request form)
 *  POST settings/profile-verification/request    → store
 *  POST settings/profile-verification/reverify   → reVerify (trigger re-verify after name/avatar change)
 *
 * Admin routes (user.can:user.verifications.review):
 *  GET  profile-verification-admin               → adminIndex
 *  GET  profile-verification-admin/{request}     → adminReview
 *  POST profile-verification-admin/{request}/approve  → adminApprove
 *  POST profile-verification-admin/{request}/reject   → adminReject
 *  GET  profile-verification-admin/tick-types         → adminTickTypes
 *  POST profile-verification-admin/tick-types/{id}    → adminUpdateTickType
 */
class ProfileVerificationController extends Controller
{
    // ------------------------------------------------------------------
    // User-facing actions
    // ------------------------------------------------------------------

    public function index()
    {
        $user = Auth::user();
        $user->load('verificationTickType');
        $requests = ProfileVerificationRequest::where('user_id', $user->id)
            ->with('tickType')
            ->orderByDesc('created_at')
            ->get();
        $tickTypes = VerificationTickType::publicRequestable()->get();
        return view('user.verification.index', compact('user', 'requests', 'tickTypes'));
    }

    public function create()
    {
        $user = Auth::user();
        if (in_array($user->profile_verification_status, ['pending', 'verified', 'pending_reverification'], true)) {
            return redirect()->route('user.profile-verification.index')
                ->with('info', 'You already have a verification request or are already verified.');
        }
        $tickTypes = VerificationTickType::publicRequestable()->get();
        return view('user.verification.request', compact('user', 'tickTypes'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        if (in_array($user->profile_verification_status, ['pending', 'pending_reverification'], true)) {
            return redirect()->route('user.profile-verification.index')
                ->with('info', 'You already have a pending verification request.');
        }

        $tickTypes = VerificationTickType::publicRequestable()->pluck('id')->all();

        $data = $request->validate([
            'tick_type_id'  => 'required|integer|in:' . implode(',', $tickTypes),
            'official_name' => 'required|string|max:200',
            'purpose'       => 'required|string|max:3000',
            'logo'          => \App\Services\UploadPolicy::rule('verification.logo', $user),
            'proof_files.*' => \App\Services\UploadPolicy::rule('verification.proof', $user),
        ]);

        $logoPath   = $this->uploadLogo($request, $user);
        $proofPaths = $this->uploadProofFiles($request, $user);

        if ($logoPath === false || $proofPaths === false) {
            return back()->withInput()->with('error', 'File upload failed. Please try again.');
        }

        ProfileVerificationRequest::create([
            'user_id'       => $user->id,
            'tick_type_id'  => $data['tick_type_id'],
            'official_name' => $data['official_name'],
            'purpose'       => $data['purpose'],
            'logo_path'     => $logoPath,
            'proof_files'   => $proofPaths,
            'status'        => 'pending',
            'kind'          => 'new',
        ]);

        $user->update(['profile_verification_status' => 'pending']);

        return redirect()->route('user.profile-verification.index')
            ->with('success', 'Verification request submitted! We will review it and get back to you.');
    }

    /**
     * Triggered when a verified user changes their name or avatar.
     * Applies the change immediately but switches the tick to "pending_reverification".
     */
    public function reVerify(Request $request)
    {
        $user = Auth::user();

        if (!$user->isVerified()) {
            return redirect()->route('user.profile-verification.index')
                ->with('error', 'You are not currently verified.');
        }

        $data = $request->validate([
            'new_name'   => 'nullable|string|max:200',
            'new_avatar' => 'nullable|string|max:500',
        ]);

        $newName   = trim((string) ($data['new_name'] ?? $user->name));
        $newAvatar = trim((string) ($data['new_avatar'] ?? $user->avatar));

        $nameChanged   = $newName   !== (string) $user->profile_verified_name;
        $avatarChanged = $newAvatar !== (string) $user->profile_verified_avatar;

        if (!$nameChanged && !$avatarChanged) {
            return redirect()->route('user.profile-verification.index')
                ->with('info', 'No changes detected.');
        }

        // Kill any outstanding pending re-verification requests for this user
        ProfileVerificationRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->where('kind', 'reverification')
            ->update(['status' => 'superseded']);

        ProfileVerificationRequest::create([
            'user_id'           => $user->id,
            'tick_type_id'      => $user->profile_verification_type_id,
            'official_name'     => $newName,
            'purpose'           => 'Name / avatar change by verified user.',
            'kind'              => 'reverification',
            'status'            => 'pending',
            'prev_verified_name'=> $user->profile_verified_name,
            'new_name'          => $nameChanged   ? $newName   : null,
            'new_avatar'        => $avatarChanged ? $newAvatar : null,
        ]);

        $user->update(['profile_verification_status' => 'pending_reverification']);

        return redirect()->route('user.profile-verification.index')
            ->with('success', 'Change submitted for re-verification. Your tick is temporarily marked as pending while we review.');
    }

    // ------------------------------------------------------------------
    // Admin actions
    // ------------------------------------------------------------------

    public function adminIndex(Request $request)
    {
        $queue = $request->query('queue', 'new'); // new | reverification

        $query = ProfileVerificationRequest::with(['user', 'tickType'])
            ->where('kind', $queue === 'reverification' ? 'reverification' : 'new');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->orderByDesc('created_at')->paginate(20);
        $pendingNewCount    = ProfileVerificationRequest::where('kind', 'new')->where('status', 'pending')->count();
        $pendingReVerCount  = ProfileVerificationRequest::where('kind', 'reverification')->where('status', 'pending')->count();

        return view('user.verification.admin-index', compact('requests', 'queue', 'pendingNewCount', 'pendingReVerCount'));
    }

    public function adminReview(ProfileVerificationRequest $profileVerificationRequest)
    {
        $profileVerificationRequest->load(['user', 'tickType', 'reviewer']);
        return view('user.verification.admin-review', ['req' => $profileVerificationRequest]);
    }

    public function adminApprove(Request $request, ProfileVerificationRequest $profileVerificationRequest)
    {
        if ($profileVerificationRequest->status !== 'pending') {
            return redirect()->route('user.profile-verification.admin.index')
                ->with('error', 'This request has already been reviewed.');
        }

        $data = $request->validate([
            'admin_notes'  => 'nullable|string|max:2000',
            'tick_type_id' => 'nullable|integer|exists:verification_tick_types,id',
        ]);

        $req  = $profileVerificationRequest;
        $user = $req->user;

        DB::transaction(function () use ($req, $user, $data) {
            $req->update([
                'status'       => 'approved',
                'admin_notes'  => $data['admin_notes'] ?? null,
                'reviewed_at'  => now(),
                'reviewed_by'  => Auth::id(),
            ]);

            $tickId = $data['tick_type_id'] ?? $req->tick_type_id;

            $updates = [
                'profile_verification_status'   => 'verified',
                'profile_verification_type_id'  => $tickId,
                'profile_verified_at'           => now(),
            ];

            if ($req->kind === 'new') {
                $updates['profile_verified_name']   = $req->official_name;
                $updates['profile_verified_avatar'] = $req->logo_path;
            } else {
                // Re-verification: apply the approved name/avatar change
                if ($req->new_name)   $updates['profile_verified_name']   = $req->new_name;
                if ($req->new_avatar) $updates['profile_verified_avatar'] = $req->new_avatar;
            }

            $user->update($updates);
        });

        $tickName     = optional(VerificationTickType::find($data['tick_type_id'] ?? $req->tick_type_id))->name ?? 'verified';
        $verifiedName = (string) ($user->fresh()->profile_verified_name ?? $req->official_name);
        app(\App\Modules\Admin\Services\UserAccountNotifier::class)
            ->verificationApproved($user, $tickName, $verifiedName, $req->kind !== 'new');

        return redirect()->route('user.profile-verification.admin.index')
            ->with('success', 'Verification approved — user now holds the ' . optional($req->tickType)->name . ' tick.');
    }

    public function adminReject(Request $request, ProfileVerificationRequest $profileVerificationRequest)
    {
        $data = $request->validate([
            'admin_notes' => 'required|string|max:2000',
        ]);

        $req  = $profileVerificationRequest;
        $user = $req->user;

        DB::transaction(function () use ($req, $user, $data) {
            $req->update([
                'status'      => 'rejected',
                'admin_notes' => $data['admin_notes'],
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
            ]);

            // On rejection, revert the user's status
            if ($req->kind === 'new') {
                $user->update(['profile_verification_status' => 'unverified']);
            } else {
                // Re-verification rejected → revert to fully verified
                $user->update(['profile_verification_status' => 'verified']);
            }
        });

        app(\App\Modules\Admin\Services\UserAccountNotifier::class)
            ->verificationRejected($user, $data['admin_notes'], $req->kind !== 'new');

        return redirect()->route('user.profile-verification.admin.index')
            ->with('success', 'Verification request rejected.');
    }

    public function adminTickTypes()
    {
        $tickTypes = VerificationTickType::orderBy('sort_order')->get();
        return view('user.verification.admin-tick-types', compact('tickTypes'));
    }

    public function adminUpdateTickType(Request $request, VerificationTickType $verificationTickType)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:80',
            'color'      => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_active'  => 'boolean',
            'sort_order' => 'integer|min:0|max:999',
        ]);

        $verificationTickType->update($data);

        return redirect()->route('user.profile-verification.admin.tick-types')
            ->with('success', 'Tick type updated.');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function uploadLogo(Request $request, $user): ?string
    {
        if (!$request->hasFile('logo')) return null;
        try {
            $file = UserFile::createFromUpload($request->file('logo'), $user, ['upload_key' => 'verification.logo']);
            return $file->url_path;
        } catch (\RuntimeException) {
            return false;
        }
    }

    private function uploadProofFiles(Request $request, $user): array|false
    {
        if (!$request->hasFile('proof_files')) return [];
        $paths = [];
        foreach ($request->file('proof_files') as $file) {
            try {
                $pf = UserFile::createFromUpload($file, $user, [
                    'enforce_allowlist' => false,
                    'upload_key'        => 'verification.proof',
                ]);
                $paths[] = $pf->url_path;
            } catch (\RuntimeException) {
                return false;
            }
        }
        return $paths;
    }
}
