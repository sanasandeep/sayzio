{{-- One template card for the picker grid. Rendered server-side for the
     first chunk and streamed as HTML for later chunks (see the picker's
     background chunk loader). Expects: $tpl (decorated with content_summary
     + preview_layout), $link, $locked (bool), $hasBlocks (bool). Must stay
     inside the picker's x-data scope: uses matches()/highlight(). --}}
@php
    $summary = $tpl->content_summary ?? [];
    $topCount = count($summary);
    $blockCount = $topCount;
    foreach ($summary as $s) { $blockCount += count($s['children'] ?? []); }
    $previewRows = $tpl->preview_layout ?? [];
@endphp
<div x-show="matches('{{ $tpl->category }}', {{ \Illuminate\Support\Js::from(strtolower($tpl->name . ' ' . $tpl->description . ' ' . ucfirst($tpl->category))) }})"
     x-cloak
     x-data="{ expanded: false }"
     class="glass rounded-2xl border border-white/10 overflow-hidden hover:border-blue-500/40 transition group">
    <div class="aspect-[4/3] flex items-center justify-center overflow-hidden relative" style="background: linear-gradient(135deg, rgba(61,107,255,0.12), rgba(92,131,255,0.04));">
        @if($tpl->thumbnail_url)
            <img src="{{ $tpl->thumbnail_url }}" alt="{{ $tpl->name }}" class="w-full h-full object-cover">
        @elseif(!empty($previewRows))
            {{-- Auto-generated mini blueprint of the page's top-level
                 blocks. Shared with the guided wizard's starting-design
                 step via the template-preview-blueprint partial. --}}
            @include('user.links.partials.template-preview-blueprint', ['previewRows' => $previewRows])
        @else
            <img src="{{ asset('template-placeholders/page.svg') }}" alt="{{ $tpl->name }} preview" class="w-full h-full object-cover">
        @endif
        @if($locked)
            <div class="absolute top-2 right-2 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/90 text-white"><i class="fas fa-lock mr-1"></i>{{ $tpl->plan_tier }}</div>
        @endif
    </div>
    <div class="p-4">
        {{-- Title row: name on the left, small subtle category pill on the
             right so the most useful info (the title and the chip list below)
             reads first. --}}
        <div class="flex items-start justify-between gap-2 mb-1.5">
            <h3 class="text-sm font-semibold text-white flex-1 min-w-0"
                x-html="highlight({{ \Illuminate\Support\Js::from($tpl->name) }})">{{ $tpl->name }}</h3>
            <span class="shrink-0 text-[9px] uppercase tracking-wide px-1.5 py-0.5 rounded-full whitespace-nowrap text-white/55"
                  style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.10);"
                  x-html="highlight({{ \Illuminate\Support\Js::from(ucfirst($tpl->category)) }})">{{ ucfirst($tpl->category) }}</span>
        </div>
        @if($tpl->description)
            <p class="text-xs text-white/50 mb-2 line-clamp-2"
               x-html="highlight({{ \Illuminate\Support\Js::from($tpl->description) }})">{{ $tpl->description }}</p>
        @endif

        @if($topCount)
            {{-- Primary "what's inside" caption: small icon-tagged chips
                 (icon + short label like '2 Cards', 'Heading'), grouped by
                 type with counts. Full breakdown lives in the expand panel
                 below so card heights stay consistent. --}}
            @php
                $chipGroups = [];
                foreach ($summary as $entry) {
                    $key = $entry['type'];
                    if (!isset($chipGroups[$key])) {
                        $chipGroups[$key] = ['icon' => $entry['icon'] ?: 'fa-cube', 'label' => $entry['label'], 'count' => 0];
                    }
                    $chipGroups[$key]['count'] += 1;
                }
                $chipGroups = array_values($chipGroups);
                $shownChips = array_slice($chipGroups, 0, 3);
                $extraChips = max(0, count($chipGroups) - 3);
            @endphp
            <div class="flex flex-wrap gap-1 mb-2">
                @foreach($shownChips as $chip)
                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-medium text-white/90"
                          style="background: rgba(92,131,255,0.10); border: 1px solid rgba(92,131,255,0.18);">
                        <i class="fas {{ $chip['icon'] }} text-blue-300" style="font-size: 9px;"></i>
                        <span>{{ $chip['count'] > 1 ? $chip['count'] . ' ' . $chip['label'] . 's' : $chip['label'] }}</span>
                    </span>
                @endforeach
                @if($extraChips > 0)
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold text-blue-300/90">
                        +{{ $extraChips }} more
                    </span>
                @endif
            </div>
            <p class="text-[10px] text-white/35 mb-2">{{ $blockCount }} {{ \Illuminate\Support\Str::plural('block', $blockCount) }} total</p>
            <button type="button"
                    @click="expanded = !expanded"
                    class="text-[11px] text-blue-400 hover:text-blue-300 mb-3 inline-flex items-center gap-1">
                <i class="fas" :class="expanded ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                <span x-text="expanded ? 'Hide what\'s inside' : 'See what\'s inside'"></span>
            </button>
            <div x-show="expanded" x-cloak class="mb-3 -mt-1 rounded-lg border border-white/10 bg-white/5 p-2.5 max-h-64 overflow-y-auto">
                <div class="text-[10px] uppercase tracking-wide text-white/40 mb-1.5">What's inside</div>
                <ul class="space-y-1.5">
                    @foreach($summary as $entry)
                        <li class="text-[11px] text-white/85">
                            <div class="flex items-start gap-2">
                                <i class="fas {{ $entry['icon'] ?: 'fa-cube' }} text-blue-400 mt-0.5 w-3 text-center"></i>
                                <span class="flex-1 min-w-0">
                                    <span class="font-semibold">{{ $entry['label'] }}</span>
                                    @if(!empty($entry['preview']))
                                        <span class="text-white/50">, {{ $entry['preview'] }}</span>
                                    @endif
                                </span>
                            </div>
                            @if(!empty($entry['children']))
                                <ul class="mt-1 ml-5 pl-2 border-l border-white/10 space-y-1">
                                    @foreach($entry['children'] as $child)
                                        <li class="flex items-start gap-2 text-[10.5px] text-white/70">
                                            <i class="fas {{ $child['icon'] ?: 'fa-cube' }} text-blue-400/80 mt-0.5 w-3 text-center"></i>
                                            <span class="flex-1 min-w-0">
                                                <span class="font-medium">{{ $child['label'] }}</span>
                                                @if(!empty($child['preview']))
                                                    <span class="text-white/45">, {{ $child['preview'] }}</span>
                                                @endif
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if($locked)
            <a href="{{ route('user.upgrade') }}" class="block text-center w-full py-2 text-xs font-semibold rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20 hover:bg-amber-500/20 transition">
                <i class="fas fa-lock mr-1"></i>Upgrade to "{{ $tpl->plan_tier }}" to use
            </a>
        @else
            <div class="flex items-center gap-2">
                <button type="button"
                        @click="openPreview('{{ route('user.onboarding.template.preview', ['id' => $tpl->id]) }}', @js($tpl->name))"
                        class="shrink-0 py-2 px-3 text-xs font-semibold rounded-xl bg-white/5 hover:bg-white/10 text-white border border-white/10 transition"
                        title="Preview as a published page">
                    <i class="fas fa-eye mr-1"></i>Preview
                </button>
                <form method="POST" action="{{ route('user.links.templates.apply-page', $link) }}" class="flex-1"
                      @if($hasBlocks) onsubmit="return window.themedConfirmSubmit(this, {title: 'Replace existing blocks?', message: 'This will replace your existing blocks on this Link in Bio.', confirmText: 'Replace', confirmIcon: 'fa-arrows-rotate', iconClass: 'fa-triangle-exclamation'})" @endif>
                    @csrf
                    <input type="hidden" name="template_id" value="{{ $tpl->id }}">
                    @if($hasBlocks)<input type="hidden" name="confirm_overwrite" value="1">@endif
                    <button type="submit" class="w-full py-2 text-xs font-semibold rounded-xl bg-blue-600 hover:bg-blue-700 text-white transition">
                        {{ $hasBlocks ? 'Replace with this template' : 'Use this template' }}
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>
