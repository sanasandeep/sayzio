<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Models\DmcaTakedown;
use App\Modules\Common\Services\ViewerSession;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Public DMCA / IP takedown intake form (Task #1211). Lives at
 * /legal/dmca — the route name is in the public web routes
 * reserved-word allow-list so /legal can sit alongside /@handle.
 */
class DmcaController extends Controller
{
    public function show()
    {
        return view('public.dmca-form');
    }

    public function store(Request $request)
    {
        $rateKey = 'dmca:' . $request->ip();
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            return back()->with('error', 'Too many takedown requests from this network — please try again later.');
        }
        RateLimiter::hit($rateKey, 3600);

        $data = $request->validate([
            'reporter_name'                   => 'required|string|max:200',
            'reporter_email'                  => 'required|email|max:200',
            'reporter_address'                => 'nullable|string|max:500',
            'rights_holder'                   => 'nullable|string|max:200',
            'original_work_url'               => 'required|url|max:2000',
            'infringing_url'                  => 'required|url|max:2000',
            'good_faith_acknowledged'         => 'accepted',
            'penalty_of_perjury_acknowledged' => 'accepted',
            'signature'                       => 'required|string|max:200',
        ]);

        // Best-effort lookup of the target creator + post from the
        // infringing URL so admins can hit "Hide post" without doing
        // detective work first.
        [$targetUserId, $targetPostId] = $this->resolveInfringingTarget($data['infringing_url']);

        $viewer = ViewerSession::user() ?? auth()->user();

        DmcaTakedown::create([
            'reporter_user_id' => $viewer?->id,
            'reporter_name'    => $data['reporter_name'],
            'reporter_email'   => $data['reporter_email'],
            'reporter_address' => $data['reporter_address'] ?? null,
            'rights_holder'    => $data['rights_holder'] ?? null,
            'original_work_url'=> $data['original_work_url'],
            'infringing_url'   => $data['infringing_url'],
            'target_user_id'   => $targetUserId,
            'target_post_id'   => $targetPostId,
            'good_faith_acknowledged'         => true,
            'penalty_of_perjury_acknowledged' => true,
            'signature'        => $data['signature'],
            'reporter_ip'      => $request->ip(),
            'status'           => 'pending',
        ]);

        return view('public.dmca-thanks');
    }

    /**
     * Try to map the infringing URL to a creator + post id. Falls back
     * to (null, null) so the DB row still saves.
     */
    protected function resolveInfringingTarget(string $url): array
    {
        $parts = parse_url($url);
        $path = trim((string) ($parts['path'] ?? ''), '/');
        if ($path === '') return [null, null];

        // /@handle or /@handle/p/{id}
        if (str_starts_with($path, '@')) {
            $segments = explode('/', $path);
            $handle = ltrim($segments[0] ?? '', '@');
            $creator = $handle ? User::where('handle', strtolower($handle))->first() : null;
            $postId  = null;
            if ($creator && ($segments[1] ?? null) === 'p' && isset($segments[2])) {
                $postId = is_numeric($segments[2]) ? (int) $segments[2] : null;
                if ($postId) {
                    $exists = CreatorPost::query()->withoutGlobalScope('workspace')
                        ->where('user_id', $creator->id)->whereKey($postId)->exists();
                    if (!$exists) $postId = null;
                }
            }
            return [$creator?->id, $postId];
        }
        return [null, null];
    }
}
