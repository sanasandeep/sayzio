@extends('admin.layouts.app')
@section('title', 'Edit page — ' . $page->title)

@push('styles')
<style>
    .rte-btn { display: inline-flex; align-items: center; justify-content: center; min-width: 1.75rem; height: 1.75rem; padding: 0 .4rem; font-size: 12px; color: rgba(255,255,255,.7); background: transparent; border-radius: .375rem; border: 1px solid transparent; }
    .rte-btn:hover { background: rgba(255,255,255,.08); color: #fff; }
    .rte-btn:focus { outline: none; border-color: rgba(139,92,246,.6); }
    .rte-content :is(h3,h4) { font-weight: 600; color: #fff; margin: .5em 0 .25em; }
    .rte-content h3 { font-size: 1.05rem; }
    .rte-content h4 { font-size: 0.95rem; }
    .rte-content p { margin: .35em 0; }
    .rte-content ul { list-style: disc; padding-left: 1.25rem; margin: .35em 0; }
    .rte-content ol { list-style: decimal; padding-left: 1.25rem; margin: .35em 0; }
    .rte-content blockquote { border-left: 3px solid rgba(255,255,255,.2); padding-left: .75rem; color: rgba(255,255,255,.75); margin: .5em 0; }
    .rte-content code { background: rgba(255,255,255,.08); padding: .05em .35em; border-radius: .25rem; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.85em; }
    .rte-content a { color: #c4b5fd; text-decoration: underline; }
    .rte-content:empty::before { content: attr(data-placeholder); color: rgba(255,255,255,.35); }
</style>
@endpush

@push('scripts')
<script>
    function rteEditor() {
        return {
            editor: null,
            onChange: null,
            mount(el, initialHtml, onChange) {
                this.editor = el;
                this.onChange = onChange;
                el.setAttribute('data-placeholder', 'Write the section body…');
                el.innerHTML = initialHtml || '';
                try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) {}
                el.addEventListener('input', () => this.sync());
                el.addEventListener('blur', () => this.sync());
                el.addEventListener('paste', (e) => {
                    e.preventDefault();
                    const text = (e.clipboardData || window.clipboardData).getData('text/plain');
                    document.execCommand('insertText', false, text);
                });
            },
            sync() {
                if (this.onChange && this.editor) this.onChange(this.editor.innerHTML);
            },
            focus() { if (this.editor) this.editor.focus(); },
            exec(cmd, value = null) {
                this.focus();
                try { document.execCommand(cmd, false, value); } catch (e) {}
                this.sync();
            },
            block(tag) {
                this.exec('formatBlock', tag.toUpperCase());
            },
            wrapInline(tag) {
                this.focus();
                const sel = window.getSelection();
                if (!sel || sel.rangeCount === 0) return;
                const range = sel.getRangeAt(0);
                if (range.collapsed) return;
                const node = document.createElement(tag);
                try {
                    node.appendChild(range.extractContents());
                    range.insertNode(node);
                    sel.removeAllRanges();
                    const newRange = document.createRange();
                    newRange.selectNodeContents(node);
                    sel.addRange(newRange);
                } catch (e) {}
                this.sync();
            },
            addLink() {
                this.focus();
                const sel = window.getSelection();
                const hasSelection = sel && sel.toString().length > 0;
                const url = window.prompt('Link URL (https://, /path, mailto:, tel:)');
                if (!url) return;
                if (hasSelection) {
                    this.exec('createLink', url);
                } else {
                    const text = window.prompt('Link text', url) || url;
                    document.execCommand('insertHTML', false,
                        '<a href="' + url.replace(/"/g, '&quot;') + '">' + text.replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])) + '</a>');
                    this.sync();
                }
            },
        };
    }
</script>
@endpush

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <a href="{{ route('admin.site-pages.index') }}" class="text-xs text-violet-400 hover:underline"><i class="fas fa-arrow-left mr-1"></i>Back to all pages</a>

    @php
        $isServices = $page->slug === 'services';
        $policySlugs = \App\Modules\Common\Support\SitePagesContent::policySlugs();
        $isPolicy = in_array($page->slug, $policySlugs, true);
        if ($isServices) {
            $sectionsForJs = [];
            foreach (array_values($page->sections ?? []) as $s) {
                $bullets = $s['bullets'] ?? [];
                if (is_array($bullets)) { $bullets = implode("\n", $bullets); }
                $sectionsForJs[] = [
                    'heading'   => (string) ($s['heading'] ?? ''),
                    'tagline'   => (string) ($s['tagline'] ?? ''),
                    'body'      => (string) ($s['body'] ?? ''),
                    'icon'      => (string) ($s['icon'] ?? ''),
                    'tint'      => (string) ($s['tint'] ?? ''),
                    'bullets'   => (string) $bullets,
                    'cta_label' => (string) ($s['cta_label'] ?? ''),
                    'cta_url'   => (string) ($s['cta_url'] ?? ''),
                ];
            }
        } else {
            $sectionsForJs = array_map(function ($s) {
                return [
                    'id'      => $s['id'] ?? '',
                    'heading' => $s['heading'] ?? '',
                    'body'    => $s['body'] ?? '',
                    'visible' => array_key_exists('visible', $s) ? (bool) $s['visible'] : true,
                ];
            }, array_values($page->sections ?? []));
        }
    @endphp

    @if($page->slug === 'features')
        @include('admin.site-pages.partials.features-editor', ['page' => $page, 'categories' => $featuresCategories])
    @else
    @if($isServices)
        @include('admin.partials.icon-picker')
    @endif
    <form method="POST" action="{{ route('admin.site-pages.update', $page->slug) }}"
          @if($isServices)
          x-data="{ sections: {{ json_encode($sectionsForJs) }},
                    moveUp(i){ if(i>0){ const a=this.sections; [a[i-1],a[i]]=[a[i],a[i-1]]; } },
                    moveDown(i){ const a=this.sections; if(i<a.length-1){ [a[i+1],a[i]]=[a[i],a[i+1]]; } } }"
          @else
          x-data="{ sections: {{ json_encode($sectionsForJs) }},
                    moveUp(i){ if(i>0){ const a=this.sections; [a[i-1],a[i]]=[a[i],a[i-1]]; } },
                    moveDown(i){ const a=this.sections; if(i<a.length-1){ [a[i+1],a[i]]=[a[i],a[i+1]]; } } }"
          @endif
          class="glass rounded-2xl p-6 space-y-5">
        @csrf
        @method('PUT')
        <div>
            <h2 class="text-lg font-semibold text-white">{{ $page->title }} <span class="text-xs text-white/40 ml-2">/{{ $page->slug === 'home' ? '' : $page->slug }}</span></h2>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">Page title</label>
            <input type="text" name="title" required value="{{ old('title', $page->title) }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
            @error('title')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">Meta description</label>
            <textarea name="meta_description" rows="2" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">{{ old('meta_description', $page->meta_description) }}</textarea>
            @if($isServices)
                <p class="mt-1 text-[11px] text-white/40">Doubles as the hero subtitle on the public /services page.</p>
            @endif
        </div>

        @if($isPolicy)
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">Intro paragraph</label>
                <textarea name="intro" rows="3" placeholder="Short intro shown under the page title." class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">{{ old('intro', $page->intro) }}</textarea>
                @error('intro')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">Last updated</label>
                    <input type="date" name="last_updated_at" value="{{ old('last_updated_at', optional($page->last_updated_at)->format('Y-m-d')) }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    @error('last_updated_at')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2 text-sm text-white">
                        <input type="hidden" name="show_toc" value="0">
                        <input type="checkbox" name="show_toc" value="1" {{ old('show_toc', $page->show_toc ?? true) ? 'checked' : '' }} class="rounded border-white/20 bg-white/5">
                        Show table of contents
                    </label>
                </div>
            </div>
        @endif

        @if($isServices)
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-semibold uppercase tracking-wider text-white/60">Use-case blocks</label>
                    <button type="button" @click="sections.push({heading:'',tagline:'',body:'',icon:'fa-circle-dot',tint:'',bullets:'',cta_label:'Get started',cta_url:'/register'})" class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 rounded-lg text-white">
                        <i class="fas fa-plus mr-1"></i> Add use case
                    </button>
                </div>
                <template x-for="(s, i) in sections" :key="i">
                    <div class="bg-white/5 border border-white/10 rounded-xl p-4 mb-3 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] uppercase tracking-wider text-white/40">Use case <span x-text="i+1"></span></span>
                            <button type="button" @click="sections.splice(i,1)" class="text-xs text-red-400 hover:text-red-300"><i class="fas fa-trash"></i></button>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Title</label>
                                <input type="text" :name="'sections['+i+'][heading]'" x-model="s.heading" placeholder="Marketing channel"
                                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Tagline</label>
                                <input type="text" :name="'sections['+i+'][tagline]'" x-model="s.tagline" placeholder="Run campaigns from a single, trackable hub."
                                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Description</label>
                            <textarea :name="'sections['+i+'][body]'" x-model="s.body" rows="3" placeholder="Short paragraph that pitches this use case."
                                      class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white"></textarea>
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Bullets <span class="normal-case tracking-normal text-white/40">(one per line)</span></label>
                            <textarea :name="'sections['+i+'][bullets]'" x-model="s.bullets" rows="4" placeholder="Branded link-in-bio with UTM-friendly short links&#10;Per-link click analytics and traffic sources"
                                      class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono"></textarea>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Icon</label>
                                <div class="flex gap-1">
                                    <input type="text" :name="'sections['+i+'][icon]'" x-model="s.icon" placeholder="fa-bullhorn"
                                           class="flex-1 min-w-0 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono">
                                    <button type="button" @click="$store.iconPicker.openFor(s.icon, (name) => s.icon = name)"
                                            class="shrink-0 px-2.5 py-2 bg-white/5 border border-white/10 hover:bg-white/10 rounded-lg text-white/70 hover:text-white text-sm flex items-center gap-2"
                                            title="Pick from gallery">
                                        <span class="w-5 h-5 flex items-center justify-center text-violet-200">
                                            <i class="fas" :class="s.icon || 'fa-circle-dot'"></i>
                                        </span>
                                        <i class="fas fa-th"></i>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Tint (Tailwind gradient classes)</label>
                                <input type="text" :name="'sections['+i+'][tint]'" x-model="s.tint" placeholder="from-violet-500/30 to-fuchsia-500/10"
                                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono">
                                <p class="mt-1 text-[11px] text-white/40">Leave empty to use a built-in default.</p>
                            </div>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">CTA label</label>
                                <input type="text" :name="'sections['+i+'][cta_label]'" x-model="s.cta_label" placeholder="Get started"
                                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">CTA URL</label>
                                <input type="text" :name="'sections['+i+'][cta_url]'" x-model="s.cta_url" placeholder="/register"
                                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                            </div>
                        </div>
                    </div>
                </template>
                <div x-show="sections.length===0" class="text-xs text-white/40 text-center py-4">No use cases yet — click "Add use case".</div>
            </div>
        @else
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-semibold uppercase tracking-wider text-white/60">Content sections</label>
                    <button type="button" @click="sections.push({id:'',heading:'',body:'',visible:true})" class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 rounded-lg text-white">
                        <i class="fas fa-plus mr-1"></i> Add section
                    </button>
                </div>
                <template x-for="(s, i) in sections" :key="i">
                    <div class="bg-white/5 border border-white/10 rounded-xl p-4 mb-3 space-y-2"
                         :class="{ 'opacity-60': !s.visible }">
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <span class="text-[10px] uppercase tracking-wider text-white/40">Section <span x-text="i+1"></span></span>
                            <div class="flex items-center gap-2">
                                <label class="inline-flex items-center gap-1.5 text-[11px] text-white/70 cursor-pointer select-none">
                                    <input type="hidden" :name="'sections['+i+'][visible]'" value="0">
                                    <input type="checkbox" :name="'sections['+i+'][visible]'" value="1" x-model="s.visible" class="rounded border-white/20 bg-white/5">
                                    <span x-text="s.visible ? 'Visible' : 'Hidden'"></span>
                                </label>
                                <button type="button" @click="moveUp(i)" :disabled="i===0" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-1.5 py-1" title="Move up"><i class="fas fa-arrow-up"></i></button>
                                <button type="button" @click="moveDown(i)" :disabled="i===sections.length-1" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-1.5 py-1" title="Move down"><i class="fas fa-arrow-down"></i></button>
                                <button type="button" @click="if(confirm('Delete this section?')) sections.splice(i,1)" class="text-xs text-red-400 hover:text-red-300 px-1.5 py-1" title="Delete"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                        <input type="hidden" :name="'sections['+i+'][id]'" :value="s.id">
                        <input type="text" :name="'sections['+i+'][heading]'" x-model="s.heading" placeholder="Section heading"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                        <label class="block text-[10px] uppercase tracking-wider text-white/40">Body @if(!$isPolicy)<span class="normal-case tracking-normal text-white/40">(Markdown or basic HTML)</span>@endif</label>
                        @if($isPolicy)
                            <div x-data="rteEditor()"
                                 x-init="mount($refs.editor, s.body || '', html => s.body = html)"
                                 class="rounded-lg border border-white/10 overflow-hidden bg-white/5">
                                <div class="flex flex-wrap items-center gap-1 px-2 py-1.5 border-b border-white/10 bg-white/5">
                                    <button type="button" @mousedown.prevent="exec('bold')" title="Bold" class="rte-btn"><i class="fas fa-bold"></i></button>
                                    <button type="button" @mousedown.prevent="exec('italic')" title="Italic" class="rte-btn"><i class="fas fa-italic"></i></button>
                                    <button type="button" @mousedown.prevent="exec('underline')" title="Underline" class="rte-btn"><i class="fas fa-underline"></i></button>
                                    <span class="w-px h-4 bg-white/10 mx-1"></span>
                                    <button type="button" @mousedown.prevent="block('h3')" title="Heading 3" class="rte-btn font-semibold">H3</button>
                                    <button type="button" @mousedown.prevent="block('h4')" title="Heading 4" class="rte-btn font-semibold">H4</button>
                                    <button type="button" @mousedown.prevent="block('p')" title="Paragraph" class="rte-btn"><i class="fas fa-paragraph"></i></button>
                                    <button type="button" @mousedown.prevent="block('blockquote')" title="Quote" class="rte-btn"><i class="fas fa-quote-right"></i></button>
                                    <button type="button" @mousedown.prevent="wrapInline('code')" title="Inline code" class="rte-btn"><i class="fas fa-code"></i></button>
                                    <span class="w-px h-4 bg-white/10 mx-1"></span>
                                    <button type="button" @mousedown.prevent="exec('insertUnorderedList')" title="Bulleted list" class="rte-btn"><i class="fas fa-list-ul"></i></button>
                                    <button type="button" @mousedown.prevent="exec('insertOrderedList')" title="Numbered list" class="rte-btn"><i class="fas fa-list-ol"></i></button>
                                    <span class="w-px h-4 bg-white/10 mx-1"></span>
                                    <button type="button" @mousedown.prevent="addLink()" title="Add link" class="rte-btn"><i class="fas fa-link"></i></button>
                                    <button type="button" @mousedown.prevent="exec('unlink')" title="Remove link" class="rte-btn"><i class="fas fa-unlink"></i></button>
                                </div>
                                <div x-ref="editor" contenteditable="true" spellcheck="true"
                                     class="rte-content min-h-[160px] px-3 py-2 text-sm text-white focus:outline-none"></div>
                            </div>
                            <input type="hidden" :name="'sections['+i+'][body]'" :value="s.body">
                            <p class="text-[11px] text-white/40 leading-relaxed">
                                Use the toolbar to format text. Allowed tags: <code class="text-white/60">a, strong, em, u, ul, ol, li, p, br, h3, h4, blockquote, code</code>. Output is sanitized on save — anything else (scripts, inline handlers, unsafe link protocols) is stripped.
                            </p>
                        @else
                            <textarea :name="'sections['+i+'][body]'" x-model="s.body" rows="6" placeholder="Body — line breaks are preserved."
                                      class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono"></textarea>
                            <p class="text-[11px] text-white/40 leading-relaxed">
                                Formatting: <code class="text-white/60">**bold**</code>,
                                <code class="text-white/60">*italic*</code>,
                                <code class="text-white/60">[text](https://url)</code>,
                                lines starting with <code class="text-white/60">-</code> become bullet lists,
                                <code class="text-white/60">1.</code> become numbered lists.
                                Safe HTML tags (<code class="text-white/60">a, strong, em, ul, ol, li, p, br, h3, h4, blockquote, code</code>) are allowed; anything else (including scripts, inline event handlers, and unsafe link protocols) is filtered out.
                            </p>
                        @endif
                    </div>
                </template>
                <div x-show="sections.length===0" class="text-xs text-white/40 text-center py-4">No sections yet — click "Add section".</div>
            </div>
        @endif

        @php
            $errorSlugs = ['error-403', 'error-404', 'error-500', 'error-503', 'error-419', 'error-429'];
            $errorLabels = [
                'error-403' => '403 (no access)',
                'error-404' => '404 (not found)',
                'error-500' => '500 (server error)',
                'error-503' => '503 (maintenance)',
                'error-419' => '419 (session expired)',
                'error-429' => '429 (too many requests)',
            ];
        @endphp
        @if(in_array($page->slug, $errorSlugs))
            <div class="grid sm:grid-cols-2 gap-4 pt-2">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">Call-to-action label</label>
                    <input type="text" name="cta_label" value="{{ old('cta_label', $page->cta_label) }}" placeholder="Back to home" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    @error('cta_label')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">Call-to-action URL</label>
                    <input type="text" name="cta_url" value="{{ old('cta_url', $page->cta_url) }}" placeholder="/" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    @error('cta_url')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>
        @endif

        @if($page->slug === 'error-404')
            <div class="pt-2">
                <label class="inline-flex items-start gap-2 text-sm text-white">
                    <input type="hidden" name="error_404_suggestions_enabled" value="0">
                    <input type="checkbox" name="error_404_suggestions_enabled" value="1" {{ old('error_404_suggestions_enabled', $settings['error_404_suggestions_enabled']) ? 'checked' : '' }} class="mt-0.5 rounded border-white/20 bg-white/5">
                    <span>
                        Show "Did you mean…?" suggestions
                        <span class="block text-xs text-white/50 mt-0.5">When a visitor hits a 404, show up to 3 close matches from your biolinks, short links and site pages. Nothing is shown when no match is close enough.</span>
                    </span>
                </label>
            </div>
        @endif

        @if($page->slug === 'about')
            @include('admin.site-pages.partials.about-editor', ['page' => $page])
        @elseif($page->slug === 'contact')
            @include('admin.site-pages.partials.contact-editor', ['page' => $page])
        @endif

        <div class="pt-4 border-t border-white/10 flex items-center justify-between">
            @if(in_array($page->slug, $errorSlugs))
                <span class="text-xs text-white/40">Shown automatically when visitors hit a {{ $errorLabels[$page->slug] }} response.</span>
            @else
                <a href="/{{ $page->slug === 'home' ? '' : $page->slug }}" target="_blank" class="text-xs text-violet-400 hover:underline">View live page <i class="fas fa-external-link-alt ml-1 text-[10px]"></i></a>
            @endif
            <button type="submit" class="px-6 py-2.5 bg-violet-600 hover:bg-violet-700 text-white rounded-xl font-medium">Save changes</button>
        </div>
    </form>
    @endif

    @if($page->slug === 'discovery')
        <div class="glass rounded-2xl p-6">
            <h3 class="text-sm font-semibold text-white mb-1">Discovery settings</h3>
            <p class="text-xs text-white/50 mb-4">Controls how the public /discovery page renders biolinks.</p>
            <form method="POST" action="{{ route('admin.site-pages.discovery-settings') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">Biolinks per page</label>
                    <input type="number" min="4" max="60" name="discovery_per_page" value="{{ old('discovery_per_page', $settings['discovery_per_page']) }}" class="w-32 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-white">
                    <input type="checkbox" name="discovery_show_search" value="1" {{ $settings['discovery_show_search'] ? 'checked' : '' }} class="rounded border-white/20 bg-white/5">
                    Show search bar
                </label>
                <div class="pt-2">
                    <button type="submit" class="px-5 py-2 bg-violet-600 hover:bg-violet-700 rounded-lg text-sm font-medium text-white">Save settings</button>
                </div>
            </form>
        </div>
    @endif

    @if($page->slug === 'creators-feed')
        <div class="glass rounded-2xl p-6">
            <h3 class="text-sm font-semibold text-white mb-1">Creators feed settings</h3>
            <p class="text-xs text-white/50 mb-4">Controls how the public /creators-feed page renders posts.</p>
            <form method="POST" action="{{ route('admin.site-pages.creators-feed-settings') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">Posts per page</label>
                    <input type="number" min="4" max="60" name="creators_feed_per_page" value="{{ old('creators_feed_per_page', $settings['creators_feed_per_page']) }}" class="w-32 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-white">
                    <input type="checkbox" name="creators_feed_show_pinned" value="1" {{ $settings['creators_feed_show_pinned'] ? 'checked' : '' }} class="rounded border-white/20 bg-white/5">
                    Show pinned posts at the top
                </label>
                <div class="pt-2">
                    <button type="submit" class="px-5 py-2 bg-violet-600 hover:bg-violet-700 rounded-lg text-sm font-medium text-white">Save settings</button>
                </div>
            </form>
        </div>
    @endif

    @if(isset($revisions) && $revisions->count())
        <div class="glass rounded-2xl p-6">
            <h3 class="text-sm font-semibold text-white mb-1">Revision history</h3>
            <p class="text-xs text-white/50 mb-4">Every save snapshots the page so you can see what changed and roll back if needed.</p>
            <div class="space-y-2 max-h-96 overflow-y-auto pr-2">
                @foreach($revisions as $rev)
                    @php $revEditor = $rev->editor(); @endphp
                    <div class="flex items-start justify-between gap-3 p-3 bg-white/5 border border-white/10 rounded-xl">
                        <div class="min-w-0">
                            <p class="text-sm text-white">
                                {{ $rev->created_at->format('M j, Y g:i a') }}
                                <span class="text-xs text-white/50 ml-1">#{{ $rev->id }}</span>
                            </p>
                            <p class="text-xs text-white/60 mt-0.5">
                                @if($revEditor)
                                    <i class="far fa-user mr-1"></i>{{ $revEditor->name ?? $rev->editor_name }}
                                @elseif($rev->editor_name)
                                    <i class="far fa-user mr-1"></i>{{ $rev->editor_name }}
                                @else
                                    <i class="far fa-user mr-1"></i><span class="text-white/40">System</span>
                                @endif
                            </p>
                            @if($rev->summary)
                                <p class="text-xs text-white/70 mt-1">{{ $rev->summary }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <a href="{{ route('admin.site-pages.revisions.show', [$page->slug, $rev->id]) }}"
                               class="text-xs px-3 py-1.5 bg-white/10 hover:bg-white/15 text-white rounded-lg">
                                <i class="far fa-eye mr-1"></i>View
                            </a>
                            @if(!$loop->first)
                                <form method="POST" action="{{ route('admin.site-pages.revisions.restore', [$page->slug, $rev->id]) }}"
                                      onsubmit="return confirm('Restore this revision? Your current content will be saved as a new revision first.')">
                                    @csrf
                                    <button type="submit" class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 text-white rounded-lg">
                                        <i class="fas fa-clock-rotate-left mr-1"></i>Restore
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($page->slug === 'faqs')
        <div class="glass rounded-2xl p-6">
            <h3 class="text-sm font-semibold text-white mb-3">FAQ items</h3>
            <form method="POST" action="{{ route('admin.site-pages.faqs.store') }}" class="space-y-2 mb-5 pb-5 border-b border-white/10">
                @csrf
                <input type="text" name="question" required placeholder="Question" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                <textarea name="answer" required rows="3" placeholder="Answer" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white"></textarea>
                <button type="submit" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 rounded-lg text-xs font-medium text-white"><i class="fas fa-plus mr-1"></i> Add FAQ</button>
            </form>

            <div class="space-y-3">
                @foreach($faqs as $f)
                    <div class="bg-white/5 border border-white/10 rounded-xl p-4 space-y-2">
                        <form method="POST" action="{{ route('admin.site-pages.faqs.update', $f) }}" class="space-y-2">
                            @csrf @method('PUT')
                            <div class="flex items-center gap-2">
                                <input type="number" name="sort_order" value="{{ $f->sort_order }}" class="w-20 px-2 py-1.5 bg-white/5 border border-white/10 rounded-lg text-xs text-white">
                                <input type="text" name="question" value="{{ $f->question }}" required class="flex-1 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                            </div>
                            <textarea name="answer" required rows="3" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">{{ $f->answer }}</textarea>
                            <button type="submit" class="px-3 py-1.5 bg-violet-600 hover:bg-violet-700 rounded-lg text-xs text-white">Save</button>
                        </form>
                        <form method="POST" action="{{ route('admin.site-pages.faqs.destroy', $f) }}" onsubmit="return confirm('Delete this FAQ?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="px-3 py-1.5 bg-red-500/20 hover:bg-red-500/30 text-red-300 rounded-lg text-xs"><i class="fas fa-trash mr-1"></i>Delete</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
