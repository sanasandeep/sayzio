@canInWorkspace('links.edit')
<form method="POST" action="{{ route('user.links.toggle-active', $link) }}" class="inline-flex">
    @csrf
    <input type="hidden" name="is_active" value="{{ $link->is_active ? '0' : '1' }}">
    <button type="submit" class="btn-ghost text-xs py-2" style="color: var(--text-secondary);" aria-label="{{ $link->is_active ? 'Disable link' : 'Enable link' }}: {{ $link->title ?: $link->alias }}">
        <i class="fas {{ $link->is_active ? 'fa-toggle-on' : 'fa-toggle-off' }}" aria-hidden="true"></i>
        <span>{{ $link->is_active ? 'Disable link' : 'Enable link' }}</span>
    </button>
</form>
@endcanInWorkspace
