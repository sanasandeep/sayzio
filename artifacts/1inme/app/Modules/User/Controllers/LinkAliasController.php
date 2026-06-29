<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkAlias;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Manage additional aliases for a Link. The "primary" alias remains on the
 * links.alias column; this controller only manages the *extra* aliases stored
 * in link_aliases. Plan limits (max_aliases_per_link) only count extras —
 * the primary alias is always free.
 */
class LinkAliasController extends Controller
{
    /**
     * Reserved top-level paths that must never be claimed as a public alias.
     * Mirrors the route regex constraint in routes/web.php.
     */
    private const RESERVED_ALIASES = [
        'user', 'admin', 'qr', 'storage', 'sanctum', 'api', 'f',
        'login', 'register', 'logout', 'password', 'verify-email',
        'home', 'dashboard', 'profile', 'settings', 'help', 'support',
        'terms', 'privacy', 'about', 'contact', 'pricing', 'plans',
    ];

    /** Public accessor so other controllers (e.g. primary alias edit) can mirror this check. */
    public static function reservedAliases(): array
    {
        return self::RESERVED_ALIASES;
    }

    public function store(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);

        $user = $request->user();
        // Cap is resolved per the TARGET link's type — a plan may allow, say,
        // 5 extra aliases on a biolink but only 1 on a short link.
        $maxExtras = $user->getMaxAliasesPerLink($link->type);
        $currentExtras = $link->aliases()->count();

        if ($maxExtras !== -1 && $currentExtras >= $maxExtras) {
            $typeLabel = \App\Modules\Common\Support\PlanFormCatalogue::aliasLinkTypes()[$link->type] ?? 'link';
            $msg = $maxExtras === 0
                ? "Your current plan does not include additional aliases for this {$typeLabel}. Upgrade to add custom alternative URLs."
                : "You've reached your plan's alias limit ({$maxExtras} extra " . ($maxExtras === 1 ? 'alias' : 'aliases') . " per {$typeLabel}). Upgrade for more.";
            return $this->respond($request, false, $msg, 403);
        }

        // Resolve the alias minimum through the owner's plan (free/unconfigured
        // users land on the largest minimum; paid tiers step down) so extra
        // aliases enforce the same floor as the primary alias.
        $aliasLimits = $user->getAliasLengthLimits();

        $validated = $request->validate([
            'alias' => [
                'required', 'string', 'min:' . $aliasLimits['min'], 'max:60',
                new \App\Modules\User\Rules\AliasFormat(),
            ],
        ]);

        $alias = $validated['alias'];

        if (in_array(strtolower($alias), self::RESERVED_ALIASES, true)) {
            return $this->respond($request, false, "'{$alias}' is a reserved name and cannot be used.", 422);
        }

        // Admin-managed banned names list. Holders of
        // `user.banned_names.bypass` skip this check.
        if (!$user->hasPermission('user.banned_names.bypass') && \App\Modules\Admin\Services\BannedNameChecker::isBanned($alias)) {
            return $this->respond($request, false, "This name is reserved and can't be used.", 422);
        }

        // Globally unique across both `links.alias` and `link_aliases.alias`,
        // matched case-insensitively so a case-variant of an existing alias is
        // rejected as taken (mirrors UniqueAliasCi + case-insensitive resolution).
        $lower = mb_strtolower($alias);
        $takenInPrimary = Link::whereRaw('LOWER(alias) = ?', [$lower])->exists();
        $takenInExtras  = LinkAlias::whereRaw('LOWER(alias) = ?', [$lower])->exists();
        if ($takenInPrimary || $takenInExtras) {
            return $this->respond($request, false, "'{$alias}' is already taken. Please choose another.", 422);
        }

        $row = LinkAlias::create([
            'link_id' => $link->id,
            'alias'   => $alias,
        ]);

        return $this->respond($request, true, 'Alias added.', 200, ['alias' => $row]);
    }

    public function destroy(Request $request, Link $link, LinkAlias $alias)
    {
        abort_if($link->user_id !== $request->user()->id, 403);
        abort_if($alias->link_id !== $link->id, 404);

        // MERGE behavior: when an alias is deleted, its historical clicks are
        // re-assigned to the link's primary alias so the numbers roll up into
        // the main total instead of being orphaned as a dangling label.
        $deletedAlias = $alias->alias;
        $primary = $link->alias;
        $merged = 0;

        DB::transaction(function () use ($link, $alias, $deletedAlias, $primary, &$merged) {
            if ($primary && $deletedAlias !== $primary) {
                $merged = DB::table('link_clicks')
                    ->where('link_id', $link->id)
                    ->where('alias', $deletedAlias)
                    ->update(['alias' => $primary]);
            }
            $alias->delete();
        });

        $msg = $merged > 0
            ? "Alias removed. {$merged} historical click" . ($merged === 1 ? '' : 's') . " merged into the primary alias."
            : 'Alias removed.';
        return $this->respond($request, true, $msg);
    }

    /**
     * Promote an extra alias to be the primary alias for the link.
     * The currently-primary alias is demoted into the link_aliases table so
     * existing visitor URLs keep working.
     */
    public function promote(Request $request, Link $link, LinkAlias $alias)
    {
        abort_if($link->user_id !== $request->user()->id, 403);
        abort_if($alias->link_id !== $link->id, 404);

        $oldPrimary = $link->alias;
        $newPrimary = $alias->alias;

        if ($oldPrimary === $newPrimary) {
            return $this->respond($request, true, 'Already the primary alias.');
        }

        DB::transaction(function () use ($link, $alias, $oldPrimary, $newPrimary) {
            // Free up the unique constraint by removing the new-primary row first.
            $alias->delete();
            // Switch the primary alias on the link itself.
            $link->update(['alias' => $newPrimary]);
            // Demote the old primary into the extras table so its URL still resolves.
            if ($oldPrimary) {
                LinkAlias::create(['link_id' => $link->id, 'alias' => $oldPrimary]);
            }
        });

        return $this->respond($request, true, "'{$newPrimary}' is now the primary alias.");
    }

    private function respond(Request $request, bool $ok, string $message, int $status = 200, array $extra = [])
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(array_merge(['success' => $ok, 'message' => $message], $extra), $ok ? $status : $status);
        }
        return back()->with($ok ? 'success' : 'error', $message);
    }
}
