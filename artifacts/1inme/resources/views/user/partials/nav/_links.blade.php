{{--
    Links: the Links & Pages group, verbatim.
--}}
                {{-- ========== LINKS & PAGES (collapsible) ========== --}}
                @if($__can['links_view'] || $__can['inbox_view'] || $__can['files_view'])
                @php $grpLinksActive = nav_route_is('user.qr-codes.*') || nav_route_is('user.qrcode*') || nav_route_is('user.forms.*') || nav_route_is('user.backlinks.*') || nav_route_is('user.splash-pages.*') || nav_route_is('user.resume.*') || nav_route_is('user.projects.*') || nav_route_is('user.files.*') || nav_route_is('user.cloud-files.*') || nav_route_is('user.cloud-oauth.*') || nav_route_is('user.payouts.*') || nav_route_is('user.adult-content.*'); @endphp
                <div x-data="{ open: {{ $grpLinksActive ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                            class="sidebar-group-toggle section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                        <span>Links &amp; Pages</span>
                        <svg class="nav-icon grp-chevron" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
                    </button>
                    <div x-show="open || sidebarMode === 'icons'" x-cloak>
                        @if($__can['qr_view'])
                        <a href="{{ route('user.qr-codes.index') }}"
                           class="sidebar-link {{ nav_route_is('user.qr-codes.*') || nav_route_is('user.qrcode*') ? 'active' : '' }}"
                           style="--nav-tint:#6366f1; --nav-tint-soft:rgba(99,102,241,0.12);">
                            <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-qr-code"></use></svg></div>
                            <span class="nav-label">QR Codes</span>
                            <span class="sidebar-tooltip">QR Codes</span>
                        </a>
                        @endif
                        @if($__can['forms_view'])
                        <a href="{{ route('user.forms.index') }}"
                           class="sidebar-link {{ nav_route_is('user.forms.*') ? 'active' : '' }}"
                           style="--nav-tint:#ec4899; --nav-tint-soft:rgba(236,72,153,0.12);">
                            <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-clipboard-list"></use></svg></div>
                            <span class="nav-label">Forms</span>
                            <span class="sidebar-tooltip">Forms</span>
                        </a>
                        @endif
                        @if($__can['links_view'])
                        <a href="{{ route('user.backlinks.index') }}"
                           class="sidebar-link {{ nav_route_is('user.backlinks.*') ? 'active' : '' }}"
                           style="--nav-tint:#22d3ee; --nav-tint-soft:rgba(34,211,238,0.12);">
                            <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-target"></use></svg></div>
                            <span class="nav-label">Backlinks</span>
                            <span class="sidebar-tooltip">Backlinks</span>
                        </a>
                        <a href="{{ route('user.splash-pages.index') }}"
                           class="sidebar-link {{ nav_route_is('user.splash-pages.*') ? 'active' : '' }}"
                           style="--nav-tint:#6e61ff; --nav-tint-soft:rgba(110,97,255,0.12);">
                            <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-rocket"></use></svg></div>
                            <span class="nav-label">Intros</span>
                            <span class="sidebar-tooltip">Intros</span>
                        </a>
                        <a href="{{ route('user.resume.editor') }}"
                           class="sidebar-link {{ nav_route_is('user.resume.*') ? 'active' : '' }}"
                           style="--nav-tint:#14b8a6; --nav-tint-soft:rgba(20,184,166,0.12);">
                            <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-file-text"></use></svg></div>
                            <span class="nav-label">AI Resume / Portfolio</span>
                            <span class="sidebar-tooltip">AI Resume / Portfolio</span>
                        </a>
                        @endif
                        @if($__can['files_view'] || $__can['links_view'])
                        @php
                            $__filesLastTab = session('files_last_tab', 'vault');
                            if ($__filesLastTab === 'cloud' && $__can['files_view']) {
                                $filesHref = route('user.cloud-files.index');
                            } elseif ($__can['links_view']) {
                                $filesHref = route('user.files.index');
                            } else {
                                $filesHref = route('user.cloud-files.index');
                            }
                        @endphp
                        <a href="{{ $filesHref }}"
                           class="sidebar-link {{ nav_route_is('user.files.*') || nav_route_is('user.cloud-files.*') || nav_route_is('user.cloud-oauth.*') ? 'active' : '' }}"
                           style="--nav-tint:#06b6d4; --nav-tint-soft:rgba(6,182,212,0.12);">
                            <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-folder-open"></use></svg></div>
                            <span class="nav-label">Files</span>
                            <span class="sidebar-tooltip">Files, your vault &amp; cloud library</span>
                        </a>
                        @endif
                        @if($__can['posts_view'])
                        <a href="{{ route('user.payouts.show') }}"
                           class="sidebar-link {{ nav_route_is('user.payouts.*') || nav_route_is('user.adult-content.*') ? 'active' : '' }}"
                           style="--nav-tint:#10b981; --nav-tint-soft:rgba(16,185,129,0.12);">
                            <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-wallet"></use></svg></div>
                            <span class="nav-label">Earnings & Payouts</span>
                            @include('common.partials.soon-badge', ['feature' => 'payouts'])
                            <span class="sidebar-tooltip">Earnings & Payouts</span>
                        </a>
                        @endif
                    </div>
                </div>
                @endif
