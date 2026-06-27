@extends('user.layouts.app')
@section('title', 'Edit Contact Card')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('user.links.show', $link) }}" class="text-white/30 hover:text-white/50" title="Back to link"><i class="fas fa-arrow-left"></i></a>
            <div>
                <h1 class="text-2xl font-bold text-white">Edit Contact Card</h1>
                <p class="text-xs text-white/40 mt-0.5">{{ $vcf->fullName() ?: 'Untitled' }}</p>
            </div>
        </div>
        <a href="{{ route('user.links.show', $link) }}" class="text-xs text-white/50 hover:text-white">View link &rarr;</a>
    </div>

    <div class="mb-6">
        @include('user.links.partials.aliases-card', ['link' => $link])
    </div>

    <form method="POST" action="{{ route('user.links.vcf.update', $link) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('user.links.partials.vcf-form')

        <div class="flex justify-end gap-3 mt-6">
            <a href="{{ route('user.links.show', $link) }}" class="px-5 py-2.5 rounded-xl text-sm text-white/70 bg-white/5 hover:bg-white/10 border border-white/10 transition">Cancel</a>
            <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-gradient-to-r from-blue-500 to-fuchsia-500 hover:from-blue-600 hover:to-fuchsia-600 shadow-lg shadow-blue-500/30 transition">
                <i class="fas fa-save mr-1"></i> Save Changes
            </button>
        </div>
    </form>

    @include('user.links.partials.embed-panel', ['link' => $link])
</div>
@endsection
