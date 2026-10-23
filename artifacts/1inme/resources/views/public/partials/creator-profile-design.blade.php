<style>
html[data-profile-theme="paper"]{--cp-cover:linear-gradient(135deg,#cbdafa,#e4d8f7)}
html[data-profile-theme="warm"]{--cp-cover:linear-gradient(135deg,#dfb78b,#ebd7c5)}
html[data-profile-theme="mint"]{--cp-cover:linear-gradient(135deg,#a3cdb9,#d6e6c8)}
html[data-profile-theme="midnight"]{--cp-cover:linear-gradient(135deg,#252b42,#39304d)}
.cp-avatar{width:{{ $__presentation['avatar_size'] }}px;height:{{ $__presentation['avatar_size'] }}px;border-radius:{{ $__presentation['avatar_shape'] === 'circle' ? '50%' : ($__presentation['avatar_shape'] === 'square' ? '4px' : '20px') }};object-position:{{ $__presentation['avatar_x'] }}% {{ $__presentation['avatar_y'] }}%;flex-shrink:0}
html[data-profile-theme="warm"] body{background:#f5f0e8}html[data-profile-theme="warm"] .cp-card{background:#fffcf7;border-color:#e0d6c8}
html[data-profile-theme="mint"] body{background:#eef6f1}html[data-profile-theme="mint"] .cp-card{background:#fcfffd;border-color:#ccdfd2}
html[data-profile-mode="dark"]{color-scheme:dark}
html[data-profile-mode="dark"] body{background:#101218;color:#e5e9f2}
html[data-profile-mode="dark"] :is(.cp-card,.cp-tab-bar){background:#1b1f29;color:#e5e9f2;border-color:#353c4d}
html[data-profile-mode="dark"] :is(.text-slate-500,.text-slate-600,.text-slate-700,.text-slate-800,.text-slate-900){color:#bac3d3}
html[data-profile-mode="dark"] .max-w-3xl :is(.bg-white,.bg-slate-50,.bg-blue-50){background:#242a37;border-color:#424d62;color:#d7e0f1}
html[data-profile-mode="dark"] .cp-share-button{border-color:#424d62}
html[data-profile-theme] .cp-card{box-shadow:0 6px 24px rgba(15,23,42,.035)}

html[data-profile-theme="paper"]{--cp-page:linear-gradient(145deg,#edf2ff,#f4edfc 55%,#f8faff)}
html[data-profile-theme="warm"]{--cp-page:linear-gradient(145deg,#fff1e5,#f9e9ee 60%,#fff9f0);--cp-cover:linear-gradient(135deg,#e7ab7d,#e8a8bd)}
html[data-profile-theme="mint"]{--cp-page:linear-gradient(145deg,#e3f5ed,#e8effb 60%,#f2faf7);--cp-cover:linear-gradient(135deg,#78bea9,#9dbfe8)}
html[data-profile-theme="midnight"]{--cp-page:linear-gradient(145deg,#e8eafa,#f1e7f5 60%,#f4f3fb);--cp-cover:linear-gradient(135deg,#7984c9,#ad86bc)}
html[data-profile-mode="light"] body{background:var(--cp-page);color:#172033;background-attachment:fixed}
html[data-profile-mode="light"] .cp-card{background:#ffffffed;border-color:#64748b26}
html[data-profile-theme="paper"][data-profile-mode="dark"]{--cp-page:linear-gradient(145deg,#111827,#201b30);--cp-cover:linear-gradient(135deg,#35466b,#52416a)}
html[data-profile-theme="warm"][data-profile-mode="dark"]{--cp-page:linear-gradient(145deg,#211a1a,#251924);--cp-cover:linear-gradient(135deg,#704b3c,#644052)}
html[data-profile-theme="mint"][data-profile-mode="dark"]{--cp-page:linear-gradient(145deg,#11251f,#192336);--cp-cover:linear-gradient(135deg,#2c5b50,#3a5271)}
html[data-profile-theme="midnight"][data-profile-mode="dark"]{--cp-page:linear-gradient(145deg,#151827,#241c30);--cp-cover:linear-gradient(135deg,#353b66,#574161)}
html[data-profile-mode="dark"] body{background:var(--cp-page);color:#e5e9f2;background-attachment:fixed}
html[data-profile-mode="dark"] [data-cp-cover]{background-blend-mode:multiply;background-color:#3c4059}
html[data-profile-mode="dark"] .cp-avatar{border-color:#1b1f29}
html[data-profile-layout="centered"] .cp-profile-hero{border-radius:30px}
html[data-profile-layout="centered"] .cp-hero-top{flex-direction:column;align-items:center;gap:16px}
html[data-profile-layout="centered"] .cp-hero-top>div{justify-content:center}
html[data-profile-layout="centered"] .cp-hero-identity{text-align:center}
html[data-profile-layout="centered"] .cp-hero-identity :is(h1,.flex){justify-content:center}
html[data-profile-layout="centered"] .cp-card{border-radius:26px}
html[data-profile-layout="editorial"] .cp-card{border-radius:6px;box-shadow:none;border-bottom-width:2px}
html[data-profile-layout="editorial"] .cp-hero-top{flex-direction:row-reverse}
html[data-profile-layout="editorial"] .cp-hero-identity{text-align:right}
html[data-profile-layout="editorial"] .cp-hero-identity :is(h1,.flex){justify-content:flex-end}
html[data-profile-layout="editorial"] .cp-hero-identity h1{font-family:Georgia,serif;font-weight:600}
html[data-profile-layout="minimal"] .cp-profile-hero{position:relative;padding-top:32px;border-top:4px solid #8b8bd1}
html[data-profile-layout="minimal"] [data-cp-cover]{position:absolute;inset:0;height:100%!important;opacity:.12;pointer-events:none}
html[data-profile-layout="minimal"] .cp-hero-content{margin-top:0}
html[data-profile-layout="minimal"] .cp-card{border-radius:12px;box-shadow:none}
html[data-profile-layout="minimal"] .cp-hero-top{align-items:center}
html[data-profile-layout="minimal"] .cp-hero-content{display:grid;grid-template-columns:auto minmax(0,1fr);gap:18px}
html[data-profile-layout="minimal"] .cp-hero-top{display:contents}
html[data-profile-layout="minimal"] .cp-hero-top>div:first-child{grid-column:1;grid-row:1;align-self:center}
html[data-profile-layout="minimal"] .cp-hero-top>div:last-child{grid-column:1/-1;grid-row:2;justify-content:flex-start}
html[data-profile-layout="minimal"] .cp-hero-identity{grid-column:2;grid-row:1;margin-top:0;align-self:center}
@media(max-width:420px){.cp-hero-top{gap:12px}.cp-hero-top>div:last-child{gap:6px}.cp-profile-hero .cp-hero-identity h1{font-size:1.6rem}}
</style>
