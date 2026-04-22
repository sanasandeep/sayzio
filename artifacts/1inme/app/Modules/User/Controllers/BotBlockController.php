<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\BotDetector;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Per-user blocklist for bot families. When a creator blocks a family
 * (e.g. "GPTBot (OpenAI)"), LinkTrackingService drops matching hits
 * before they're persisted — so blocked bots are invisible everywhere
 * (totals, breakdowns, exports, badges, downstream events).
 */
class BotBlockController extends Controller
{
    public function index(BotDetector $detector)
    {
        $user = Auth::user();
        $blocked = $this->normalize($user->blocked_bot_families ?? []);
        $known = $detector->knownFamilies();

        // Surface anything the user blocked that isn't in the current
        // BOT_FAMILIES map (e.g. an old name we've since renamed) so they
        // can still see and remove it instead of it being orphaned.
        $available = array_values(array_diff($known, $blocked));
        $extras = array_values(array_diff($blocked, $known));

        return view('user.bot-blocks.index', [
            'blocked'   => $blocked,
            'available' => $available,
            'extras'    => $extras,
        ]);
    }

    public function store(Request $request, BotDetector $detector)
    {
        $family = trim((string) $request->input('family', ''));
        if ($family === '') {
            return back()->withErrors(['family' => 'Pick a bot family to block.']);
        }

        // Accept any classifier-known family OR an "Other …" bucket the
        // breakdown panel can produce (Other bot / Other crawler / Other
        // spider / Other scraper / Unknown (no UA)). Anything else is
        // refused so we don't pollute the list with arbitrary strings.
        $allowed = array_merge($detector->knownFamilies(), [
            'Other bot', 'Other crawler', 'Other spider', 'Other scraper',
            'Unknown (no UA)',
        ]);
        if (!in_array($family, $allowed, true)) {
            return back()->withErrors(['family' => 'Unknown bot family.']);
        }

        /** @var User $user */
        $user = Auth::user();
        $blocked = $this->normalize($user->blocked_bot_families ?? []);
        if (!in_array($family, $blocked, true)) {
            $blocked[] = $family;
        }

        $user->blocked_bot_families = array_values($blocked);
        $user->save();

        return back()->with('success', "Blocked {$family} from being recorded.");
    }

    public function destroy(Request $request, string $family)
    {
        /** @var User $user */
        $user = Auth::user();
        $blocked = $this->normalize($user->blocked_bot_families ?? []);
        $blocked = array_values(array_filter($blocked, fn ($f) => $f !== $family));

        $user->blocked_bot_families = $blocked;
        $user->save();

        return back()->with('success', "{$family} will be counted again going forward.");
    }

    /**
     * @param mixed $raw
     * @return array<int, string>
     */
    private function normalize($raw): array
    {
        if (!is_array($raw)) return [];
        $out = [];
        foreach ($raw as $v) {
            if (is_string($v) && $v !== '' && !in_array($v, $out, true)) {
                $out[] = $v;
            }
        }
        return $out;
    }
}
