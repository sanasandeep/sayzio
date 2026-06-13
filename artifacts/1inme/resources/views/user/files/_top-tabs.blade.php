@php
    use App\Modules\User\Services\WorkspacePermissions as WP;
    $canVault = WP::userCan('links.view');
    $canCloud = WP::userCan('files.view');
    $vaultActive = request()->routeIs('user.files.*');
    $cloudActive = request()->routeIs('user.cloud-files.*') || request()->routeIs('user.cloud-oauth.*');
    if ($vaultActive) {
        session(['files_last_tab' => 'vault']);
    } elseif ($cloudActive) {
        session(['files_last_tab' => 'cloud']);
    }
@endphp
@if($canVault || $canCloud)
<div class="flex flex-wrap items-center gap-2 mb-5">
    @if($canVault)
    <a href="{{ route('user.files.index') }}"
       class="px-4 py-2 rounded-xl text-sm font-semibold transition-all {{ $vaultActive ? 'bg-violet-500/20 text-violet-200 ring-1 ring-violet-500/30' : 'text-gray-300 hover:bg-white/5' }}"
       style="{{ $vaultActive ? '' : 'background: var(--bg-glass);' }}">
        <i class="fas fa-hard-drive mr-1.5"></i> My Vault
    </a>
    @endif
    @if($canCloud)
    <a href="{{ route('user.cloud-files.index') }}"
       class="px-4 py-2 rounded-xl text-sm font-semibold transition-all {{ $cloudActive ? 'bg-cyan-500/20 text-cyan-200 ring-1 ring-cyan-500/30' : 'text-gray-300 hover:bg-white/5' }}"
       style="{{ $cloudActive ? '' : 'background: var(--bg-glass);' }}">
        <i class="fas fa-cloud mr-1.5"></i> Cloud Library
    </a>
    @endif
</div>
@endif
