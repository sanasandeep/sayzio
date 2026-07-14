@extends('user.layouts.app')
@section('title', 'Bulk Create Short Links')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.create') }}" class="text-white/30 hover:text-white transition-colors" title="Back"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 class="text-2xl font-bold text-white">Bulk Create Short Links</h1>
            <p class="text-xs text-white/40 mt-0.5">Paste a list or upload a CSV — apply the same settings to every link.</p>
        </div>
    </div>

    @if(session('error'))
        <div class="mb-4 px-4 py-3 rounded-xl bg-red-500/10 border border-red-500/30 text-sm text-red-300">
            <i class="fas fa-exclamation-triangle mr-2"></i>{{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('user.links.url.bulk.preview') }}" enctype="multipart/form-data"
          x-data="{ tab: 'paste', passwordProtect: false, showAdvanced: false }">
        @csrf

        <div class="glass rounded-2xl p-6 mb-6">
            <h2 class="text-base font-semibold text-white mb-1">Step 1 — Add your URLs</h2>
            <p class="text-xs text-white/40 mb-4">Up to {{ $maxRows }} rows per batch.</p>

            <div class="flex gap-1 mb-4 bg-white/5 p-1 rounded-xl w-fit">
                <button type="button" @click="tab = 'paste'"
                        :class="tab === 'paste' ? 'bg-blue-600 text-white' : 'text-white/60 hover:text-white'"
                        class="px-4 py-1.5 rounded-lg text-xs font-medium transition-all">
                    <i class="fas fa-paste mr-1"></i> Paste list
                </button>
                <button type="button" @click="tab = 'csv'"
                        :class="tab === 'csv' ? 'bg-blue-600 text-white' : 'text-white/60 hover:text-white'"
                        class="px-4 py-1.5 rounded-lg text-xs font-medium transition-all">
                    <i class="fas fa-file-csv mr-1"></i> Upload CSV
                </button>
            </div>

            <div x-show="tab === 'paste'">
                <label class="block text-sm font-medium text-white/60 mb-1.5">Destination URLs <span class="text-white/30 text-xs">(one per line)</span></label>
                <textarea name="urls_text" rows="10"
                          placeholder="https://example.com/page-one&#10;https://example.com/page-two&#10;https://example.com/page-three"
                          class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-blue-500/40 outline-none font-mono">{{ old('urls_text') }}</textarea>
            </div>

            <div x-show="tab === 'csv'" x-cloak>
                <label class="block text-sm font-medium text-white/60 mb-1.5">CSV file</label>
                <input type="file" name="csv_file" accept=".csv,text/csv"
                       class="block w-full text-sm text-white/70 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-600 file:text-white hover:file:bg-blue-700">
                <p class="text-[11px] text-white/40 mt-2">
                    Required column: <code class="text-blue-300">long_url</code> (or <code class="text-blue-300">url</code>).
                    Optional: <code class="text-blue-300">alias</code>, <code class="text-blue-300">title</code>.
                </p>
            </div>
        </div>

        <div class="glass rounded-2xl p-6 mb-6">
            <h2 class="text-base font-semibold text-white mb-1">Step 2 — Shared settings</h2>
            <p class="text-xs text-white/40 mb-4">These apply to every link in the batch. Per-row alias and title still win.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                @if(($domains ?? collect())->isNotEmpty())
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1.5">Branded domain</label>
                    <select name="domain_id" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-blue-500/40 outline-none">
                        <option value="" class="bg-[#0d0818]">Default</option>
                        @foreach($domains as $d)
                            <option value="{{ $d->id }}" {{ old('domain_id') == $d->id ? 'selected' : '' }} class="bg-[#0d0818]">{{ $d->domain }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1.5">Project</label>
                    <select name="project_id" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-blue-500/40 outline-none">
                        <option value="" class="bg-[#0d0818]">No project</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" {{ old('project_id') == $project->id ? 'selected' : '' }} class="bg-[#0d0818]">{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1.5">Redirect type</label>
                    <select name="redirect_type" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-blue-500/40 outline-none">
                        <option value="301" class="bg-[#0d0818]">301 — Permanent</option>
                        <option value="302" {{ old('redirect_type') == 302 ? 'selected' : '' }} class="bg-[#0d0818]">302 — Temporary</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1.5">Expires at <span class="text-white/30 text-xs">(optional)</span></label>
                    <input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}"
                           class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-blue-500/40 outline-none">
                </div>
            </div>

            <div class="border-t border-white/5 pt-4">
                <button type="button" @click="showAdvanced = !showAdvanced" class="flex items-center justify-between w-full text-left">
                    <span class="text-sm font-semibold text-white"><i class="fas fa-sliders-h mr-2 text-white/40"></i>More shared options</span>
                    <i class="fas text-white/30" :class="showAdvanced ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                </button>

                <div x-show="showAdvanced" x-cloak class="mt-5 space-y-5">
                    <div>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" x-model="passwordProtect"
                                   class="rounded bg-white/5 border-white/20 text-blue-600 focus:ring-blue-500/40">
                            <span class="text-sm text-white/70"><i class="fas fa-shield-alt mr-1.5 text-blue-400"></i>Password-protect every link</span>
                        </label>
                        <div x-show="passwordProtect" class="mt-2 ml-7">
                            @include('common.partials.password-field', [
                                'name' => 'password',
                                'placeholder' => 'Shared password',
                                'autocomplete' => 'new-password',
                                'wrapClass' => 'max-w-xs',
                                'inputClass' => 'w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-blue-500/40 outline-none',
                            ])
                        </div>
                    </div>

                    <div>
                        <h3 class="text-xs font-semibold text-blue-400 mb-2 uppercase tracking-wider"><i class="fas fa-search mr-1.5"></i>SEO defaults</h3>
                        <div class="space-y-2">
                            <input type="text" name="seo_title" value="{{ old('seo_title') }}" placeholder="SEO title"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-blue-500/40 outline-none">
                            <textarea name="seo_description" rows="2" placeholder="SEO description"
                                      class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-blue-500/40 outline-none">{{ old('seo_description') }}</textarea>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-xs font-semibold text-blue-400 mb-2 uppercase tracking-wider"><i class="fas fa-chart-bar mr-1.5"></i>UTM parameters</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <input type="text" name="utm_source"   value="{{ old('utm_source') }}"   placeholder="utm_source"   class="bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-blue-500/40 outline-none">
                            <input type="text" name="utm_medium"   value="{{ old('utm_medium') }}"   placeholder="utm_medium"   class="bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-blue-500/40 outline-none">
                            <input type="text" name="utm_campaign" value="{{ old('utm_campaign') }}" placeholder="utm_campaign" class="bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-blue-500/40 outline-none">
                            <input type="text" name="utm_term"     value="{{ old('utm_term') }}"     placeholder="utm_term"     class="bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-blue-500/40 outline-none">
                            <input type="text" name="utm_content"  value="{{ old('utm_content') }}"  placeholder="utm_content"  class="bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-blue-500/40 outline-none md:col-span-2">
                        </div>
                    </div>

                    @if($pixels->count())
                    <div>
                        <h3 class="text-xs font-semibold text-blue-400 mb-2 uppercase tracking-wider"><i class="fas fa-bullseye mr-1.5"></i>Tracking pixels</h3>
                        <div class="space-y-1">
                            @foreach($pixels as $pixel)
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="pixel_ids[]" value="{{ $pixel->id }}" class="rounded bg-white/5 border-white/20 text-blue-600 focus:ring-blue-500/40">
                                <span class="text-sm text-white/60">{{ $pixel->name }} <span class="text-white/25">({{ ucfirst(str_replace('_', ' ', $pixel->type)) }})</span></span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <label class="flex items-center gap-3 cursor-pointer pt-2">
                        <input type="checkbox" name="show_preview_page" value="1" class="rounded bg-white/5 border-white/20 text-blue-600 focus:ring-blue-500/40">
                        <span class="text-sm text-white/60">Show a preview page before each redirect</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('user.links.create') }}" class="px-5 py-2.5 text-sm text-white/40 hover:text-white hover:bg-white/5 rounded-xl transition-all">Cancel</a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium transition-all hover:shadow-lg hover:shadow-blue-500/20">
                Preview batch <i class="fas fa-arrow-right ml-1.5 text-xs"></i>
            </button>
        </div>
    </form>
</div>
@endsection
