@extends('user.layouts.app')
@section('title', 'Create Form')

@push('scripts')
<script>
(function() {
    window.__formTemplates = @json($templatesFlat);

    document.addEventListener('alpine:init', function() {
        Alpine.data('formCreate', function() {
            var allTemplates = window.__formTemplates || [];
            return {
                template: @js($initialTemplate ?? 'contact'),
                search: '',
                activeCategory: 'all',
                get noResults() {
                    var self = this;
                    return allTemplates.every(function(t) { return !self._matches(t); });
                },
                _matches: function(t) {
                    if (this.activeCategory !== 'all' && this.activeCategory !== t.category) return false;
                    if (!this.search) return true;
                    var q = this.search.toLowerCase();
                    return t.label.toLowerCase().includes(q) ||
                           t.desc.toLowerCase().includes(q) ||
                           t.category.toLowerCase().includes(q);
                },
                tplVisible: function(key) {
                    var self = this;
                    var t = allTemplates.find(function(x) { return x.key === key; });
                    return t ? self._matches(t) : false;
                },
                categoryVisible: function(cat) {
                    var self = this;
                    return allTemplates.some(function(t) { return t.category === cat && self._matches(t); });
                },
                selectedLabel: function() {
                    var self = this;
                    var t = allTemplates.find(function(x) { return x.key === self.template; });
                    return t ? t.label : self.template;
                },
            };
        });
    });
}());
</script>
@endpush

