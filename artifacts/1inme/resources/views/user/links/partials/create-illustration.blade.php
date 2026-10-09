<span class="create-art" aria-hidden="true"><svg viewBox="0 0 112 70" fill="none">
@if($kind === 'url')
<rect class="art-tint" x="3" y="12" width="74" height="25" rx="7"/><rect class="art-line" x="3" y="12" width="74" height="25" rx="7"/><path class="art-line" d="M13 24h44m6 0h4"/><path class="art-line" d="M28 42v9h18m-4-4 4 4-4 4"/><g class="art-motion"><rect class="art-surface" x="54" y="40" width="51" height="24" rx="7"/><rect class="art-line" x="54" y="40" width="51" height="24" rx="7"/><path class="art-line" d="M65 52h27"/></g>
@elseif($kind === 'biolink')
<rect class="art-tint" x="25" y="3" width="60" height="64" rx="8"/><rect class="art-line" x="25" y="3" width="60" height="64" rx="8"/><circle class="art-line" cx="55" cy="18" r="6"/><path class="art-line" d="M43 30h24"/><g class="art-motion"><rect class="art-surface" x="36" y="38" width="38" height="9" rx="4"/><rect class="art-line" x="36" y="38" width="38" height="9" rx="4"/><rect class="art-line" x="36" y="52" width="38" height="7" rx="3"/></g>
@elseif($kind === 'business')
<rect class="art-tint" x="19" y="27" width="75" height="37" rx="4"/><path class="art-line" d="M19 31v33h75V31M29 64V43h22v21m14-19h18v10H65z"/><g class="art-motion"><path class="art-surface" d="m17 29 8-18h64l8 18z"/><path class="art-line" d="m17 29 8-18h64l8 18H17Zm0 0c0 10 16 10 16 0m0 0c0 10 16 10 16 0m0 0c0 10 16 10 16 0m0 0c0 10 16 10 16 0m0 0c0 10 16 10 16 0M35 11l-2 18m17-18-1 18m15-18 1 18m14-18 2 18"/></g>
@else
<rect class="art-tint" x="12" y="10" width="32" height="23" rx="5"/><rect class="art-line" x="12" y="10" width="32" height="23" rx="5"/><path class="art-line" d="M22 19h12m-12 6h8"/><rect class="art-line" x="12" y="43" width="32" height="20" rx="5"/><path class="art-line" d="m23 49 9 4-9 4z"/><g class="art-motion"><path class="art-tint" d="m72 9 17 10v20L72 49 55 39V19z"/><path class="art-line" d="m72 9 17 10v20L72 49 55 39V19l17-10Zm0 0v20m-17-10 17 10 17-10m-17 10v20"/></g><circle class="art-line" cx="83" cy="60" r="5"/>
@endif
</svg></span>
