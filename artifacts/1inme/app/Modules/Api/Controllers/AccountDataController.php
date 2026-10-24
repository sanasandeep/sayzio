<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Common\Models\PrivacyRequest;
use App\Modules\Common\Services\PrivacyRequestNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountDataController
{
    public function page()
    {
        return view('user.settings.account-data');
    }

    public function index(Request $request)
    {
        $items = PrivacyRequest::where('user_id', $request->user()->id)->latest()->limit(50)->get()->map(fn ($p) => $this->serialize($p));

        return response()->json(['data' => ['items' => $items]]);
    }

    public function store(Request $request, PrivacyRequestNotifier $notifier)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['export', 'deletion', 'data_deletion'])],
            'reason' => 'nullable|string|max:2000',
            'confirmation' => 'required_unless:type,export|string',
            'data_scopes' => 'required_if:type,data_deletion|array|min:1|max:3',
            'data_scopes.*' => 'distinct|in:links,contacts,notifications',
        ]);
        $expected = $data['type'] === 'deletion' ? 'DELETE ACCOUNT' : 'DELETE DATA';
        if ($data['type'] !== 'export' && ($data['confirmation'] ?? '') !== $expected) {
            throw ValidationException::withMessages(['confirmation' => 'Type '.$expected.' to confirm this request.']);
        }
        $user = $request->user();
        $pr = DB::transaction(function () use ($user, $data, $request) {
            $user->newQuery()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = PrivacyRequest::where('user_id', $user->id)->where('type', $data['type'])->whereIn('status', ['verified', 'approved', 'processing'])->first();
            abort_if($existing, 409, 'An active request of this type already exists.');
            $pr = PrivacyRequest::create(['user_id' => $user->id, 'email' => strtolower((string) $user->email), 'type' => $data['type'], 'reason' => $data['reason'] ?? null, 'data_scopes' => $data['type'] === 'data_deletion' ? $data['data_scopes'] : null, 'status' => 'verified', 'verified_at' => now(), 'ip' => $request->ip()]);
            $pr->recordAudit('submitted', 'user:'.$user->id, 'Submitted through authenticated account data API.');

            return $pr;
        });
        $notifier->notifyReceived($pr);
        $notifier->notifyVerified($pr);

        return response()->json(['data' => ['request' => $this->serialize($pr)]], 201);
    }

    public function download(Request $request, PrivacyRequest $privacyRequest)
    {
        abort_unless((int) $privacyRequest->user_id === (int) $request->user()->id, 404);
        abort_unless($privacyRequest->downloadIsLive(), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($privacyRequest->archive_path), 404);
        $privacyRequest->recordAudit('downloaded', 'user:'.$request->user()->id);

        return response()->download($disk->path($privacyRequest->archive_path), 'sayzio-data-'.$privacyRequest->id.'.zip', ['Cache-Control' => 'private, no-store']);
    }

    private function serialize(PrivacyRequest $p): array
    {
        return ['id' => $p->id, 'type' => $p->type, 'label' => $p->typeLabel(), 'status' => $p->status, 'data_scopes' => $p->data_scopes, 'created_at' => $p->created_at?->toIso8601String(), 'scheduled_at' => $p->scheduled_at?->toIso8601String(), 'download_expires_at' => $p->download_expires_at?->toIso8601String(), 'can_download' => (bool) $p->downloadIsLive(), 'rejection_reason' => $p->rejection_reason];
    }
}
