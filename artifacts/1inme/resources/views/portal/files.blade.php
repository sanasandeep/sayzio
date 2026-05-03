@extends('portal.layout')
@section('title', 'Files')
@section('content')
<h1 class="text-xl font-bold mb-4">Shared files</h1>

@forelse($folders as $folder)
    <div class="bg-white border border-slate-200 rounded-xl mb-4 overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 bg-slate-50 flex items-center gap-2">
            <i class="fas fa-folder brand-text"></i>
            <span class="font-semibold text-sm">{{ $folder['share']->label ?: $folder['path'] }}</span>
            <span class="text-xs text-slate-500">({{ $folder['provider'] }})</span>
            <span class="ml-auto text-xs text-slate-500">{{ $folder['files']->count() }} file(s)</span>
        </div>
        <ul class="divide-y divide-slate-100">
            @forelse($folder['files'] as $file)
                <li class="px-4 py-2.5 flex items-center gap-3 text-sm">
                    <i class="far fa-file text-slate-400"></i>
                    <span class="flex-1 truncate">{{ $file->name }}</span>
                    <span class="text-xs text-slate-400 hidden sm:inline">{{ $file->humanSize() }}</span>
                    <a href="{{ route('portal.files.download', $file->id) }}" class="brand-btn px-3 py-1 rounded text-xs">
                        <i class="fas fa-download mr-1"></i>Download
                    </a>
                </li>
            @empty
                <li class="px-4 py-3 text-sm text-slate-400">No files in this folder.</li>
            @endforelse
        </ul>
    </div>
@empty
    <div class="bg-white border border-dashed border-slate-300 rounded-xl p-10 text-center text-slate-500">
        No folders shared with you yet.
    </div>
@endforelse
@endsection
