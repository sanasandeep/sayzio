<style>
@keyframes stats-art-float {0%,100%{transform:translateY(0) rotate(-1deg)}50%{transform:translateY(-5px) rotate(2deg)}}
.ov-count-art {animation:stats-art-float 5s ease-in-out infinite;transform-origin:center;pointer-events:none;}
:is(.ov-count,.kpi-hero,.folder-kpi):nth-child(2n) .ov-count-art {animation-delay:-1.5s;}
:is(.ov-count,.kpi-hero,.folder-kpi):nth-child(3n) .ov-count-art {animation-delay:-3s;}
@media(prefers-reduced-motion:reduce){.ov-count-art{animation:none!important;}}
</style>
