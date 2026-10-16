        {{-- ───────────────── PLANS RAIL ─────────────────
             All plan cards live on one horizontal rail. They grow to
             fill the width when there's room and scroll horizontally
             (styled scrollbar + edge fades + hint) once their combined
             min-widths overflow the container — no responsive
             column-count logic to keep in sync with the plan count. --}}
        @php
            $planCount = count($plans);
            // All plan cards sit on a single horizontal rail (see the
            // `.plans-rail` / `.plans-scroll` styles). Each card keeps a
            // readable min-width and grows to fill spare space; when the
            // combined widths exceed the container the rail scrolls
            // horizontally, so there's no responsive column-count logic
            // to maintain regardless of how many tiers the seeder ships.
            $hasRec = isset($recommendation['recommendedPlan']) && $recommendation['recommendedPlan'];
            $recPlanId = $hasRec ? $recommendation['recommendedPlan']->id : null;
            $currentPlanId = $recommendation['currentPlan']->id ?? null;
            // True only when a *distinct* recommended card will be shown
            // (a recommendation exists and it isn't the user's current plan).
            // Drives single-highlight: when a recommended card carries the
            // accent, "Most popular" stays a badge-only secondary treatment.
            $showsRecommended = $hasRec && $recPlanId !== $currentPlanId;

            // Re-index so each card can reach the plan before it for the
            // Linktree-style "Everything in {previous}, plus:" delta list.
            $plansArr = $plans->values();

            // Does ANY plan carry a first-term intro discount (any currency ×
            // cycle)? When one does, every card reserves the intro badge +
            // "was/renews" slots so the headline price, billing note and the
            // CTA below stay on the same baseline whether or not a given card
            // shows an offer for the active currency/cycle. When none do, the
            // reservation is dropped so the price block stays tight.
            $anyIntro = $plansArr->contains(function ($r) {
                foreach (($r['prices'] ?? []) as $byCycle) {
                    foreach ((array) $byCycle as $p) {
                        if (!empty($p['intro'])) return true;
                    }
                }
                return false;
            });

        @endphp
        {{-- The plan cards are the page, and nothing named them.

             Each card's tier is an <h3>, and the nearest heading above the
             rail was the page's own <h1>, so the outline read h1 then h3 --
             the one heading-level skip on /pricing, and the same one on
             /coins, which renders this view too.

             The heading is visually hidden rather than drawn because the rail
             needs no title on screen: the cards say what they are. It exists
             for the outline and for a screen reader, which can now jump to the
             plans as a group instead of landing on "Starter" with nothing
             saying what Starter is one of. --}}
        {{-- ───────────────── PLAN BANDS ─────────────────
             One rail per group instead of one rail of everything. The rail
             markup below is unchanged; it simply renders once per band, with
             $plansArr rebound to that band's rows. Grouping comes from
             PlansAndAddonsSeeder::publicPlanGroups() via the controller.    --}}
        @foreach($planBands as $band)
            @php
                $plansArr = $band['rows'];
                $planCount = $plansArr->count();
            @endphp
            <div class="plan-band-heading" data-anim="fade-up">
                <h2 class="text-sm font-semibold uppercase tracking-[0.14em] text-gray-400">{{ $band['title'] }}</h2>
                @if($band['blurb'])
                    <p class="text-sm text-gray-500">{{ $band['blurb'] }}</p>
                @endif
                <span class="flex-1 h-px bg-white/10"></span>
            </div>
        <h2 class="sr-only">Plans</h2>
        <div data-anim="fade-up" class="plans-rail mt-8"
             x-data="{
                 scrollable: false,
                 update() {
                     const s = this.$refs.scroll;
                     if (!s) return;
                     const max = s.scrollWidth - s.clientWidth;
                     this.scrollable = max > 4;
                     this.$el.classList.toggle('can-scroll-left', s.scrollLeft > 4);
                     this.$el.classList.toggle('can-scroll-right', s.scrollLeft < max - 4);
                 }
             }"
             x-init="$nextTick(() => update()); window.addEventListener('resize', () => update());">
            <div class="plans-scroll" x-ref="scroll" @scroll="update()">
                <div class="plans-row @if(!empty($planGrid)) plans-row--grid @endif">
            @foreach($plansArr as $i => $row)
                @php
                    $plan = $row['model'];
                    $features = $plan->features ?? [];
                    $isPopular = $plan->is_popular;
                    $isCurrent = $currentPlanId === $plan->id;
                    $isRecommended = $recPlanId === $plan->id && !$isCurrent;
                    // Single-highlight: the recommended card takes the accent
                    // when one is shown; otherwise the "Most popular" card does.
                    // (Popular still keeps its badge either way.)
                    $isAccent = $isRecommended || (!$showsRecommended && $isPopular);
                    $cmpKind = auth()->check()
                        ? \App\Services\PlanRecommender::compare($recommendation['currentPlan'] ?? null, $plan)
                        : 'guest';
                    $planJs = $row['prices']; // { USD: {monthly, annual}, INR: {monthly, annual} }
                    $prevPlan = $i > 0 ? $plansArr[$i - 1]['model'] : null;
                    $limitLabel = function (string $key, string $one, string $many) use ($features) {
                        $value = (int) ($features[$key] ?? 0);
                        return $value === -1 ? 'Unlimited '.$many : number_format($value).' '.($value === 1 ? $one : $many);
                    };
                    $highlights = [
                        ['fa-id-badge', $limitLabel('max_biolinks', 'Link in Bio page', 'Link in Bio pages')],
                        ['fa-link', $limitLabel('max_links', 'short link', 'short links')],
                        !empty($features['custom_domains']) && (int) ($features['max_custom_domains'] ?? 0) !== 0
                            ? ['fa-globe', $limitLabel('max_custom_domains', 'custom domain', 'custom domains')]
                            : ['fa-chart-simple', ($features['analytics'] ?? 'basic') === 'advanced' ? 'Advanced analytics' : 'Click analytics'],
                        (int) ($features['included_coins_monthly'] ?? 0) > 0
                            ? ['fa-coins', number_format($features['included_coins_monthly']).' coins / month']
                            : ['fa-user', $limitLabel('max_seats_per_workspace', 'workspace seat', 'workspace seats')],
                    ];
                    $ctaFilled = $isAccent;
                    $borderClasses = $isCurrent
                        ? 'border-emerald-400/50'
                        : ($isRecommended
                            ? 'border-cyan-400/50'
                            : ($isPopular
                                ? 'border-blue-500/50'
                                : 'border-white/10'));
                    // Header-band tint mirrors the card emphasis.
                    $bandClass = $isCurrent ? 'is-current' : ($isAccent ? 'is-accent' : '');
                    // Per-tier header chip icon — adds a visual accent to the
                    // (otherwise plain) name/description band, picked by tier
                    // emphasis so every card reads consistently.
                    $isFreeTier = !empty($row['is_free']);
                    $tierIcon = $isCurrent ? 'fa-circle-check'
                        : ($isRecommended ? 'fa-wand-magic-sparkles'
                        : ($isPopular ? 'fa-star'
                        : ($isFreeTier ? 'fa-gift' : 'fa-gem')));
                @endphp
                <div
                    x-data='{ plan: @json($planJs) }'
                    id="{{ $pricingScope }}-plan-{{ $plan->id }}"
                    :class="matches.some(m => m.slug === '{{ $plan->slug }}') ? 'is-match' : ''"
                    class="sy-card compact-plan {{ $isAccent ? 'sy-card--lead sy-ribbon card-lit' : '' }} {{ $isCurrent ? 'sy-card--current' : '' }} plan-card">

                    <div class="sy-head">
                        <div class="sy-eyebrow">{{ $pricingNames[$plan->slug] ?? $plan->name }}</div>
                        @if($isCurrent)
                            <span class="sy-flag sy-flag--current"><i class="fas fa-circle-check"></i> Your plan</span>
                        @elseif($isRecommended)
                            <span class="sy-flag">Recommended</span>
                        @elseif($isPopular)
                            <span class="sy-flag">Most popular</span>
                        @elseif($isAccent)
                            <span class="sy-flag">More room to grow</span>
                        @endif
                    </div>

                    @php
                        // SSR price, so the number is right before Alpine
                        // hydrates. On the annual cycle the headline is the
                        // per-month equivalent, matching what the card says.
                        if ($cycle === 'annual' && empty($row['is_free'])) {
                            $annualMinor = (int) ($row['prices'][$currency]['annual']['amount_minor'] ?? 0);
                            $initialFormatted = $annualMinor > 0
                                ? \App\Services\PricingResolver::money((int) round($annualMinor / 12), $currency)
                                : ($row['prices'][$currency]['monthly']['formatted'] ?? '—');
                        } else {
                            $initialFormatted = $row['prices'][$currency][$cycle]['formatted'] ?? '—';
                        }
                    @endphp

                    <div class="sy-figure">
                        <span x-text="intro(plan) ? (cycle==='annual' ? introPerMonth(plan) : intro(plan).first_formatted) : (cycle==='annual' && hasAnnual(plan) ? perMonth(plan) : money(plan, cycle))">{{ $initialFormatted }}</span>
                        @unless(!empty($row['is_free']))
                            <span class="unit">/ mo</span>
                        @endunless
                    </div>

                    {{-- Intro offer, billing cadence and tax all read as one
                         quiet block under the number rather than three boxes
                         with their own reserved heights. --}}
                    <div class="sy-note">
                        <template x-if="intro(plan)">
                            <span class="sy-intro">
                                <i class="fas fa-bolt"></i>
                                <span x-text="(intro(plan)?.label) || ('Save ' + intro(plan)?.percent_off + '% first ' + (cycle==='annual' ? 'year' : 'month'))"></span>
                            </span>
                        </template>
                        <template x-if="intro(plan)">
                            <span class="block">
                                <span class="was" x-text="cycle==='annual' ? introNormalPerMonth(plan) : (intro(plan)?.normal_formatted ?? '')"></span>
                                renews at <span x-text="cycle==='annual' ? introNormalPerMonth(plan) : (intro(plan)?.normal_formatted ?? '')"></span>/mo
                            </span>
                        </template>
                        @if(!empty($row['is_free']))
                            <span class="block">Free forever, no card required</span>
                        @else
                            <span class="block" x-text="billingNote(plan)"></span>
                        @endif
                        @foreach(['USD','INR'] as $cur)
                            @foreach(['monthly','annual'] as $c)
                                @php $taxBlock = $row['tax'][$cur][$c] ?? null; @endphp
                                @if(($row['prices'][$cur][$c]['amount_minor'] ?? 0) > 0)
                                    <span class="block" x-show="currency==='{{ $cur }}' && cycle==='{{ $c }}'" x-cloak>
                                        @if(!empty($taxBlock) && !empty($taxBlock['tax_breakdown']))
                                            @foreach($taxBlock['tax_breakdown'] as $line)
                                                + {{ $line['label'] }} {{ \App\Services\PricingResolver::money((int) $line['amount_minor'], $cur) }}<br>
                                            @endforeach
                                            Total {{ \App\Services\PricingResolver::money((int) $taxBlock['grand_total_minor'], $cur) }}
                                        @else
                                            + taxes as applicable, shown at checkout.
                                        @endif
                                    </span>
                                @endif
                            @endforeach
                        @endforeach
                    </div>

                    <p class="sy-blurb plan-band-desc">{{ $plan->description }}</p>

                    {{-- The CTA sits above the feature list, not below it.
                         A visitor who has decided at the price should not have
                         to scroll past forty features to act. --}}
                    <div class="sy-foot sy-foot--lead">
                        @switch($cmpKind)
                            @case('current')
                                <span class="sy-cta sy-cta--held"><i class="fas fa-check"></i> Current plan</span>
                                @break
                            @case('upgrade')
                                <a :href="'{{ route('user.upgrade') }}?cycle=' + cycle" href="{{ route('user.upgrade', ['cycle' => $cycle]) }}" class="surface-lit-keep sy-cta">
                                    Upgrade to {{ $pricingNames[$plan->slug] ?? $plan->name }} <i class="fas fa-arrow-right"></i>
                                </a>
                                @break
                            @case('downgrade')
                                <a :href="'{{ route('user.upgrade') }}?cycle=' + cycle" href="{{ route('user.upgrade', ['cycle' => $cycle]) }}" class="surface-lit-keep sy-cta sy-cta--ghost">
                                    Downgrade to {{ $pricingNames[$plan->slug] ?? $plan->name }}
                                </a>
                                @break
                            @case('choose')
                                <a :href="'{{ route('user.upgrade') }}?cycle=' + cycle" href="{{ route('user.upgrade', ['cycle' => $cycle]) }}" class="surface-lit-keep sy-cta {{ $ctaFilled ? '' : 'sy-cta--ghost' }}">
                                    Choose {{ $pricingNames[$plan->slug] ?? $plan->name }}
                                </a>
                                @break
                            @default
                                <a href="{{ route('user.register') }}" class="surface-lit-keep sy-cta {{ $ctaFilled ? '' : 'sy-cta--ghost' }}">
                                    {{ !empty($row['is_free']) ? 'Get started free' : 'Start free trial' }} <i class="fas fa-arrow-right"></i>
                                </a>
                        @endswitch
                    </div>

                    <ul class="plan-highlights" aria-label="{{ $pricingNames[$plan->slug] ?? $plan->name }} highlights">
                        @foreach($highlights as [$icon, $label])
                            <li><i class="fas {{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span></li>
                        @endforeach
                    </ul>
                    <a class="plan-compare-link" href="#{{ $pricingScope }}-comparison">Compare all features <span aria-hidden="true">↗</span></a>

                </div>
            @endforeach
                </div>
            </div>
            @if($planCount > 1)
                <div x-show="scrollable" x-cloak class="mt-3 flex items-center justify-center gap-2 text-[11px] text-gray-400" aria-hidden="true">
                    <i class="fas fa-arrows-left-right sy-ico"></i>
                    Scroll to compare every plan
                </div>
            @endif
        </div>
        @endforeach

        {{-- Rule 3: sections are separated by a labelled rule, not by a
             guess at a margin. --}}
        <div id="{{ $pricingScope }}-comparison" class="sy-divider-label"><span>Compare every plan</span></div>

        {{-- ───────────────── COMPARE FEATURES AT A GLANCE ───────────────── --}}
        @php
            // Plan-vs-plan feature matrix. Reuses the same labels from
            // the per-card lists above so visitors can scan deltas without
            // hunting through cards.
            $matrixGroups = [
                'Limits' => [
                    ['max_links',         'Short links',          'number'],
                    ['max_biolinks',      'Link in Bio pages',    'number'],
                    ['max_projects',      'Projects',             'number'],
                    ['storage_limit_mb',  'Storage (MB)',         'number'],
                    ['contacts_max',      'Contacts',             'number'],
                    ['max_files',         'Files',                'number'],
                    ['max_forms',         'Forms',                'number'],
                ],
                'Content blocks' => [
                    ['block_types_allowed', 'Link in Bio block types',  'blocks'],
                ],
                'Link types' => [
                    ['max_conversational',   'Conversational pages',  'number'],
                    ['max_slides',           'Slides pages',          'number'],
                    ['max_ai_chat',          'AI Chatbot pages',      'number'],
                    ['max_restaurant_menu',  'Restaurant Menu pages', 'number'],
                    ['max_service_booking',  'Service Booking pages', 'number'],
                    ['max_reviews',          'Reviews pages',         'number'],
                    ['max_resume',           'Resume / portfolio pages','number'],
                ],
                'Growth & analytics' => [
                    ['analytics',                'Analytics depth',         'analytics'],
                    ['pixels',                   'Marketing pixels',        'bool'],
                    ['utm_params',               'UTM parameters',          'bool'],
                    ['max_custom_domains',       'Custom domains',          'number'],
                    ['seo_settings',             'SEO & social previews',   'bool'],
                ],
                'Per-link controls' => [
                    ['link_password',            'Password protection',     'bool'],
                    ['link_expiry',              'Link expiry',             'bool'],
                    ['link_geo_targeting',       'Geo targeting',           'bool'],
                    ['link_device_targeting',    'Device targeting',        'bool'],
                    ['link_deep_link',           'Deep links',              'bool'],
                    ['link_smart_rules',         'Smart redirect rules',    'bool'],
                ],
                'Monetization & teams' => [
                    ['ecommerce',                'Sell from your bio',      'bool'],
                    ['custom_forms',             'Custom forms',            'bool'],
                    ['paid_forms',               'Paid forms',              'bool'],
                    ['form_analytics_advanced',  'Advanced form analytics', 'bool'],
                    ['teams',                    'Team workspaces',         'bool'],
                    ['leads',                    'Leads capture',           'bool'],
                    ['vaults',                   'Credential vault',        'bool'],
                    ['creator_profile_public',   'Public creator profile',  'bool'],
                ],
                'AI features' => [
                    ['max_minds',                'AI Minds',      'number'],
                    ['max_personas',             'AI Agents',               'number'],
                    ['max_companions',           'Chat Widgets',            'number'],
                    ['ai_widget',                'Site Assistant widget',   'bool'],
                    ['ai_voice_assistant',       'AI Voice Assistant',      'bool'],
                    ['ask_coach',                'AI Coach',                'bool'],
                    ['card_scan',                'Card & Brochure Scanner', 'bool'],
                    ['ai_resume_tools',          'AI Resume Tools',         'bool'],
                    ['audience_type_estimation', 'Audience Insights',       'ai_access'],
                ],
                'Developer API' => [
                    ['api_access',               'API access',              'bool'],
                    ['api_calls_monthly',        'API calls / month',       'number'],
                    ['api_rate_per_min',         'API rate (calls / min)',  'number'],
                ],
                'Included coins' => [
                    ['included_coins_monthly',   'Coins / monthly cycle',   'number'],
                    ['included_coins_yearly',    'Coins / yearly cycle',    'number'],
                ],
            ];
            // Fixed (locked) column widths so every plan column lines up
            // uniformly; the matrix scroll box handles overflow when the
            // combined width exceeds the container.
            $colTpl = 'minmax(170px, 1.35fr) repeat(' . count($plans) . ', minmax(145px, 1fr))';
            $blockLabel = fn (string $slug): string => \App\Modules\Common\Support\PlanBlockLabels::label($slug);
            $renderCell = function ($plan, $key, $kind) use ($blockLabel) {
                $features = $plan->features ?? [];
                if ($kind === 'ai_access') {
                    // AI features gated by AiPlanAccess::featureAllowed()
                    // rather than a seeded plan-form key (e.g. Audience
                    // Insights / audience_type_estimation). Mirror its
                    // resolution: an explicit per-plan key wins; otherwise
                    // the feature is unlocked on any paid (non-free) plan.
                    $on = array_key_exists($key, $features)
                        ? !empty($features[$key])
                        : ($plan->slug ?? null) !== 'free';
                    return $on
                        ? '<span class="feat-mark feat-mark-yes" aria-label="Included"><i class="fas fa-check text-[11px]"></i></span>'
                        : '<span class="feat-mark feat-mark-no" aria-label="Not included"><i class="fas fa-minus text-[10px]"></i></span>';
                }
                if (!array_key_exists($key, $features) && $kind !== 'analytics') {
                    return '<span class="feat-mark feat-mark-no" aria-label="Not included"><i class="fas fa-minus text-[10px]"></i></span>';
                }
                $val = $features[$key] ?? null;
                if ($kind === 'blocks') {
                    if ($val === '*') {
                        return '<span class="font-semibold">All blocks</span>';
                    }
                    if (is_array($val) && count($val) > 0) {
                        $labels = [];
                        foreach ($val as $slug) {
                            if (!is_string($slug) || $slug === '') continue;
                            $labels[$blockLabel($slug)] = true;
                        }
                        $labels = array_keys($labels);
                        sort($labels, SORT_NATURAL | SORT_FLAG_CASE);
                        $count = count($labels);
                        if ($count === 0) {
                            return '<span class="feat-mark feat-mark-no" aria-label="Not included"><i class="fas fa-minus text-[10px]"></i></span>';
                        }
                        return '<span x-data="{ open: false }" class="inline-flex flex-col items-center gap-1">'
                             . '<button type="button" @click="open = !open" class="inline-flex items-center gap-1 text-white font-semibold transition" '
                             . ':aria-expanded="open" aria-label="' . e($count . ' blocks') . '">'
                             . $count . ' blocks <i class="fas fa-chevron-down text-[8px]" :class="open ? \'rotate-180\' : \'\'"></i>'
                             . '</button>'
                             . '<span x-show="open" x-cloak class="block text-[11px] text-gray-400 leading-snug">' . e(implode(', ', $labels)) . '</span>'
                             . '</span>';
                    }
                    return '<span class="feat-mark feat-mark-no" aria-label="Not included"><i class="fas fa-minus text-[10px]"></i></span>';
                }
                if ($kind === 'number') {
                    if ((int) $val === -1) return '<span class="font-semibold">Unlimited</span>';
                    if ((int) $val === 0)  return '<span class="feat-mark feat-mark-no" aria-label="Not included"><i class="fas fa-minus text-[10px]"></i></span>';
                    return '<span class="text-white font-semibold">' . number_format((int) $val) . '</span>';
                }
                if ($kind === 'analytics') {
                    $label = is_string($val) ? ucfirst($val) : 'Basic';
                    $cls = strtolower((string) $val) === 'advanced' ? 'font-semibold' : 'text-gray-300';
                    return '<span class="' . $cls . '">' . e($label) . '</span>';
                }
                return $val
                    ? '<span class="feat-mark feat-mark-yes" aria-label="Included"><i class="fas fa-check text-[11px]"></i></span>'
                    : '<span class="feat-mark feat-mark-no" aria-label="Not included"><i class="fas fa-minus text-[10px]"></i></span>';
            };
        @endphp
        <div class="pricing-comparison" data-anim="fade-up">
            <div class="text-center max-w-2xl mx-auto mb-6">
                <div class="sy-eyebrow">Side by side</div>
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">Compare features at a glance</h2>
                <p class="text-gray-400 mt-2">Compare the included features and limits side by side.</p>
            </div>
            <div class="glass-panel rounded-3xl overflow-hidden">
                <div class="feat-matrix-scroll">
                    <div class="feat-matrix grid" style="grid-template-columns: {{ $colTpl }};min-width:{{ 170 + count($plans) * 145 }}px;">
                        {{-- Header row --}}
                        <div class="feat-cell feat-head feat-row-name">Feature</div>
                        @foreach($plans as $row)
                            @php $p = $row['model']; @endphp
                            <div class="feat-cell feat-head text-center {{ $p->is_popular ? 'feat-popular-col' : '' }}">
                                <span class="text-white text-sm font-semibold normal-case tracking-normal">
                                    @if($p->is_popular)<i class="fas fa-star sy-ico text-[10px]"></i>@endif
                                    {{ $pricingNames[$p->slug] ?? $p->name }}
                                </span>
                            </div>

                        @endforeach

                        {{-- Grouped rows --}}
                        @foreach($matrixGroups as $groupName => $rows)
                            <div class="feat-cell feat-group" style="grid-column: span {{ count($plans) + 1 }};">{{ $groupName }}</div>
                            @foreach($rows as $ri => [$fkey, $flabel, $fkind])
                                @php $stripe = $ri % 2 === 1 ? 'feat-stripe' : ''; @endphp
                                <div class="feat-cell feat-row-name {{ $stripe }}">{{ $flabel }}</div>
                                @foreach($plans as $prow)
                                    @php $p = $prow['model']; @endphp
                                    <div class="feat-cell text-center {{ $stripe }} {{ $p->is_popular ? 'feat-popular-col' : '' }}">
                                        {!! $renderCell($p, $fkey, $fkind) !!}
                                    </div>
                                @endforeach
                            @endforeach
                        @endforeach
                    </div>
                </div>
                <div class="md:hidden text-center text-[11px] text-gray-500 px-4 py-3 bg-white/[.02] border-t border-white/5">
                    <i class="fas fa-arrows-left-right"></i> Swipe to see all plans
                </div>
            </div>
        </div>

