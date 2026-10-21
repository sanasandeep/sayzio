@extends('user.layouts.app')
@section('title', 'Create Contact Card')

@section('content')
@include('user.links.partials.create-premium-style')
<div>
    @include('user.partials.create-step-header', [
        'title' => 'Create Contact Card',
        'icon' => 'fa-address-card',
    ])


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
