@php
    $allowRemove = $allowRemove ?? false;
    $removeLabel = $removeLabel ?? 'Remove';
@endphp
<form method="POST" action="{{ route('admin.banned-names.conflicts.resolve', $item) }}"
      class="flex items-center justify-end gap-2 flex-wrap">
    @csrf
    <input type="hidden" name="type" value="{{ $type }}">
    <input type="hidden" name="id" value="{{ $id }}">
    <input type="text" name="new_value"
           placeholder="new name"
           pattern="[A-Za-z0-9_\-]+"
           maxlength="100"
           class="px-2.5 py-1.5 rounded-lg text-xs bg-white/5 border border-white/10 text-white/90 placeholder-white/30 font-mono focus:outline-none focus:border-blue-500/50 ak-strong ak-input">
    <button type="submit" name="action" value="rename"
            class="px-2.5 py-1.5 rounded-lg text-xs bg-blue-600 hover:bg-blue-700 text-white inline-flex items-center gap-1.5">
        <i class="fas fa-pen text-[10px]"></i> Rename
    </button>
    @if($allowRemove)
        <button type="submit" name="action" value="remove"
                onclick="return window.themedConfirmAction(this, {title: '{{ $removeLabel }}?', message: 'This cannot be undone.', confirmText: '{{ $removeLabel }}', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})"
                class="px-2.5 py-1.5 rounded-lg text-xs bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/30 inline-flex items-center gap-1.5 ak-red">
            <i class="fas fa-trash text-[10px]"></i> {{ $removeLabel }}
        </button>
    @endif
</form>
