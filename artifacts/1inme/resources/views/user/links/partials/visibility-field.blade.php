{{--
    Shared "Who can see this" visibility selector for non-biolink links
    (short link / file / event / contact card). The chosen tier is stored
    on the `links.visibility` column — the single source of truth — and is
    enforced by RedirectController::enforceVisibility().

    Expects (all optional):
      $link            existing Link (for edit forms) used to seed the value
      $visInputClass   override the <select> CSS classes per form theme
      $visWrapClass    override the wrapper CSS classes
--}}
@php
    $__visValue = old('visibility', ($link ?? null)?->visibility ?? 'public');
    $__visInputClass = $visInputClass
        ?? 'w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-violet-500/40 outline-none';
@endphp
<div class="{{ $visWrapClass ?? '' }}">
    <label class="block text-sm font-medium text-white/60 mb-1.5">
        Who can see this <span class="text-white/30 font-normal">(visibility)</span>
    </label>
    <select name="visibility" class="{{ $__visInputClass }}">
        <option value="public" {{ $__visValue === 'public' ? 'selected' : '' }} class="bg-[#0d0818]">Public — anyone with the link</option>
        <option value="registered" {{ $__visValue === 'registered' ? 'selected' : '' }} class="bg-[#0d0818]">Registered — signed-in users only</option>
        <option value="followers" {{ $__visValue === 'followers' ? 'selected' : '' }} class="bg-[#0d0818]">Followers — people who follow you</option>
        <option value="subscribers" {{ $__visValue === 'subscribers' ? 'selected' : '' }} class="bg-[#0d0818]">Subscribers — active subscribers only</option>
    </select>
    <p class="text-xs text-white/30 mt-1">Restricted tiers show a locked screen to everyone else.</p>
    @error('visibility') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
</div>
