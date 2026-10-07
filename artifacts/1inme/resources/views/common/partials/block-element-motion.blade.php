<style>
@keyframes sz-element-float { 0%,100% { translate:0 0; } 50% { translate:0 -3px; } }
@keyframes sz-element-breathe { 0%,100% { box-shadow:0 0 0 0 rgba(106,168,139,.05); } 50% { box-shadow:0 0 0 4px rgba(106,168,139,.18); } }
@keyframes sz-element-reveal { from { opacity:.4; translate:0 6px; } to { opacity:1; translate:0 0; } }
@media (prefers-reduced-motion:reduce) {
    [style*="sz-element-"] { animation:none !important; translate:none !important; }
}
</style>
