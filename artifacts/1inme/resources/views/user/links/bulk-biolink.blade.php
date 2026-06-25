@extends('user.layouts.app')
@section('title', 'Bulk Create Biolink Pages')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.create') }}" class="text-white/30 hover:text-white transition-colors" title="Back"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 class="text-2xl font-bold text-white">Bulk Create Biolink Pages</h1>
            <p class="text-xs text-white/40 mt-0.5">Mail-merge a master page with a sheet of data — one personalized biolink per row.</p>
        </div>
    </div>

    @if(session('error'))
        <div class="mb-4 px-4 py-3 rounded-xl bg-red-500/10 border border-red-500/30 text-sm text-red-300">
            <i class="fas fa-exclamation-triangle mr-2"></i>{{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('user.links.biolink.bulk.preview') }}" enctype="multipart/form-data"
          x-data="{ source: '{{ old('source', $templates->isNotEmpty() ? 'template' : 'page') }}', tab: 'paste' }">
        @csrf

        {{-- Step 1 — pick a blueprint --}}
        <div class="glass rounded-2xl p-6 mb-6">
            <h2 class="text-base font-semibold text-white mb-1">Step 1 — Pick a blueprint</h2>
            <p class="text-xs text-white/40 mb-4">This master page is copied for every row. Put <code class="text-violet-300">{{ '{{token}}' }}</code> placeholders in its text, links or images and we'll fill them in from your sheet.</p>

            <div class="flex gap-1 mb-4 bg-white/5 p-1 rounded-xl w-fit">
                <button type="button" @click="source = 'template'"
                        :class="source === 'template' ? 'bg-violet-600 text-white' : 'text-white/60 hover:text-white'"
                        class="px-4 py-1.5 rounded-lg text-xs font-medium transition-all">
                    <i class="fas fa-shapes mr-1"></i> From a template
                </button>
                <button type="button" @click="source = 'page'"
                        :class="source === 'page' ? 'bg-violet-600 text-white' : 'text-white/60 hover:text-white'"
                        class="px-4 py-1.5 rounded-lg text-xs font-medium transition-all">
                    <i class="fas fa-clone mr-1"></i> From one of my pages
                </button>
            </div>

            <div x-show="source === 'template'">
                <label class="block text-sm font-medium text-white/60 mb-1.5">Template</label>
                @if($templates->isEmpty())
                    <p class="text-xs text-white/40">No templates available yet.</p>
                @else
                    <select name="template_id" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                        @foreach($templates as $t)
                            <option value="{{ $t['id'] }}" {{ old('template_id') == $t['id'] ? 'selected' : '' }} {{ $t['locked'] ? 'disabled' : '' }} class="bg-[#0d0818]">
                                {{ $t['name'] }}{{ $t['category'] ? ' · ' . $t['category'] : '' }}{{ $t['locked'] ? '  (locked — upgrade)' : '' }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>

            <div x-show="source === 'page'" x-cloak>
                <label class="block text-sm font-medium text-white/60 mb-1.5">My biolink page</label>
                @if($pages->isEmpty())
                    <p class="text-xs text-white/40">You don't have any biolink pages yet. Create one first, then come back to mail-merge it.</p>
                @else
                    <select name="link_id" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                        @foreach($pages as $p)
                            <option value="{{ $p['id'] }}" {{ old('link_id') == $p['id'] ? 'selected' : '' }} class="bg-[#0d0818]">{{ $p['label'] }}</option>
                        @endforeach
                    </select>
                @endif
            </div>

            <input type="hidden" name="source" :value="source">
        </div>

        {{-- Step 2 — the data sheet --}}
        <div class="glass rounded-2xl p-6 mb-6">
            <div class="flex items-start justify-between gap-3 mb-1">
                <h2 class="text-base font-semibold text-white">Step 2 — Add your data</h2>
                <button type="submit" formaction="{{ route('user.links.biolink.bulk.sample') }}" formtarget="_blank"
                        class="text-[11px] text-violet-300 hover:text-violet-200 whitespace-nowrap">
                    <i class="fas fa-download mr-1"></i> Sample CSV for this blueprint
                </button>
            </div>
            <p class="text-xs text-white/40 mb-4">
                First row = column headers. Reserved columns: <code class="text-violet-300">handle</code> (the page URL — blank to auto-generate) and <code class="text-violet-300">title</code>. Every other column is matched to a <code class="text-violet-300">{{ '{{token}}' }}</code>. Up to {{ $maxRows }} rows.
            </p>

            <div class="flex gap-1 mb-4 bg-white/5 p-1 rounded-xl w-fit">
                <button type="button" @click="tab = 'paste'"
                        :class="tab === 'paste' ? 'bg-violet-600 text-white' : 'text-white/60 hover:text-white'"
                        class="px-4 py-1.5 rounded-lg text-xs font-medium transition-all">
                    <i class="fas fa-paste mr-1"></i> Paste a table
                </button>
                <button type="button" @click="tab = 'file'"
                        :class="tab === 'file' ? 'bg-violet-600 text-white' : 'text-white/60 hover:text-white'"
                        class="px-4 py-1.5 rounded-lg text-xs font-medium transition-all">
                    <i class="fas fa-file-csv mr-1"></i> Upload CSV / Excel
                </button>
            </div>

            <div x-show="tab === 'paste'">
                <label class="block text-sm font-medium text-white/60 mb-1.5">Paste from a spreadsheet <span class="text-white/30 text-xs">(tab- or comma-separated)</span></label>
                <textarea name="sheet_text" rows="10"
                          placeholder="handle	title	first_name	city&#10;jane-doe	Jane Doe	Jane	Berlin&#10;rui-lee	Rui Lee	Rui	Lisbon"
                          class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 outline-none font-mono">{{ old('sheet_text') }}</textarea>
            </div>

            <div x-show="tab === 'file'" x-cloak>
                <label class="block text-sm font-medium text-white/60 mb-1.5">CSV or Excel file</label>
                <input type="file" name="sheet_file" accept=".csv,.xlsx,text/csv"
                       class="block w-full text-sm text-white/70 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-violet-600 file:text-white hover:file:bg-violet-700">
                <p class="text-[11px] text-white/40 mt-2">Accepts <code class="text-violet-300">.csv</code> and <code class="text-violet-300">.xlsx</code>. The first sheet is used.</p>
            </div>
        </div>

        {{-- Step 3 — shared settings --}}
        <div class="glass rounded-2xl p-6 mb-6">
            <h2 class="text-base font-semibold text-white mb-1">Step 3 — Shared settings</h2>
            <p class="text-xs text-white/40 mb-4">Applied to every page in the batch.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @if(($domains ?? collect())->isNotEmpty())
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1.5">Branded domain</label>
                    <select name="domain_id" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                        <option value="" class="bg-[#0d0818]">Default</option>
                        @foreach($domains as $d)
                            <option value="{{ $d->id }}" {{ old('domain_id') == $d->id ? 'selected' : '' }} class="bg-[#0d0818]">{{ $d->domain }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1.5">Project</label>
                    <select name="project_id" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                        <option value="" class="bg-[#0d0818]">No project</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" {{ old('project_id') == $project->id ? 'selected' : '' }} class="bg-[#0d0818]">{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('user.links.create') }}" class="px-5 py-2.5 text-sm text-white/40 hover:text-white hover:bg-white/5 rounded-xl transition-all">Cancel</a>
            <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium transition-all hover:shadow-lg hover:shadow-violet-500/20">
                Preview batch <i class="fas fa-arrow-right ml-1.5 text-xs"></i>
            </button>
        </div>
    </form>
</div>
@endsection
