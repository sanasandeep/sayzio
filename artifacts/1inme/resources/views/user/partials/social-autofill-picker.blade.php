{{--
    Task #3588: reusable "Autofill from connected account" control.
    Generalizes the connection-id picker introduced for the socials block so
    ANY form field pair (handle + URL, or a single URL/handle field) can
    one-click autofill from the current user's Connected Accounts, instead of
    each form re-implementing its own copy of the same `<select>`.

    Usage:
        @include('user.partials.social-autofill-picker', [
            'connections' => $myConnections,     // Collection<SocialAccountConnection>
            'onSelect'    => 'socials[i] = { service: opt.dataset.label, value: opt.dataset.handle }',
            'buttonLabel' => 'Autofill from connected account',
        ])

    `onSelect` is raw Alpine/JS evaluated with `opt` bound to the selected
    <option> element (dataset: platform, handle, url, label). It runs inside
    the caller's existing x-data scope, so it can freely assign into whatever
    local model the surrounding form uses (an array entry, a flat field,
    etc.) — this partial makes no assumption about field shape.
--}}
@php
    $connections = $connections ?? collect();
    $buttonLabel = $buttonLabel ?? 'Autofill from connected account';
    $selectClass = $selectClass ?? 'w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-xs text-white/70 focus:ring-2 focus:ring-blue-500/40 outline-none';
@endphp
@if($connections->isNotEmpty())
    <select
        class="{{ $selectClass }}"
        @change="
            let opt = $event.target.selectedOptions[0];
            if (opt && opt.value) { {{ $onSelect }} }
            $event.target.selectedIndex = 0;
        ">
        <option value="" class="bg-[#0d0818]">{{ $buttonLabel }}…</option>
        @foreach($connections as $conn)
            <option value="{{ $conn->id }}"
                    data-platform="{{ $conn->platform }}"
                    data-handle="{{ $conn->handle }}"
                    data-url="{{ $conn->resolvedProfileUrl() }}"
                    data-label="{{ \App\Modules\User\Models\SocialAccountConnection::platformLabel($conn->platform) }}"
                    class="bg-[#0d0818]">
                {{ \App\Modules\User\Models\SocialAccountConnection::platformLabel($conn->platform) }} · @{{ $conn->handle }}
            </option>
        @endforeach
    </select>
@endif
