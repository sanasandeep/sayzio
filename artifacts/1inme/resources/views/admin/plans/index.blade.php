@extends('admin.layouts.app')
@section('title', 'Plans')
@section('page-title', 'Subscription Plans')

@section('content')
<div x-data="{ importOpen: false }" class="flex items-center justify-between mb-6">
    <p class="text-sm text-white/40">Manage subscription plans and pricing</p>
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.plans.export') }}" class="px-4 py-2 bg-white/10 text-white/80 rounded-xl text-sm font-medium hover:bg-white/20 transition">
            <i class="fas fa-download mr-2"></i>Export CSV
        </a>
        <button type="button" @click="importOpen = true" class="px-4 py-2 bg-white/10 text-white/80 rounded-xl text-sm font-medium hover:bg-white/20 transition">
            <i class="fas fa-upload mr-2"></i>Import CSV
        </button>
        <a href="{{ route('admin.plans.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700 transition">
            <i class="fas fa-plus mr-2"></i>Add Plan
        </a>
    </div>

    {{-- Import upload modal --}}
    <div x-show="importOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="importOpen = false">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="importOpen = false"></div>
        <div class="relative w-full max-w-lg glass rounded-2xl border border-white/10 p-6">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h2 class="text-lg font-semibold text-white/90">Import plan changes</h2>
                    <p class="text-xs text-white/50 mt-1 leading-relaxed">
                        Upload a CSV in the same format as the export. Rows are matched to
                        plans by their <span class="font-mono text-white/70">Slug</span>, and you'll
                        see a diff of every change before anything is saved.
                    </p>
                </div>
                <button type="button" @click="importOpen = false" class="text-white/40 hover:text-white/80">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            @if(session('error'))
            <div class="rounded-xl px-4 py-3 mb-4 bg-rose-500/10 border border-rose-500/30 text-rose-200 text-sm">
                <i class="fas fa-exclamation-circle mr-1.5"></i>{{ session('error') }}
            </div>
            @endif
            @if ($errors->any())
            <div class="rounded-xl px-4 py-3 mb-4 bg-rose-500/10 border border-rose-500/30 text-rose-200 text-sm">
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
            @endif

            <form method="POST" action="{{ route('admin.plans.import.preview') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[11px] uppercase tracking-wider text-white/50 font-bold mb-1.5">
                        CSV file <span class="text-white/30 normal-case">(max 4 MB)</span>
                    </label>
                    <input type="file" name="file" required accept=".csv,text/csv,text/plain"
                           class="block w-full text-sm text-white/80 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-white/10 file:text-white/80 hover:file:bg-white/15">
                    <p class="text-[11px] text-white/40 mt-2">
                        Tip: click <span class="text-white/60">Export CSV</span> first, edit the values,
                        then upload the edited file here. Unknown slugs are skipped.
                    </p>
                </div>
                <div class="flex items-center gap-3 pt-1">
                    <button type="submit" class="px-4 py-2 rounded-xl text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white">
                        <i class="fas fa-eye mr-2"></i>Preview changes
                    </button>
                    <button type="button" @click="importOpen = false" class="text-sm text-white/60 hover:text-white">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach($plans as $plan)
        @include('admin.plans.partials._card', ['plan' => $plan])
    @endforeach
</div>

@if(isset($archivedPlans) && $archivedPlans->count() > 0)
<div x-data="{ open: false }" class="mt-10">
    <button type="button" @click="open = !open" class="flex items-center gap-2 text-sm text-white/50 hover:text-white/80 transition">
        <i class="fas" :class="open ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
        Archived plans ({{ $archivedPlans->count() }})
        <span class="text-white/30">— legacy plans kept for existing subscribers</span>
    </button>
    <div x-show="open" x-cloak class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
        @foreach($archivedPlans as $plan)
            @include('admin.plans.partials._card', ['plan' => $plan])
        @endforeach
    </div>
</div>
@endif
@endsection