@section('content')
<div class="max-w-3xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Create a New Form',
        'subtitle' => 'Pick a starting template, you can fully customize fields and design afterwards.',
        'icon' => 'fa-wpforms',
        'back' => route('user.forms.index'),
    ])

    <form method="POST" action="{{ route('user.forms.store') }}" class="space-y-6"
          x-data="formCreate()">
        @csrf

        {{-- Basics --}}
        <div class="card-premium p-6">
            <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">Basics</h3>
            <p class="text-[11px] mb-5" style="color: var(--text-faint);">Give your form a name and an optional description. You can change all of this later.</p>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Form title <span class="text-red-400">*</span></label>
                    <input type="text" name="title" value="{{ old('title') }}" placeholder="e.g. Contact Us" class="theme-input w-full" required maxlength="160">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Description <span class="text-[10px]" style="color: var(--text-faint);"> - shown below the title on the public form</span></label>
                    <textarea name="description" rows="2" maxlength="1000" placeholder="Optional, a short message to set expectations" class="theme-input w-full">{{ old('description') }}</textarea>
                </div>
                @if(auth()->user()->projects()->exists())
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Folder <span class="text-[10px]" style="color: var(--text-faint);"> - optional</span></label>
                    <select name="project_id" class="theme-input w-full">
                        <option value="">No folder</option>
                        @foreach(auth()->user()->projects()->orderBy('name')->get() as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                @if(($domains ?? collect())->isNotEmpty())
                @php $selectedDomainId = old('domain_id', $defaultDomainId ?? ''); @endphp
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Domain <span class="text-[10px]" style="color: var(--text-faint);"> - the address your form link uses</span></label>
                    <select name="domain_id" class="theme-input w-full">
                        @php $primaryDomainId = $domains->firstWhere('is_primary', true)?->id; @endphp
                        @unless($primaryDomainId)
                            <option value="">{{ rtrim(parse_url(config('app.url'), PHP_URL_HOST) ?: config('app.url'), '/') }} (default)</option>
                        @endunless
                        @foreach($domains as $d)
                            <option value="{{ $d->id }}" {{ (string) $selectedDomainId === (string) $d->id ? 'selected' : '' }}>{{ $d->domain }}{{ $d->is_primary ? ' (default)' : '' }}</option>
                        @endforeach
                    </select>
                    @error('domain_id') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                @endif
            </div>
        </div>

        {{-- Template Picker --}}
        <div class="card-premium p-6">
            <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">Choose a template</h3>
            <p class="text-[11px] mb-4" style="color: var(--text-faint);">Start with a ready-made set of fields, or pick Blank and design from scratch.</p>

            @if(!empty($templateUnavailable))
            {{-- A deep link pointed at a template key that has since been retired
                 from the catalog. Surface the fallback instead of silently
                 ignoring the ?template= param. --}}
            <div class="flex items-start gap-2.5 mb-4 p-3 rounded-xl text-[11px]"
                 style="background: rgba(245,158,11,0.10); border: 1px solid rgba(245,158,11,0.35); color: var(--text-muted);">
                <i class="fas fa-triangle-exclamation mt-0.5" style="color: rgb(245 158 11);"></i>
                <span>The template <strong style="color: var(--text-primary);">“{{ $requestedTemplate }}”</strong> is no longer available. We’ve started you from <strong style="color: var(--text-primary);">Contact</strong>, pick any template below to continue.</span>
            </div>
            @endif

            {{-- Search --}}
            <div class="relative mb-3">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs pointer-events-none" style="color: var(--text-faint);"></i>
                <input type="text" x-model="search" placeholder="Search templates…"
                       class="theme-input w-full pl-8 text-xs"
                       @input="activeCategory = 'all'">
            </div>

            {{-- Category filter pills --}}
            <div class="flex flex-wrap gap-1.5 mb-5">
                <button type="button"
                        @click="activeCategory = 'all'; search = ''"
                        :style="activeCategory === 'all'
                            ? 'background:rgb(59 130 246);color:#fff;border-color:rgb(59 130 246)'
                            : 'background:var(--bg-glass-input);color:var(--text-muted);border-color:var(--border-glass)'"
                        class="px-2.5 py-1 rounded-full text-[11px] font-medium transition-colors border">
                    All
                </button>
                @foreach($templateCategories as $catKey => $catMeta)
                <button type="button"
                        @click="activeCategory = '{{ $catKey }}'; search = ''"
                        :style="activeCategory === '{{ $catKey }}'
                            ? 'background:rgb(59 130 246);color:#fff;border-color:rgb(59 130 246)'
                            : 'background:var(--bg-glass-input);color:var(--text-muted);border-color:var(--border-glass)'"
                        class="px-2.5 py-1 rounded-full text-[11px] font-medium transition-colors border">
                    <i class="fas {{ $catMeta['icon'] }} mr-1 text-[10px]"></i>{{ $catMeta['label'] }}
                </button>
                @endforeach
            </div>

            {{-- No results --}}
            <div x-show="noResults" class="py-10 text-center" style="color: var(--text-faint);" x-cloak>
                <i class="fas fa-search text-2xl mb-3 opacity-40 block"></i>
                <p class="text-sm">No templates match "<span x-text="search"></span>".</p>
                <button type="button" @click="search = ''; activeCategory = 'all'"
                        class="text-xs text-blue-400 mt-1 underline underline-offset-2">Clear search</button>
            </div>

            {{-- Grouped template cards --}}
            <div class="space-y-6">
                @foreach($templateGroups as $catKey => $templates)
                <div x-show="categoryVisible('{{ $catKey }}')">
                    {{-- Category heading --}}
                    <div class="flex items-center gap-2 mb-3">
                        <i class="fas {{ $templateCategories[$catKey]['icon'] ?? 'fa-layer-group' }} text-xs"
                           style="color: var(--text-faint);"></i>
                        <span class="text-xs font-semibold uppercase tracking-wide"
                              style="color: var(--text-faint);">{{ $templateCategories[$catKey]['label'] ?? $catKey }}</span>
                        <div class="flex-1 h-px" style="background: var(--border-glass);"></div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                        @foreach($templates as $key => $tpl)
                        <label class="cursor-pointer" x-show="tplVisible('{{ $key }}')">
                            <input type="radio" name="template" value="{{ $key }}"
                                   x-model="template" class="sr-only"
                                   {{ $key === ($initialTemplate ?? 'contact') ? 'checked' : '' }}>
                            <div class="p-3 rounded-xl transition-all h-full"
                                 :class="template === '{{ $key }}' ? 'ring-2 ring-blue-500' : 'hover:border-blue-400'"
                                 style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                                <div class="flex items-start gap-2">
                                    <div class="shrink-0 w-7 h-7 rounded-lg flex items-center justify-center mt-0.5"
                                         :style="template === '{{ $key }}'
                                             ? 'background:rgba(59,130,246,0.15)'
                                             : 'background:var(--bg-glass)'">
                                        <i class="fas {{ $tpl['icon'] }} text-[11px]"
                                           :class="template === '{{ $key }}' ? 'text-blue-400' : ''"
                                           style="color: var(--text-muted);"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-[11px] font-semibold leading-snug"
                                             style="color: var(--text-primary);">{{ $tpl['label'] }}</div>
                                        <div class="text-[10px] mt-0.5 leading-tight"
                                             style="color: var(--text-faint);">{{ $tpl['desc'] }}</div>
                                    </div>
                                </div>
                            </div>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Selected template indicator --}}
            <div class="mt-4 flex items-center gap-2 text-[11px]" style="color: var(--text-muted);">
                <i class="fas fa-check-circle text-blue-400"></i>
                <span>Selected: <strong style="color: var(--text-primary);" x-text="selectedLabel()"></strong></span>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn-primary px-6 py-3 text-sm font-semibold inline-flex items-center gap-2">
                <i class="fas fa-arrow-right text-xs"></i> Create Form & Open Builder
            </button>
            <a href="{{ route('user.forms.index') }}" class="text-xs px-4 py-2" style="color: var(--text-faint);">Cancel</a>
        </div>
    </form>
</div>
@endsection
