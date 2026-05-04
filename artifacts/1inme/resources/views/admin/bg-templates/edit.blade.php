@extends('admin.layouts.app')
@section('title', 'Edit Background Template')
@section('page-title', 'Edit: ' . $template->name)

@section('content')
<div class="max-w-4xl">
    @if(session('success'))
        <div class="rounded-xl px-4 py-3 bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 text-sm mb-4">
            {{ session('success') }}
        </div>
    @endif
    <div class="glass rounded-2xl border border-white/10 p-6">
        <form method="POST" action="{{ route('admin.bg-templates.update', $template) }}" class="space-y-5">
            @csrf
            @method('PUT')
            @include('admin.bg-templates._form', ['template' => $template, 'categories' => $categories])
            <div class="flex items-center justify-between gap-3 pt-2">
                <div class="flex items-center gap-3">
                    <button type="submit" class="px-4 py-2 rounded-xl text-sm font-medium bg-violet-600 hover:bg-violet-700 text-white">
                        Save changes
                    </button>
                    <a href="{{ route('admin.bg-templates.index') }}" class="text-sm text-white/60 hover:text-white">Back</a>
                </div>
            </div>
        </form>
        <form method="POST" action="{{ route('admin.bg-templates.destroy', $template) }}"
              onsubmit="return confirm('Delete &quot;{{ addslashes($template->name) }}&quot;? This cannot be undone.');"
              class="mt-6 pt-5 border-t border-white/10">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm text-rose-300 hover:text-rose-200">
                <i class="fas fa-trash text-xs mr-1"></i> Delete this template
            </button>
        </form>
    </div>
</div>
@endsection
