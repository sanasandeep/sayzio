@php
    $isCause = $link->type === 'nonprofit_campaign';
    $accent = $isCause ? '#db2777' : '#0d9488';
@endphp
<section class="glass rounded-2xl p-6 mb-6" style="border-top:3px solid {{ $accent }};background:linear-gradient(135deg,color-mix(in srgb,{{ $accent }} 7%,var(--bg-card)),var(--bg-card))">
    <div class="flex flex-wrap gap-4 items-start justify-between mb-4">
        <div>
            <h2 class="text-lg font-bold">{{ $isCause ? 'Build support for your cause' : 'Bring your community together' }}</h2>
            <p class="text-sm mt-1" style="color:var(--text-dim)">{{ $isCause ? 'Tell your story, share campaigns and invite people to volunteer or contribute.' : 'Introduce your club, share events and announcements, and give visitors a way to join.' }}</p>
        </div>
        <a class="btn-ghost" href="{{ route('user.links.settings.appearance',$link) }}"><i class="fas fa-palette mr-2"></i>How it looks</a>
    </div>
    @canInWorkspace('inbox.view')
    <a class="btn-ghost inline-block mb-3" href="{{ route('user.inbox.index') }}"><i class="fas fa-inbox mr-2"></i>{{ $isCause ? 'Volunteer requests' : 'Membership requests' }}</a>
    @endcanInWorkspace
    @if(!$isCause && \Illuminate\Support\Facades\Route::has('user.links.memberships'))
    <a class="btn-ghost inline-block mb-3" href="{{ route('user.links.memberships',$link) }}"><i class="fas fa-users mr-2"></i>Manage members</a>
    @endif
    @include('user.links.partials.business-starter-picker', ['link'=>$link])
    <p class="text-xs mt-3" style="color:var(--text-dim)">Starters are hidden drafts. Edit each block below, replace sample links, then show the blocks you want visitors to see.</p>
    @if($isCause)
    <details class="mt-4 rounded-xl border p-4" style="border-color:var(--border-glass)">
        <summary class="cursor-pointer font-semibold">Donation options</summary>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
            <div><h3 class="font-semibold text-sm">External donation link</h3><p class="text-sm mt-1" style="color:var(--text-dim)">Edit the Donation block and add your donation website URL.</p></div>
            <div><h3 class="font-semibold text-sm">Integrated contributions</h3><p class="text-sm mt-1" style="color:var(--text-dim)">Edit the Tip Jar block to set amounts and a message. Checkout uses your connected payment provider and account currency. A gateway enabled for charges is required.</p>
            @if((int) auth()->id() === (int) workspace_owner_id())<a class="inline-block text-sm mt-2" href="{{ route('user.payouts.show') }}">Connect a payment gateway <i class="fas fa-arrow-right"></i></a>@endif
            @canInWorkspace('posts.view')<a class="inline-block text-sm mt-2 ml-3" href="{{ route('user.monetization.payments') }}">View payments <i class="fas fa-arrow-right"></i></a>@endcanInWorkspace
            </div>
        </div>
    </details>
    @endif
</section>
