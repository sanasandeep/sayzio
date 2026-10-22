{{--
    AI: the AI group, verbatim. Nine items, the largest single group
    in the sidebar, and the one that most wanted a panel of its own.
--}}
                {{-- ========== AI (collapsible) ========== --}}
                @php
                    $grpAiActive = nav_route_is('user.ai.*') || nav_route_is('user.minds.*') || nav_route_is('user.ai-personas.*') || nav_route_is('user.ai-companions.*');
                    $__aiEngineOff = !\App\Services\AI\AiEngineSettings::isEnabled();
                @endphp
                <div x-data="{ open: {{ $grpAiActive ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                            class="sidebar-group-toggle section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                        <span class="inline-flex items-center gap-1.5">
                            AI
                            @if($__aiEngineOff)
                                <span class="navrail-soft-pill rounded-full px-1.5 py-0.5 text-[9px] font-semibold normal-case tracking-normal"
                                      title="An administrator has turned the AI engine off">Off</span>
                            @endif
                        </span>
                        <i class="fas fa-chevron-down grp-chevron"></i>
                    </button>
                    <div x-show="open || sidebarMode === 'icons'" x-cloak>
                        <a href="{{ route('user.ai.mind.show') }}"
                           class="sidebar-link {{ nav_route_is('user.ai.mind.*') ? 'active' : '' }}"
                           style="--nav-tint:#5c83ff; --nav-tint-soft:rgba(92,131,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-brain"></i></div>
                            <span class="nav-label">AI Planner</span>
                            <span class="sidebar-tooltip">AI Planner</span>
                        </a>
                        @if($__can['settings_view'])
                        <a href="{{ route('user.minds.index') }}"
                           class="sidebar-link {{ nav_route_is('user.minds.*') ? 'active' : '' }}"
                           style="--nav-tint:#22d3ee; --nav-tint-soft:rgba(34,211,238,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-network-wired"></i></div>
                            <span class="nav-label">AI Minds</span>
                            <span class="sidebar-tooltip">AI Minds</span>
                        </a>
                        @endif
                        <a href="{{ route('user.ai.persona.show') }}"
                           class="sidebar-link {{ nav_route_is('user.ai.persona.*') ? 'active' : '' }}"
                           style="--nav-tint:#90acff; --nav-tint-soft:rgba(144,172,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-user-pen"></i></div>
                            <span class="nav-label">AI Persona Generator</span>
                            <span class="sidebar-tooltip">AI Persona Generator</span>
                        </a>
                        <a href="{{ route('user.ai-personas.index') }}"
                           class="sidebar-link {{ nav_route_is('user.ai-personas.*', 'user.ai.staff.*') ? 'active' : '' }}"
                           style="--nav-tint:#f472b6; --nav-tint-soft:rgba(244,114,182,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-user-astronaut"></i></div>
                            <span class="nav-label">Agents</span>
                            <span class="sidebar-tooltip">Agents</span>
                        </a>

                        <a href="{{ route('user.ai-companions.index') }}"
                           class="sidebar-link {{ nav_route_is('user.ai-companions.*') ? 'active' : '' }}"
                           style="--nav-tint:#5c83ff; --nav-tint-soft:rgba(92,131,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-robot"></i></div>
                            <span class="nav-label">Channels</span>
                            <span class="sidebar-tooltip">Channels</span>
                        </a>
                        <a href="{{ route('user.brand-kits.index') }}"
                           class="sidebar-link {{ nav_route_is('user.brand-kits.*') ? 'active' : '' }}"
                           style="--nav-tint:#3d6bff; --nav-tint-soft:rgba(61,107,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-palette"></i></div>
                            <span class="nav-label">Brand Kits</span>
                            <span class="sidebar-tooltip">Brand Kits</span>
                        </a>
                        <a href="{{ (Route::has('user.marketing-studio.index')?route('user.marketing-studio.index'):route('user.brand-studio.index')) }}"
                           class="sidebar-link {{ nav_route_is('user.marketing-studio.*','user.brand-studio.*','user.ai.marketing-strategist.*') ? 'active' : '' }}"
                           style="--nav-tint:#2fb4ff; --nav-tint-soft:rgba(47,180,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-wand-magic-sparkles"></i></div>
                            <span class="nav-label">AI Marketing Studio</span>
                            <span class="sidebar-tooltip">AI Marketing Studio</span>
                        </a>
                        @if(auth()->check())
                        <a href="{{ \App\Services\AI\AiEngineSettings::askCoachAllowedFor(auth()->user()) ? route('user.ai.ask-coach.show') : route('user.ai.companion.show') }}"
                           class="sidebar-link {{ nav_route_is('user.ai.ask-coach.*', 'user.ai.companion.*') ? 'active' : '' }}"
                           style="--nav-tint:#90acff; --nav-tint-soft:rgba(144,172,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-comment-dots"></i></div>
                            <span class="nav-label">Ask Zio</span>
                            <span class="sidebar-tooltip">Ask Zio</span>
                        </a>
                        @endif
                        @if(auth()->check() && \App\Services\AI\AiEngineSettings::isEnabled() && \App\Services\AI\AiPlanAccess::featureAllowed(auth()->user(), 'marketing_strategist'))
                        @endif
                        @if(auth()->check() && auth()->user()->planFeatureEnabled('marketing_plan_calculator'))
                        <a href="{{ route('user.marketing-plan.index') }}"
                           class="sidebar-link {{ nav_route_is('user.marketing-plan.*') ? 'active' : '' }}"
                           style="--nav-tint:#60a5fa; --nav-tint-soft:rgba(96,165,250,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-calculator"></i></div>
                            <span class="nav-label">Budget & Forecast</span>
                            <span class="sidebar-tooltip">Budget & Forecast</span>
                        </a>
                        @endif
                        @if(auth()->check() && \App\Services\AI\AiEngineSettings::isEnabled() && (\App\Services\AI\AiPlanAccess::featureAllowed(auth()->user(), 'ai_staff_billing') || \App\Services\AI\AiPlanAccess::featureAllowed(auth()->user(), 'ai_staff_contacts') || \App\Services\AI\AiPlanAccess::featureAllowed(auth()->user(), 'ai_staff_general')))

                        @endif
                    </div>
                </div>

