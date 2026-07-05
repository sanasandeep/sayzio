<?php

namespace App\Services\AI;

use App\Modules\User\Models\User;

/**
 * Live snapshots of a user's Sayzio data, exposed as compact text the
 * agent can reason over. Centralised here so the Persona task and the
 * Coach task both read from the same surface.
 *
 * Each adapter is intentionally small — it returns a short bullet
 * summary and (where useful) a few representative rows. Returning
 * thousands of rows would just blow up the prompt and the embedding
 * bill.
 */
class AiMindFeatureAdapter
{
    /** Whitelist of selectable per-USER features + human labels. */
    public const FEATURES = [
        'biolinks'    => 'Link in Bio',
        'links'       => 'Short Links',
        'analytics'   => 'Analytics',
        'payments'    => 'Payments & Wallet',
        'audience'    => 'Followers & Subscribers',
        'vault'       => 'Vault',
        'tasks'       => 'Task Boards',
        'forms'       => 'Forms',
        'inbox'       => 'Inbox',
        'social'      => 'Social Connections',
        'profile'     => 'Profile',
        // Task #3523 — grounding for AI Staff's billing & contacts domains.
        'billing'     => 'Billing & Invoices',
        'contacts'    => 'Contacts & Leads',
        // Task #3611 — grounding in live calendar/event + ticketing data.
        'events'      => 'Events & Calendar',
    ];

    /**
     * PUBLIC, account-independent snapshots. These read ONLY the public
     * pricing/feature catalogue (the exact same active + public-scoped
     * sources that power the marketing /pricing and /features pages), so
     * they carry no user data and are identical for everyone — anonymous
     * visitors and signed-in users alike. They're produced without an
     * owner (see publicSnapshot()) and are intended to be attached to the
     * platform default Mind so the Site Assistant always reflects current
     * public offerings. They are deliberately NOT offered in the per-user
     * feature picker (which iterates FEATURES only).
     */
    public const PUBLIC_FEATURES = [
        'pricing'  => 'Live Pricing & Coins',
        'features' => 'Feature Catalog',
    ];

    public static function isFeature(string $key): bool
    {
        return array_key_exists($key, self::FEATURES)
            || array_key_exists($key, self::PUBLIC_FEATURES);
    }

    /**
     * True for snapshots that read only public/active catalogue data and
     * never touch a user account — so they can be served to anyone,
     * including anonymous visitors, with no leakage risk.
     */
    public static function isPublicFeature(string $key): bool
    {
        return array_key_exists($key, self::PUBLIC_FEATURES);
    }

    public static function label(string $key): string
    {
        return self::FEATURES[$key] ?? self::PUBLIC_FEATURES[$key] ?? $key;
    }

    /**
     * Returns a short text snapshot the LLM can splice into prompts.
     * Empty string means "no data / feature unavailable for this user".
     */
    public function snapshot(User $user, string $key): string
    {
        return match ($key) {
            'biolinks'  => $this->biolinks($user),
            'links'     => $this->links($user),
            'analytics' => $this->analytics($user),
            'payments'  => $this->payments($user),
            'audience'  => $this->audience($user),
            'vault'     => $this->vault($user),
            'tasks'     => $this->tasks($user),
            'forms'     => $this->forms($user),
            'inbox'     => $this->inbox($user),
            'social'    => $this->social($user),
            'profile'   => $this->profile($user),
            'billing'   => $this->billing($user),
            'contacts'  => $this->contacts($user),
            'events'    => $this->events($user),
            default     => '',
        };
    }

    /**
     * Account-independent snapshot for the PUBLIC_FEATURES keys. Takes no
     * user — every byte it returns comes from the public, active pricing /
     * feature catalogue, so the result is identical regardless of who is
     * asking. This is what lets the Site Assistant answer pricing/feature
     * questions for anonymous visitors without any data-leakage risk.
     */
    public function publicSnapshot(string $key): string
    {
        return match ($key) {
            'pricing'  => $this->pricing(),
            'features' => $this->featureCatalogue(),
            default    => '',
        };
    }

