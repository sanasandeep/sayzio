@extends('user.layouts.app')
@section('title', 'New Buzz')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('user.social-proofs.index') }}" class="text-white/50 hover:text-white text-sm"><i class="fas fa-arrow-left mr-1"></i> Back</a>
        <h1 class="text-2xl font-bold text-white mt-2">Create a Buzz campaign</h1>
        <p class="text-white/40 text-sm mt-1">Give it a name — you'll choose notification types and design in the editor.</p>
    </div>

    @if($errors->any())
    <div class="mb-4 px-4 py-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-sm">
        @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
    </div>
    @endif

    <form method="POST" action="{{ route('user.social-proofs.store') }}" class="space-y-4">
        @csrf
        <div class="glass rounded-2xl p-5">
            <label class="block text-white/70 text-sm mb-2">Campaign name</label>
            <input type="text" name="name" required value="{{ old('name', 'My notifications') }}" maxlength="120"
                   class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white placeholder-white/30 focus:outline-none focus:border-blue-500">
            <p class="text-white/40 text-xs mt-2">A campaign holds one or more notifications (recent activity, countdowns, banners, popups, etc.) — embed once, manage many.</p>
        </div>
        <div class="flex justify-end gap-2">
            <a href="{{ route('user.social-proofs.index') }}" class="px-4 py-2 rounded-xl text-white/70 hover:bg-white/5 text-sm">Cancel</a>
            <button class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-xl text-sm font-medium">Create &amp; open editor</button>
        </div>
    </form>
</div>
@endsection
