@extends('user.layouts.app')
@section('title', 'New AI Persona')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8 space-y-6">
    <div>
        <a href="{{ route('user.ai-personas.index') }}" class="text-xs text-white/50 hover:text-white"><i class="fas fa-arrow-left"></i> Back</a>
        <h1 class="text-2xl font-bold text-white mt-2">New AI Persona</h1>
        <p class="text-sm text-white/50 mt-1">Pick a template to seed the system prompt and tone — you can customize everything after.</p>
    </div>

    <form method="POST" action="{{ route('user.ai-personas.store') }}" class="space-y-6">
        @csrf
        <div>
            <label class="text-[11px] uppercase tracking-wider text-white/50">Name</label>
            <input type="text" name="name" required maxlength="120" value="{{ old('name', 'My Persona') }}"
                class="mt-1 w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
            @error('name')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
        </div>

        <div x-data="{ tpl: '{{ old('template', 'blank') }}' }">
            <label class="text-[11px] uppercase tracking-wider text-white/50">Starter template</label>
            <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-2">
                @foreach($templates as $key => $tpl)
                    <label class="cursor-pointer rounded-xl border p-3 transition"
                        :class="tpl === '{{ $key }}' ? 'border-pink-500/50 bg-pink-500/10' : 'border-white/10 bg-white/[0.03] hover:bg-white/5'">
                        <input type="radio" name="template" value="{{ $key }}" x-model="tpl" class="sr-only">
                        <p class="text-white text-sm font-semibold">{{ $tpl['label'] }}</p>
                        <p class="text-xs text-white/50 mt-0.5">{{ $tpl['description'] }}</p>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('user.ai-personas.index') }}" class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-white text-sm">Cancel</a>
            <button class="px-4 py-2 rounded-xl bg-pink-600 hover:bg-pink-500 text-white text-sm">Create Persona</button>
        </div>
    </form>
</div>
@endsection