    /**
     * Live pricing snapshot — active, public (non-internal) plans with
     * their per-currency monthly/annual prices, plus active coin packages
     * with coin amount + bonus. Mirrors PricingPagesController::plans()
     * exactly (same `Plan::active()->public()` / `CoinPackage::active()`
     * scopes and the same PricingResolver price resolution) so it can
     * never surface an internal, archived, or inactive offering.
     */
    protected function pricing(): string
    {
        $currencies = ['USD', 'INR'];

        $plans = \App\Modules\Admin\Models\Plan::active()->public()->with('prices')->ordered()->get();
        $lines = ['Live Sayzio plans (current public pricing, matches the /pricing page):'];
        foreach ($plans as $plan) {
            $priceBits = [];
            foreach ($currencies as $cur) {
                $monthly = \App\Services\PricingResolver::priceForCurrency($plan, $cur, 'monthly');
                $annual  = \App\Services\PricingResolver::priceForCurrency($plan, $cur, 'annual');
                $priceBits[] = sprintf('%s/mo or %s/yr', $monthly['formatted'], $annual['formatted']);
            }
            $tags = [];
            if ($plan->is_default) $tags[] = 'free/default';
            if ($plan->is_popular) $tags[] = 'most popular';
            $suffix = $tags ? ' (' . implode(', ', $tags) . ')' : '';
            $lines[] = sprintf('- %s%s: %s', $plan->name, $suffix, implode(' | ', $priceBits));
        }
        if ($plans->isEmpty()) {
            $lines[] = '- (No public plans are currently available.)';
        }

        $packages = \App\Modules\Admin\Models\CoinPackage::active()->with('prices')->ordered()->get();
        $lines[] = '';
        $lines[] = 'Coin packages (current public packs):';
        foreach ($packages as $pkg) {
            $priceBits = [];
            foreach ($currencies as $cur) {
                $pc = \App\Services\PricingResolver::priceForCurrency($pkg, $cur, 'monthly');
                $priceBits[] = $pc['formatted'];
            }
            $bonus = (int) $pkg->bonus_coins;
            $coins = $bonus > 0
                ? sprintf('%d coins + %d bonus = %d total', (int) $pkg->coin_amount, $bonus, $pkg->totalCoins())
                : sprintf('%d coins', (int) $pkg->coin_amount);
            $lines[] = sprintf('- %s: %s — %s', $pkg->name, $coins, implode(' / ', $priceBits));
        }
        if ($packages->isEmpty()) {
            $lines[] = '- (No coin packages are currently available.)';
        }

        return implode("\n", $lines);
    }

    /**
     * Live feature-catalogue snapshot — the public premium-feature list
     * (name, plain-English description, group) plus which public plans
     * unlock each feature. Reuses PremiumFeatures (the same catalogue the
     * /features page renders) and the `Plan::active()->public()` scope for
     * the unlock map, so internal/inactive plans never appear.
     */
    protected function featureCatalogue(): string
    {
        $plans   = \App\Modules\Admin\Models\Plan::active()->public()->ordered()->get();
        $unlocks = \App\Modules\Common\Support\PremiumFeatures::unlocksByFeature($plans);
        $planNames = $plans->pluck('name', 'slug');

        $byGroup = [];
        foreach (\App\Modules\Common\Support\PremiumFeatures::catalogue() as $entry) {
            $byGroup[$entry['group']][] = $entry;
        }

        $lines = ['Live Sayzio feature catalog (matches the /features page):'];
        foreach ($byGroup as $group => $entries) {
            $lines[] = '';
            $lines[] = $group . ':';
            foreach ($entries as $entry) {
                $slugs = $unlocks[$entry['key']] ?? [];
                $names = array_values(array_filter(array_map(
                    fn ($s) => $planNames[$s] ?? null,
                    $slugs
                )));
                $unlock = $names ? ' [plans: ' . implode(', ', $names) . ']' : '';
                $desc = \Illuminate\Support\Str::limit((string) ($entry['description'] ?? ''), 180);
                $lines[] = sprintf('- %s — %s%s', $entry['name'], $desc, $unlock);
            }
        }

        return implode("\n", $lines);
    }

