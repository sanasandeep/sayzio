@extends('user.layouts.app')

@section('title', 'Edit contact')

@section('content')
<div class="max-w-3xl mx-auto">
    <a href="{{ route('user.contacts.show', $contact) }}" class="inline-flex items-center gap-1 text-xs mb-4" style="color:var(--text-muted);">
        <i class="fas fa-arrow-left"></i> Back
    </a>
    <div class="card-premium p-6">
        <h2 class="text-lg font-bold mb-4" style="color:var(--text-primary);">Edit contact</h2>
        @if($errors->any())
            <div class="mb-4 px-4 py-3 rounded-xl text-xs" style="background:rgba(239,68,68,.10);color:#ef4444;border:1px solid rgba(239,68,68,.20)">
                @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif
        <form method="POST" action="{{ route('user.contacts.update', $contact) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            @include('user.contacts._form', ['contact' => $contact, 'phoneLabels' => $phoneLabels, 'emailLabels' => $emailLabels])
            <div class="mt-6 flex justify-between">
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background:linear-gradient(135deg,#7c3aed,#ec4899);">
                        <i class="fas fa-check mr-1"></i> Save changes
                    </button>
                    <a href="{{ route('user.contacts.show', $contact) }}" class="px-4 py-2 rounded-lg text-sm font-semibold" style="background:rgba(255,255,255,.05);color:var(--text-muted);border:1px solid rgba(255,255,255,.10)">Cancel</a>
                </div>
            </div>
        </form>
        <form method="POST" action="{{ route('user.contacts.destroy', $contact) }}" class="mt-4" onsubmit="return confirm('Delete this contact? This will also remove it from Google on your next sync.')">
            @csrf @method('DELETE')
            <button type="submit" class="text-xs font-medium" style="color:#ef4444;"><i class="fas fa-trash mr-1"></i> Delete contact</button>
        </form>
    </div>
</div>
@endsection
