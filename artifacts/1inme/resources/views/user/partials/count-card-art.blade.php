<svg class="ov-count-art" viewBox="0 0 180 150" fill="none" aria-hidden="true" focusable="false">
    <defs>
        <linearGradient id="{{ $artId }}-face" x1="30" y1="20" x2="145" y2="130" gradientUnits="userSpaceOnUse"><stop stop-color="#fff"/><stop offset="1" stop-color="#cbd8f4"/></linearGradient>
        <linearGradient id="{{ $artId }}-edge" x1="20" y1="20" x2="145" y2="130" gradientUnits="userSpaceOnUse"><stop stop-color="#fff" stop-opacity=".75"/><stop offset="1" stop-color="#fff" stop-opacity=".15"/></linearGradient>
    </defs>
    @switch($kind)
        @case('links')
        <g transform="rotate(-30 90 75)">
            <rect x="23" y="31" width="91" height="58" rx="29" stroke="#070d28" stroke-opacity=".22" stroke-width="17" transform="translate(0 7)"/>
            <rect x="23" y="31" width="91" height="58" rx="29" stroke="url(#{{ $artId }}-edge)" stroke-width="16"/>
            <rect x="65" y="61" width="91" height="58" rx="29" stroke="#070d28" stroke-opacity=".2" stroke-width="17" transform="translate(0 7)"/>
            <rect x="65" y="61" width="91" height="58" rx="29" stroke="url(#{{ $artId }}-face)" stroke-width="16"/>
            <path d="M90 37h-36c-11 0-18 7-18 18" stroke="white" stroke-opacity=".65" stroke-width="2" stroke-linecap="round"/>
            <path d="M134 67h-34c-11 0-18 7-18 18" stroke="white" stroke-width="2" stroke-linecap="round"/>
        </g>
        @break
        @case('pages')
        <g transform="rotate(12 90 75)">
            <rect x="53" y="21" width="97" height="112" rx="12" fill="white" fill-opacity=".18" stroke="white" stroke-opacity=".3"/>
            <rect x="39" y="15" width="97" height="112" rx="12" fill="url(#{{ $artId }}-edge)"/>
            <rect x="25" y="9" width="97" height="112" rx="12" fill="url(#{{ $artId }}-face)"/>
            <path d="M25 31h97" stroke="#9caacd" stroke-opacity=".4"/>
            <circle cx="37" cy="21" r="2.5" fill="currentColor" opacity=".45"/><circle cx="46" cy="21" r="2.5" fill="currentColor" opacity=".25"/><circle cx="55" cy="21" r="2.5" fill="currentColor" opacity=".15"/>
            <rect x="36" y="43" width="75" height="30" rx="6" fill="var(--ov-color)" opacity=".65"/>
            <path d="M37 85h51M37 94h72M37 103h42" stroke="var(--ov-tone)" stroke-opacity=".35" stroke-width="4" stroke-linecap="round"/>
        </g>
        @break
        @case('folders')
        <g transform="rotate(-9 90 75)">
            <path d="M26 40a10 10 0 0 1 10-10h35l12 12h60a10 10 0 0 1 10 10v66H26V40Z" fill="url(#{{ $artId }}-edge)"/>
            <rect x="44" y="26" width="90" height="81" rx="7" fill="#cbd8f4"/>
            <rect x="36" y="36" width="107" height="78" rx="7" fill="white"/>
            <path d="M48 48h49M48 57h70" stroke="var(--ov-color)" stroke-opacity=".3" stroke-width="4" stroke-linecap="round"/>
            <path d="M19 70a11 11 0 0 1 11-11h122a11 11 0 0 1 11 11l-7 45a12 12 0 0 1-12 10H38a12 12 0 0 1-12-10l-7-45Z" fill="url(#{{ $artId }}-face)"/>
            <path d="M31 69h120" stroke="white" stroke-width="2" stroke-linecap="round"/>
            <path d="M43 103h24" stroke="var(--ov-tone)" stroke-opacity=".35" stroke-width="4" stroke-linecap="round"/>
        </g>
        @break
        @case('qr')
        <g transform="rotate(12 90 75)">
            <rect x="36" y="15" width="111" height="118" rx="16" fill="white" fill-opacity=".20" stroke="white" stroke-opacity=".25"/>
            <rect x="27" y="6" width="111" height="118" rx="16" fill="url(#{{ $artId }}-face)"/>
            <g stroke="var(--ov-tone)" stroke-width="5">
                <rect x="43" y="23" width="26" height="26" rx="3"/><rect x="96" y="23" width="26" height="26" rx="3"/><rect x="43" y="76" width="26" height="26" rx="3"/>
            </g>
            <g fill="var(--ov-tone)">
                <rect x="52" y="32" width="8" height="8" rx="1"/><rect x="105" y="32" width="8" height="8" rx="1"/><rect x="52" y="85" width="8" height="8" rx="1"/>
                <path d="M78 22h8v17h-8zM77 47h9v15h-9zM43 58h16v8H43zM64 59h7v8h-7zM94 58h19v8H94zM116 58h8v21h-8zM78 73h9v14h-9zM94 74h8v8h-8zM103 88h20v8h-20zM77 96h17v8H77zM116 101h8v8h-8z"/>
            </g>
        </g>
        @break
    @endswitch
</svg>
