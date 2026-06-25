@extends('user.layouts.app')

@section('title', 'New contact')

@section('content')
<div class="max-w-3xl mx-auto">
    <a href="{{ route('user.contacts.index') }}" class="inline-flex items-center gap-1 text-xs mb-4" style="color:var(--text-muted);">
        <i class="fas fa-arrow-left"></i> Back to contacts
    </a>
    <div class="card-premium p-6">
        <h2 class="text-lg font-bold mb-4" style="color:var(--text-primary);">New contact</h2>
        @if($errors->any())
            <div class="mb-4 px-4 py-3 rounded-xl text-xs" style="background:rgba(239,68,68,.10);color:#ef4444;border:1px solid rgba(239,68,68,.20)">
                @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif
        <form method="POST" action="{{ route('user.contacts.store') }}" enctype="multipart/form-data">
            @csrf
            @include('user.contacts._form', ['contact' => null, 'phoneLabels' => $phoneLabels, 'emailLabels' => $emailLabels, 'prefillPhone' => $prefillPhone])
            <div class="mt-6 flex gap-2">
                <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background:linear-gradient(135deg,#3d6bff,#ec4899);">
                    <i class="fas fa-check mr-1"></i> Save contact
                </button>
                <a href="{{ route('user.contacts.index') }}" class="px-4 py-2 rounded-lg text-sm font-semibold" style="background:rgba(255,255,255,.05);color:var(--text-muted);border:1px solid rgba(255,255,255,.10)">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
