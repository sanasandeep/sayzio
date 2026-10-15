<svg class="sf-art" viewBox="0 0 160 120" fill="none" aria-hidden="true" focusable="false">
<defs><linearGradient id="{{ $artId }}" x1="20" y1="10" x2="130" y2="110"><stop stop-color="var(--sf-accent)" stop-opacity=".9"/><stop offset="1" stop-color="var(--sf-accent)" stop-opacity=".25"/></linearGradient></defs>
@switch($art)
@case('link')
<g class="sf-art-bob" transform="rotate(-25 80 60)"><rect x="20" y="26" width="80" height="46" rx="23" stroke="url(#{{ $artId }})" stroke-width="12"/><rect x="61" y="51" width="80" height="46" rx="23" stroke="var(--sf-accent)" stroke-width="12"/><circle class="sf-art-dot" cx="108" cy="74" r="5" fill="white"/></g>@break
@case('qr')
<g class="sf-art-bob"><rect x="36" y="12" width="88" height="96" rx="15" fill="url(#{{ $artId }})"/><g stroke="white" stroke-width="6"><path d="M49 27h21v21H49zM89 27h21v21H89zM49 66h21v21H49z"/><path d="M88 63h14v10h9v18H88V79"/></g><path class="sf-scan" d="M29 54h102" stroke="var(--sf-accent)" stroke-width="4" stroke-linecap="round"/></g>@break
@case('calendar')
<g class="sf-art-bob"><rect x="28" y="22" width="104" height="82" rx="15" fill="url(#{{ $artId }})"/><path d="M28 48h104M51 14v18M109 14v18" stroke="var(--sf-accent)" stroke-width="7" stroke-linecap="round"/><path d="M47 65h12m17 0h12m17 0h10M47 85h12" stroke="white" stroke-width="7" stroke-linecap="round"/><path class="sf-draw" d="m79 83 9 9 20-21" stroke="white" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/></g>@break
@case('file')
<g class="sf-art-bob"><path d="M44 14h49l25 25v65H44z" fill="url(#{{ $artId }})"/><path d="M93 14v26h25" fill="var(--sf-accent)"/><path d="M57 53h43M57 64h31" stroke="white" stroke-opacity=".7" stroke-width="5" stroke-linecap="round"/><path class="sf-download" d="M80 72v23m-10-9 10 10 10-10" stroke="white" stroke-width="5" stroke-linecap="round"/></g>@break
@case('form')
<g class="sf-art-bob"><rect x="36" y="11" width="88" height="99" rx="14" fill="url(#{{ $artId }})"/><path d="M52 32h41M52 50h55M52 67h37" stroke="white" stroke-width="6" stroke-linecap="round"/><rect x="51" y="84" width="58" height="13" rx="6" fill="var(--sf-accent)"/><path class="sf-draw" d="m91 61 8 8 16-19" stroke="white" stroke-width="5" stroke-linecap="round"/></g>@break
@case('store')
<g class="sf-art-bob"><rect x="30" y="43" width="100" height="61" rx="12" fill="url(#{{ $artId }})"/><path d="m30 42 10-24h80l10 24" fill="var(--sf-accent)"/><path d="M58 18v25m24-25v25m22-25v25" stroke="white" stroke-opacity=".6" stroke-width="9"/><rect class="sf-product" x="44" y="57" width="29" height="29" rx="6" fill="white" fill-opacity=".85"/><rect x="85" y="57" width="30" height="47" rx="6" fill="white" fill-opacity=".5"/></g>@break
@case('chat')
<g class="sf-art-bob"><path d="M25 21h99v66H67l-22 18V87H25z" fill="url(#{{ $artId }})"/><g class="sf-chat-dots" fill="white"><circle cx="52" cy="53" r="6"/><circle cx="75" cy="53" r="6"/><circle cx="98" cy="53" r="6"/></g></g>@break
@default
<g class="sf-art-bob"><rect x="44" y="16" width="82" height="95" rx="14" fill="var(--sf-accent)" opacity=".2"/><rect x="30" y="9" width="82" height="95" rx="14" fill="url(#{{ $artId }})"/><circle cx="71" cy="33" r="12" fill="white" fill-opacity=".85"/><g class="sf-page-blocks" fill="white" fill-opacity=".8"><rect x="44" y="55" width="54" height="10" rx="5"/><rect x="44" y="72" width="54" height="10" rx="5"/><rect x="44" y="89" width="34" height="5" rx="2.5"/></g></g>
@endswitch
</svg>
