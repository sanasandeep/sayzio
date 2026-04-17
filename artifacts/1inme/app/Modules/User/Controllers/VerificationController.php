<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Models\VerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class VerificationController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $requests = VerificationRequest::where('user_id', $user->id)
            ->with('link')
            ->orderByDesc('created_at')
            ->get();
        $biolinks = Link::where('user_id', $user->id)->where('type', 'biolink')->get();
        return view('user.verification.index', compact('requests', 'biolinks'));
    }

    public function create(Request $request)
    {
        $user = Auth::user();
        $biolinks = Link::where('user_id', $user->id)->where('type', 'biolink')->get();
        $linkId = $request->query('link_id');
        return view('user.verification.request', compact('biolinks', 'linkId'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'link_id' => 'required|integer|exists:links,id',
            'category' => 'required|in:artist_creator,business_product',
            'business_name' => 'required|string|max:200',
            'display_name' => 'required|string|max:200',
            'purpose' => 'required|string|max:2000',
            'logo' => 'nullable|image|max:2048',
            'proof_files.*' => 'nullable|file|max:5120',
        ]);

        $link = Link::where('id', $data['link_id'])->where('user_id', $user->id)->firstOrFail();

        $existing = VerificationRequest::where('link_id', $link->id)
            ->where('status', 'pending')
            ->first();
        if ($existing) {
            return redirect()->route('user.verification.index')
                ->with('error', 'A verification request is already pending for this Link in Bio page.');
        }

        $logoPath = null;
        if ($request->hasFile('logo')) {
            try {
                // Logo capped at 2MB by validation above; allowlist enforces image MIME.
                $logoFile = UserFile::createFromUpload($request->file('logo'), $user, [
                    'max_size_mb' => 2,
                ]);
                $logoPath = $logoFile->url_path;
            } catch (\RuntimeException $e) {
                return back()->withInput()->with('error', $e->getMessage());
            }
        }

        $proofPaths = [];
        if ($request->hasFile('proof_files')) {
            foreach ($request->file('proof_files') as $file) {
                try {
                    // Proof files accept arbitrary types (PDFs, screenshots, docs).
                    $pf = UserFile::createFromUpload($file, $user, [
                        'enforce_allowlist' => false,
                        'max_size_mb'       => 5,
                    ]);
                    $proofPaths[] = $pf->url_path;
                } catch (\RuntimeException $e) {
                    return back()->withInput()->with('error', $e->getMessage());
                }
            }
        }

        VerificationRequest::create([
            'user_id' => $user->id,
            'link_id' => $link->id,
            'category' => $data['category'],
            'business_name' => $data['business_name'],
            'display_name' => $data['display_name'],
            'purpose' => $data['purpose'],
            'logo_path' => $logoPath,
            'proof_files' => $proofPaths,
            'status' => 'pending',
        ]);

        return redirect()->route('user.verification.index')
            ->with('success', 'Verification request submitted successfully! We will review it shortly.');
    }

    public function adminIndex(Request $request)
    {
        $query = VerificationRequest::with(['user', 'link']);
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        $requests = $query->orderByDesc('created_at')->paginate(20);
        return view('user.verification.admin-index', compact('requests'));
    }

    public function adminReview(VerificationRequest $verificationRequest)
    {
        $verificationRequest->load(['user', 'link']);
        return view('user.verification.admin-review', compact('verificationRequest'));
    }

    public function adminApprove(Request $request, VerificationRequest $verificationRequest)
    {
        if ($verificationRequest->status !== 'pending') {
            return redirect()->route('user.verification.admin')
                ->with('error', 'This request has already been ' . $verificationRequest->status . '.');
        }

        $data = $request->validate([
            'admin_notes' => 'nullable|string|max:2000',
        ]);

        $verificationRequest->update([
            'status' => 'approved',
            'admin_notes' => $data['admin_notes'] ?? null,
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id(),
        ]);

        $link = $verificationRequest->link;
        $link->update([
            'is_verified' => true,
            'verified_name' => $verificationRequest->display_name,
            'verified_logo' => $verificationRequest->logo_path,
        ]);

        if ($link->type === 'biolink') {
            $settings = $link->settings ?? [];
            $settings['biolink']['title'] = $verificationRequest->display_name;
            $link->update([
                'title' => $verificationRequest->display_name,
                'settings' => $settings,
            ]);
        }

        $existingHeading = BiolinkBlock::where('link_id', $link->id)->where('type', 'verified_heading')->first();
        $existingAvatar = BiolinkBlock::where('link_id', $link->id)->where('type', 'verified_avatar')->first();

        if ($existingHeading) {
            $existingHeading->update([
                'settings' => array_merge($existingHeading->settings, [
                    'text' => $verificationRequest->display_name,
                    'verified' => true,
                    'locked_text' => true,
                ]),
                'is_active' => true,
            ]);
        } else {
            BiolinkBlock::create([
                'link_id' => $link->id,
                'type' => 'verified_heading',
                'is_active' => true,
                'sort_order' => 0,
                'settings' => [
                    'text' => $verificationRequest->display_name,
                    'verified' => true,
                    'locked_text' => true,
                ],
            ]);
        }

        if ($existingAvatar) {
            $existingAvatar->update([
                'settings' => array_merge($existingAvatar->settings, [
                    'image_url' => $this->logoUrl($verificationRequest->logo_path),
                    'verified' => true,
                    'locked_image' => true,
                ]),
                'is_active' => true,
            ]);
        } else {
            BiolinkBlock::create([
                'link_id' => $link->id,
                'type' => 'verified_avatar',
                'is_active' => true,
                'sort_order' => 1,
                'settings' => [
                    'image_url' => $this->logoUrl($verificationRequest->logo_path),
                    'verified' => true,
                    'locked_image' => true,
                ],
            ]);
        }

        if (!$existingHeading && !$existingAvatar) {
            BiolinkBlock::where('link_id', $link->id)
                ->whereNull('parent_id')
                ->whereNotIn('type', ['verified_heading', 'verified_avatar'])
                ->increment('sort_order', 2);
        }

        return redirect()->route('user.verification.admin')
            ->with('success', 'Verification approved. Verified blocks created.');
    }

    public function adminReject(Request $request, VerificationRequest $verificationRequest)
    {
        $data = $request->validate([
            'admin_notes' => 'required|string|max:2000',
        ]);

        $verificationRequest->update([
            'status' => 'rejected',
            'admin_notes' => $data['admin_notes'],
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id(),
        ]);

        return redirect()->route('user.verification.admin')
            ->with('success', 'Verification request rejected.');
    }

    public function toggleBlock(Request $request, BiolinkBlock $block)
    {
        $user = Auth::user();
        $link = Link::where('id', $block->link_id)->where('user_id', $user->id)->firstOrFail();

        if (!in_array($block->type, ['verified_heading', 'verified_avatar', 'verified_image'])) {
            return response()->json(['success' => false, 'message' => 'Not a verified block.'], 400);
        }

        $block->update(['is_active' => !$block->is_active]);

        return response()->json(['success' => true, 'is_active' => $block->is_active]);
    }

    /**
     * Convert a stored verification logo path into a renderable URL.
     * Vault paths begin with /f/ and are absolute; legacy public-disk
     * paths (e.g. "verification/logos/abc.png") are wrapped with asset().
     */
    private function logoUrl(?string $stored): ?string
    {
        if (! $stored) return null;
        if (str_starts_with($stored, '/f/') || str_starts_with($stored, 'http')) {
            return $stored;
        }
        return asset('storage/' . $stored);
    }
}
