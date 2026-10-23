<style>
html[data-profile-theme="paper"]{--cp-cover:linear-gradient(135deg,#cbdafa,#e4d8f7)}
html[data-profile-theme="warm"]{--cp-cover:linear-gradient(135deg,#dfb78b,#ebd7c5)}
html[data-profile-theme="mint"]{--cp-cover:linear-gradient(135deg,#a3cdb9,#d6e6c8)}
html[data-profile-theme="midnight"]{--cp-cover:linear-gradient(135deg,#252b42,#39304d)}
.cp-avatar{width:{{ $__presentation['avatar_size'] }}px;height:{{ $__presentation['avatar_size'] }}px;border-radius:{{ $__presentation['avatar_shape'] === 'circle' ? '50%' : ($__presentation['avatar_shape'] === 'square' ? '4px' : '20px') }};object-position:{{ $__presentation['avatar_x'] }}% {{ $__presentation['avatar_y'] }}%;flex-shrink:0}
html[data-profile-theme="warm"] body{background:#f5f0e8}html[data-profile-theme="warm"] .cp-card{background:#fffcf7;border-color:#e0d6c8}
html[data-profile-theme="mint"] body{background:#eef6f1}html[data-profile-theme="mint"] .cp-card{background:#fcfffd;border-color:#ccdfd2}
html[data-profile-theme="midnight"]{color-scheme:dark}
html[data-profile-theme="midnight"] body{background:#101218;color:#e5e9f2}
html[data-profile-theme="midnight"] :is(.cp-card,.cp-tab-bar){background:#1b1f29;color:#e5e9f2;border-color:#353c4d}
html[data-profile-theme="midnight"] :is(.text-slate-500,.text-slate-600,.text-slate-700,.text-slate-800,.text-slate-900){color:#bac3d3}
html[data-profile-theme="midnight"] .max-w-3xl :is(.bg-white,.bg-slate-50,.bg-blue-50){background:#242a37;border-color:#424d62;color:#d7e0f1}
html[data-profile-theme="midnight"] .cp-share-button{border-color:#424d62}
html[data-profile-theme] .cp-card{box-shadow:0 6px 24px rgba(15,23,42,.035)}
</style>
