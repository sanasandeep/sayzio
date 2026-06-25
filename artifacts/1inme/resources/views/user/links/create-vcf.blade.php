@extends('user.layouts.app')
@section('title', 'Create Contact Card')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.create') . (!empty($prefillAlias ?? '') ? '?alias=' . urlencode($prefillAlias) : '') }}" class="text-white/30 hover:text-white/50" title="Choose a different type"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 class="text-2xl font-bold text-white">Create Contact Card</h1>
            <p class="text-xs text-white/40 mt-0.5">Step 2 of 2 &middot; <a href="{{ route('user.links.create') . (!empty($prefillAlias ?? '') ? '?alias=' . urlencode($prefillAlias) : '') }}" class="text-blue-400 hover:underline">change type</a></p>
        </div>
    </div>

    <form method="POST" action="{{ route('user.links.vcf.store') }}" enctype="multipart/form-data">
        @csrf
        @include('user.links.partials.vcf-form', ['vcf' => null])

        <div class="flex justify-end gap-3 mt-6">
            <a href="{{ route('user.links.index') }}" class="px-5 py-2.5 rounded-xl text-sm text-white/70 bg-white/5 hover:bg-white/10 border border-white/10 transition">Cancel</a>
            <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-gradient-to-r from-blue-500 to-fuchsia-500 hover:from-blue-600 hover:to-fuchsia-600 shadow-lg shadow-blue-500/30 transition">
                <i class="fas fa-check mr-1"></i> Create Contact Card
            </button>
        </div>
    </form>
</div>
@endsection
