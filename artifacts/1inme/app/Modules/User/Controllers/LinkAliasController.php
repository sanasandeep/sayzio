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
                'required', 'string', 'min:' . $aliasLimits['min'], 'max:' . $aliasLimits['max'],
                new \App\Modules\User\Rules\AliasFormat(),
            ],
            'domain_id' => ['nullable', $this->availableDomainRule($user)],
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

        // Unique per domain namespace across both `links.alias` and
        // `link_aliases.alias`, matched case-insensitively so a case-variant
        // of an existing alias is rejected as taken (mirrors UniqueAliasCi +
        // case-insensitive resolution). The same alias CAN be added again for
        // a different domain — that's the point of per-domain aliasing.
        $lower = mb_strtolower($alias);
        $targetDomainId = \App\Modules\User\Support\AliasNamespace::normalizeDomainId($validated['domain_id'] ?? null);
        // Distinguish "already yours on this domain" from "taken by someone
        // else" — an alias only serves on the domain it's bound to, so
        // re-adding it for ANOTHER domain is a legitimate action.
        $ownPrimary = mb_strtolower((string) $link->alias) === $lower
            && \App\Modules\User\Support\AliasNamespace::normalizeDomainId($link->domain_id) === $targetDomainId;
        $ownExtraQ = LinkAlias::whereRaw('LOWER(alias) = ?', [$lower])->where('link_id', $link->id);
        \App\Modules\User\Support\AliasNamespace::scope($ownExtraQ, $targetDomainId);
        if ($ownPrimary || $ownExtraQ->exists()) {
            return $this->respond($request, false, "'{$alias}' is already one of this page's aliases on that domain — there's nothing to add.", 422);
        }
        if (\App\Modules\User\Support\AliasNamespace::isTaken($alias, $validated['domain_id'] ?? null, $link->id)) {
            return $this->respond($request, false, "'{$alias}' is already taken on that domain. Please choose another.", 422);
        }

        $row = LinkAlias::create([
            'link_id'   => $link->id,
            'alias'     => $alias,
            'domain_id' => $validated['domain_id'] ?? null,
        ]);

        return $this->respond($request, true, 'Alias added.', 200, ['alias' => $row]);
    }

    /**
     * Change the custom domain an existing additional alias renders on.
     * Mirrors the primary alias's domain_id update but scoped to a
     * link_aliases row instead of the link itself.
     */
    public function updateDomain(Request $request, Link $link, LinkAlias $alias)
    {
        abort_if($link->user_id !== $request->user()->id, 403);
        abort_if($alias->link_id !== $link->id, 404);

        $validated = $request->validate([
            'domain_id' => ['nullable', $this->availableDomainRule($request->user())],
        ]);

        // Moving an alias to another domain must not collide with an alias
        // already living in the target domain's namespace (aliases are
        // unique per domain, case-insensitively, across both tables).
        if (\App\Modules\User\Support\AliasNamespace::isTaken(
            (string) $alias->alias,
            $validated['domain_id'] ?? null,
            null,
            $alias->id,
        )) {
            return $this->respond($request, false, "'{$alias->alias}' is already taken on that domain.", 422);
        }

        $alias->update(['domain_id' => $validated['domain_id'] ?? null]);

        return $this->respond($request, true, 'Domain updated.', 200, ['alias' => $alias]);
    }

    /**
     * Build a Validation rule that constrains domain_id to a domain the
     * user can actually attach. Mirrors LinkController::availableDomainRule
     * so primary and additional aliases enforce the exact same entitlement.
     */
    private function availableDomainRule(\App\Modules\User\Models\User $user): \Closure
    {
        return function ($attribute, $value, $fail) use ($user) {
            if (empty($value)) return;
            $allowed = \App\Modules\User\Models\Domain::availableTo($user)->pluck('id')->all();
            if (!in_array((int) $value, $allowed, true)) {
                $fail('That domain is not available on your plan.');
            }
        };
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

        // Domains travel with their alias: the promoted alias's domain
        // becomes the link's new primary domain, while the demoted old
        // primary keeps the domain the link previously had.
        $oldPrimaryDomainId = $link->domain_id;
        $newPrimaryDomainId = $alias->domain_id;

        DB::transaction(function () use ($link, $alias, $oldPrimary, $newPrimary, $oldPrimaryDomainId, $newPrimaryDomainId) {
            // Free up the unique constraint by removing the new-primary row first.
            $alias->delete();
            // Switch the primary alias (and its domain) on the link itself.
            $link->update(['alias' => $newPrimary, 'domain_id' => $newPrimaryDomainId]);
            // Demote the old primary into the extras table, carrying its old
            // domain along, so its URL still resolves on the same host.
            if ($oldPrimary) {
                LinkAlias::create(['link_id' => $link->id, 'alias' => $oldPrimary, 'domain_id' => $oldPrimaryDomainId]);
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
