@extends('user.layouts.app')
@section('title', 'New AI Companion')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-8 space-y-6">
    <div>
        <a href="{{ route('user.ai-companions.index') }}" class="text-xs text-white/40 hover:text-white/70"><i class="fas fa-arrow-left"></i> Back</a>
        <h1 class="text-2xl font-bold text-white mt-1">New AI Companion</h1>
        <p class="text-sm text-white/50 mt-1">Wrap a Persona in a chat surface. You can change the placement and visuals later.</p>
    </div>

    @if($errors->any())
        <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if($personas->isEmpty())
        <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-200 text-sm">
            You don't have any active Personas yet.
            <a href="{{ route('user.ai-personas.create') }}" class="underline">Create one first</a>.
        </div>
    @else
        <form method="POST" action="{{ route('user.ai-companions.store') }}" class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-white/70 mb-1">Name</label>
                <input name="name" required maxlength="120" value="{{ old('name') }}" placeholder="My biolink chatbot"
                       class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
            </div>
            <div>
                <label class="block text-xs font-semibold text-white/70 mb-1">Persona</label>
                <select name="persona_id" required class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                    <option value="">Pick a Persona…</option>
                    @foreach($personas as $p)
                        <option value="{{ $p->id }}" @selected(old('persona_id')==$p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-white/70 mb-1">Placement</label>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    @foreach($placements as $key => $label)
                        <label class="rounded-xl border border-white/10 bg-white/[0.03] p-3 text-sm text-white cursor-pointer hover:bg-white/[0.06]">
                            <input type="radio" name="placement" value="{{ $key }}" @checked(old('placement', 'biolink')===$key) class="mr-2">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <a href="{{ route('user.ai-companions.index') }}" class="px-4 py-2 rounded-xl bg-white/5 text-white text-sm">Cancel</a>
                <button class="px-4 py-2 rounded-xl bg-violet-600 hover:bg-violet-500 text-white text-sm">Create Companion</button>
            </div>
        </form>
    @endif
</div>
@endsection