    protected function biolinks(User $user): string
    {
        $links = $user->links()
            ->whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)
            ->latest('updated_at')
            ->limit(20)
            ->get(['id','title','alias','clicks','active','updated_at']);
        if ($links->isEmpty()) return 'You have no Link in Bio pages.';
        $lines = ["Link in Bio ({$links->count()} most-recent):"];
        foreach ($links as $l) {
            $lines[] = sprintf('- %s (alias %s) — %d clicks, %s',
                $l->title ?? 'Untitled', $l->alias, (int) $l->clicks,
                ($l->active ?? true) ? 'active' : 'paused');
        }
        return implode("\n", $lines);
    }

    protected function links(User $user): string
    {
        $links = $user->links()
            ->whereNotIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)
            ->latest('updated_at')
            ->limit(30)
            ->get(['id','type','title','alias','target_url','clicks','active']);
        if ($links->isEmpty()) return 'You have no short/file/event links.';
        $lines = ["Short links ({$links->count()}):"];
        foreach ($links as $l) {
            $lines[] = sprintf('- [%s] %s → %s (%d clicks)',
                $l->type, $l->alias, $l->target_url ?? '(internal)', (int) $l->clicks);
        }
        return implode("\n", $lines);
    }

    protected function analytics(User $user): string
    {
        $totalClicks = (int) $user->links()->sum('clicks');
        $totalLinks  = (int) $user->links()->count();
        $top = $user->links()
            ->orderByDesc('clicks')
            ->limit(5)
            ->get(['title','alias','clicks']);
        $lines = [
            "Analytics summary:",
            "- {$totalLinks} total links, {$totalClicks} cumulative clicks.",
        ];
        if ($top->isNotEmpty()) {
            $lines[] = "- Top performers:";
            foreach ($top as $l) {
                $lines[] = sprintf('   • %s (%s) — %d clicks', $l->title ?? '—', $l->alias, (int) $l->clicks);
            }
        }
        return implode("\n", $lines);
    }

    protected function payments(User $user): string
    {
        try {
            $wallet = app(\App\Services\Billing\WalletService::class);
            $coins  = (int) $wallet->getBalance($user);
        } catch (\Throwable $e) {
            $coins = 0;
        }
        try {
            $credits = (int) app(AiUsageCharger::class)->getBalance($user);
        } catch (\Throwable $e) {
            $credits = 0;
        }
        $plan = $user->plan_id ? "plan #{$user->plan_id}" : 'free plan';
        return "Payments — wallet coins: {$coins}; AI credits: {$credits}; current {$plan}.";
    }

    protected function audience(User $user): string
    {
        $followers   = (int) ($user->followers_count ?? 0);
        $subscribers = method_exists($user, 'subscribers')
            ? (int) $user->subscribers()->count() : 0;
        return "Audience — followers: {$followers}; subscribers: {$subscribers}.";
    }

    protected function vault(User $user): string
    {
        // Vault contents are encrypted on purpose — surface only counts.
        $clientCount = method_exists($user, 'vaultClients')
            ? (int) $user->vaultClients()->count() : 0;
        $credCount   = method_exists($user, 'vaultCredentials')
            ? (int) $user->vaultCredentials()->count() : 0;
        return "Vault — {$clientCount} clients, {$credCount} stored credentials. (Secrets stay encrypted; never include their values.)";
    }

    protected function tasks(User $user): string
    {
        if (!method_exists($user, 'taskBoards')) return '';
        $boards = $user->taskBoards()->limit(10)->get(['id','title']);
        if ($boards->isEmpty()) return 'No task boards yet.';
        $lines = ['Task boards:'];
        foreach ($boards as $b) $lines[] = "- {$b->title}";
        return implode("\n", $lines);
    }

    protected function forms(User $user): string
    {
        if (!method_exists($user, 'forms')) return '';
        $forms = $user->forms()->limit(10)->get(['id','name','active']);
        if ($forms->isEmpty()) return 'No forms yet.';
        $lines = ['Forms:'];
        foreach ($forms as $f) {
            $lines[] = sprintf('- %s (%s)', $f->name ?? 'Untitled', ($f->active ?? true) ? 'live' : 'paused');
        }
        return implode("\n", $lines);
    }

    protected function inbox(User $user): string
    {
        // Just the count — message bodies might contain PII the user
        // didn't consent to embed in an LLM prompt.
        try {
            $unread = (int) \App\Modules\User\Models\InboxReply::query()
                ->whereHas('conversation', fn($q) => $q->where('user_id', $user->id))
                ->where('status', 'open')
                ->count();
        } catch (\Throwable $e) {
            $unread = 0;
        }
        return "Inbox — {$unread} open items.";
    }

    protected function social(User $user): string
    {
        try {
            $accounts = \App\Modules\User\Models\SocialAccount::where('user_id', $user->id)
                ->get(['provider','status','followers_count']);
            if ($accounts->isEmpty()) return 'No social accounts connected.';
            $lines = ['Social accounts:'];
            foreach ($accounts as $a) {
                $lines[] = sprintf('- %s — %s (%s followers)',
                    $a->provider, $a->status ?? 'connected', $a->followers_count ?? '—');
            }
            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Billing snapshot for AI Staff's billing domain: open/unpaid/overdue
     * client invoice counts plus a short list of the most pressing overdue
     * ones (number, client, amount, due date). Deliberately excludes
     * recipient emails / notes — the model doesn't need them to draft or
     * chase invoices, only their identifiers and amounts.
     */
    protected function billing(User $user): string
    {
        $invoices = \App\Modules\User\Models\Invoice::query()
            ->where('user_id', $user->id)
            ->where('kind', 'client')
            ->get(['id', 'number', 'status', 'grand_total_minor', 'amount_paid_minor', 'currency', 'due_date', 'sent_at', 'vault_client_id']);

        if ($invoices->isEmpty()) {
            return 'You have no client invoices yet.';
        }

        $unpaid = $invoices->filter(fn ($i) => !in_array($i->status, ['paid', 'refunded', 'partially_refunded'], true));
        $overdue = $unpaid->filter(fn ($i) => $i->due_date && $i->due_date->isPast());

        $lines = [sprintf(
            'Client invoices — %d total, %d unpaid, %d overdue:',
            $invoices->count(), $unpaid->count(), $overdue->count()
        )];

        $clientNames = \App\Modules\User\Models\VaultClient::query()
            ->whereIn('id', $overdue->pluck('vault_client_id')->filter()->unique())
            ->pluck('name', 'id');

        foreach ($overdue->sortBy('due_date')->take(5) as $inv) {
            $balance = max(0, (int) $inv->grand_total_minor - (int) $inv->amount_paid_minor);
            $lines[] = sprintf(
                '- %s — %s owed by %s, due %s (%s)',
                $inv->number,
                number_format($balance / 100, 2) . ' ' . strtoupper((string) $inv->currency),
                $clientNames[$inv->vault_client_id] ?? 'unknown client',
                $inv->due_date?->format('M j, Y') ?? '—',
                $inv->sent_at ? 'sent' : 'not yet sent'
            );
        }

        return implode("\n", $lines);
    }

    /**
     * Contacts snapshot for AI Staff's contacts domain: a count plus a
     * sample of name/organization/tags for the most recently touched
     * contacts. Deliberately excludes emails/phones (PII-avoidance
     * pattern used elsewhere in this file, e.g. vault()/inbox()).
     */
    protected function contacts(User $user): string
    {
        $contacts = \App\Modules\User\Models\Contact::query()
            ->where('user_id', $user->id)
            ->latest('updated_at')
            ->limit(15)
            ->get(['id', 'display_name', 'given_name', 'family_name', 'organization', 'tags']);

        if ($contacts->isEmpty()) {
            return 'You have no contacts yet.';
        }

        $total = \App\Modules\User\Models\Contact::query()->where('user_id', $user->id)->count();
        $lines = ["Contacts — {$total} total (most recently touched):"];
        foreach ($contacts as $c) {
            $name = $c->nameForDisplay();
            $org = $c->organization ? " ({$c->organization})" : '';
            $tags = !empty($c->tags) ? ' [' . implode(', ', (array) $c->tags) . ']' : '';
            $lines[] = "- {$name}{$org}{$tags}";
        }

        return implode("\n", $lines);
    }

    /**
     * Events & Calendar snapshot for Task #3611: a compact summary of the
     * user's followable-calendar events ({@see CalendarEvent}, denormalized
     * `user_id`) plus their single-invite ticketed/RSVP-able `ics` event
     * links, so the AI can answer "what's my next event?" / "how many
     * tickets have I sold?" from live data. Upcoming events (soonest
     * first) are prioritized, then a few of the most recent past events
     * fill any remaining slots. Ticket/RSVP figures reuse the existing
     * count helpers ({@see EventCheckinProgress}) rather than
     * reimplementing tallies, and only ever surface counts — never
     * attendee names/emails/phones (same PII-avoidance convention as
     * billing()/contacts()). Guarded against calendar/ticketing tables
     * being absent on older/partial installs.
     */
    protected function events(User $user): string
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('calendar_events')) {
                return '';
            }

            $now = now();
            $entries = [];

            $calendarEvents = \App\Modules\User\Models\CalendarEvent::query()
                ->where('user_id', $user->id)
                ->get(['id', 'title', 'start_at', 'timezone', 'location']);

            foreach ($calendarEvents as $e) {
                $entries[] = [
                    'title'     => $e->title,
                    'start_at'  => $e->start_at,
                    'tz'        => $e->effectiveTimezone(),
                    'location'  => $e->location,
                    'ticketing' => null,
                    'tiers'     => null,
                ];
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('ics_data')) {
                $ticketedLinks = $user->links()
                    ->where('type', 'ics')
                    ->with('icsData')
                    ->get(['id', 'user_id', 'title', 'alias']);

                $hasTierTable = \Illuminate\Support\Facades\Schema::hasTable('event_ticket_tiers');
                $hasRsvpTable = \Illuminate\Support\Facades\Schema::hasTable('rsvps');

                foreach ($ticketedLinks as $link) {
                    $ics = $link->icsData;
                    if (!$ics || !$ics->start_date) {
                        continue;
                    }

                    $ticketing = null;
                    $tierBreakdown = null;
                    if ($hasTierTable && $link->eventTicketTiers()->exists()) {
                        $progress = \App\Services\Events\EventCheckinProgress::for($link);
                        $tierModels = $link->eventTicketTiers()
                            ->orderBy('sort_order')
                            ->get(['id', 'name', 'capacity']);
                        $capacity = (int) $tierModels->sum('capacity');
                        $ticketing = sprintf(
                            '%d sold%s, %d checked in',
                            $progress['totals']['sold'],
                            $capacity > 0 ? " of {$capacity}" : '',
                            $progress['totals']['checked_in']
                        );

                        // Per-tier sold/capacity breakdown when the event has
                        // more than one priced tier, so the AI can answer
                        // "how's VIP selling?" rather than only the total.
                        // Counts-only, no attendee PII (same convention as the
                        // total above).
                        if ($tierModels->count() > 1) {
                            $soldByTier = collect($progress['tiers'])->keyBy('id');
                            $tierBits = [];
                            foreach ($tierModels as $tier) {
                                $sold = (int) ($soldByTier[$tier->id]['sold'] ?? 0);
                                if ($tier->capacity === null) {
                                    // Unbounded tier — no capacity to compare against,
                                    // so no "almost sold out" / "sold out" flag.
                                    $tierBits[] = sprintf('%s %d sold', $tier->name, $sold);
                                    continue;
                                }
                                // At-a-glance capacity flag so the AI can answer
                                // "which tiers should I add more capacity to?"
                                // without eyeballing the ratios. Uses the same
                                // sold/capacity figures shown; counts-only, no PII.
                                $cap = (int) $tier->capacity;
                                $flag = '';
                                if ($cap > 0) {
                                    if ($sold >= $cap) {
                                        $flag = ' (sold out)';
                                    } elseif ($sold / $cap >= 0.9) {
                                        $flag = ' (90%+ full)';
                                    }
                                }
                                $tierBits[] = sprintf('%s %d/%d%s', $tier->name, $sold, $cap, $flag);
                            }
                            $tierBreakdown = implode(', ', $tierBits);
                        }
                    } elseif ($hasRsvpTable) {
                        $totalRsvps = $link->rsvps()->count();
                        if ($totalRsvps > 0) {
                            $going = $link->rsvps()
                                ->where('response', 'yes')
                                ->where('status', '!=', 'cancelled')
                                ->count();
                            $ticketing = "{$going} RSVP'd yes ({$totalRsvps} responses)";
                        }
                    }

                    $entries[] = [
                        'title'     => $link->title ?: ('@' . $link->alias),
                        'start_at'  => $ics->start_date,
                        'tz'        => \App\Support\PlatformTimezone::resolve($ics->timezone),
                        'location'  => $ics->location,
                        'ticketing' => $ticketing,
                        'tiers'     => $tierBreakdown,
                    ];
                }
            }

            if (empty($entries)) {
                return 'You have no events yet.';
            }

            $upcoming = collect($entries)
                ->filter(fn ($e) => $e['start_at'] && $e['start_at']->gte($now))
                ->sortBy('start_at')
                ->values();
            $recent = collect($entries)
                ->filter(fn ($e) => !$e['start_at'] || $e['start_at']->lt($now))
                ->sortByDesc('start_at')
                ->values();

            $selected = $upcoming->take(6);
            $remaining = 8 - $selected->count();
            if ($remaining > 0) {
                $selected = $selected->concat($recent->take($remaining));
            }

            $lines = [sprintf('Events — %d total, %d upcoming:', count($entries), $upcoming->count())];
            foreach ($selected as $e) {
                $when = $e['start_at']
                    ? $e['start_at']->copy()->setTimezone($e['tz'])->format('M j, Y g:ia') . ' ' . $e['tz']
                    : 'date TBD';
                $loc = $e['location'] ? " @ {$e['location']}" : '';
                $ticket = $e['ticketing'] ? " — {$e['ticketing']}" : '';
                $lines[] = "- {$e['title']}{$ticket} ({$when}{$loc})";
                if (!empty($e['tiers'])) {
                    $lines[] = "   • tiers: {$e['tiers']}";
                }
            }

            return implode("\n", $lines);
        } catch (\Throwable $ex) {
            return '';
        }
    }

    protected function profile(User $user): string
    {
        $bits = array_filter([
            $user->name ? "Name: {$user->name}" : null,
            $user->handle ? "Handle: @{$user->handle}" : null,
            $user->email ? "Email: {$user->email}" : null,
            $user->bio ? "Bio: " . \Illuminate\Support\Str::limit($user->bio, 240) : null,
            $user->country ? "Country: {$user->country}" : null,
            $user->timezone ? "Timezone: {$user->timezone}" : null,
        ]);
        return $bits ? "Profile:\n- " . implode("\n- ", $bits) : '';
    }
}
