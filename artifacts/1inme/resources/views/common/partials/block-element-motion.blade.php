<style>
@keyframes sz-element-float { 0%,100% { translate:0 0; } 50% { translate:0 -3px; } }
@keyframes sz-element-breathe { 0%,100% { box-shadow:0 0 0 0 rgba(106,168,139,.05); } 50% { box-shadow:0 0 0 4px rgba(106,168,139,.18); } }
@keyframes sz-element-reveal { from { opacity:.4; translate:0 6px; } to { opacity:1; translate:0 0; } }
@media (prefers-reduced-motion:reduce) {
    [style*="sz-element-"] { animation:none !important; translate:none !important; }
}
</style>

<style>
[data-text-design] { overflow-wrap:anywhere; }
[data-text-design="editorial"] { font-family:Georgia,serif; text-align:left; }
[data-text-design="editorial"] p:first-child::first-letter { float:left;font-size:3.2em;line-height:.85;margin:.08em .13em 0 0; }
[data-text-design="quote"] { border-left:3px solid currentColor;padding-left:16px!important;font-family:Georgia,serif;font-style:italic;text-align:left; }
[data-text-design="marker"] { text-decoration:underline;text-decoration-color:#e8c86b;text-decoration-thickness:.35em;text-underline-offset:-.12em;text-decoration-skip-ink:none; }
[data-text-design="outline"] { font-family:Arial,sans-serif;font-weight:800;-webkit-text-stroke:1px currentColor;-webkit-text-fill-color:transparent;letter-spacing:.045em; }
[data-text-design] .sz-text-piece { display:inline-block;white-space:pre-wrap; }
[data-text-design="wobble"] .sz-text-piece { animation:sz-text-wobble 3.5s ease-in-out infinite;animation-delay:calc(var(--piece)*45ms); }
[data-text-design="split_words"] .sz-text-piece { animation:sz-text-word 3.8s ease both infinite;animation-delay:calc(var(--piece)*90ms); }
[data-text-design="split_chars"] .sz-text-piece { animation:sz-text-char 4.5s ease both infinite;animation-delay:calc(var(--piece)*35ms); }
@keyframes sz-text-wobble { 0%,65%,100% {transform:translateY(0) rotate(0);} 75% {transform:translateY(-3px) rotate(-4deg);} 85% {transform:translateY(1px) rotate(3deg);} }
@keyframes sz-text-word { 0% {opacity:.3;transform:translateY(8px);} 18%,88%,100% {opacity:1;transform:translateY(0);} }
@keyframes sz-text-char { 0% {opacity:.3;transform:rotateX(65deg) translateY(6px);} 20%,90%,100% {opacity:1;transform:none;} }
@media(prefers-reduced-motion:reduce) { [data-text-design] .sz-text-piece {animation:none!important;transform:none!important;opacity:1!important;} }
</style>
<script>
(function () {
    if (window.sayzioTextDesigns) return;
    window.sayzioTextDesigns = true;
    function prepare(root) {
        root.querySelectorAll('[data-text-design="wobble"], [data-text-design="split_words"], [data-text-design="split_chars"]').forEach(function (element) {
            if (element.dataset.textPrepared) return;
            element.dataset.textPrepared = '1';
            var walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
            var nodes = [], node, index = 0;
            while ((node = walker.nextNode())) {
                if (node.parentElement.closest('script,style,a,button,textarea')) continue;
                nodes.push(node);
            }
            nodes.forEach(function (node) {
                var parts = element.dataset.textDesign === 'split_words' ? node.textContent.split(/(\s+)/) : Array.from(node.textContent);
                var fragment = document.createDocumentFragment();
                parts.forEach(function (part) {
                    if (/^\s+$/.test(part) || index >= 240) { fragment.appendChild(document.createTextNode(part)); return; }
                    var span = document.createElement('span');
                    span.className = 'sz-text-piece';
                    span.style.setProperty('--piece', index++ % 24);
                    span.textContent = part;
                    fragment.appendChild(span);
                });
                node.replaceWith(fragment);
            });
        });
    }
    prepare(document);
    new MutationObserver(function () { prepare(document); }).observe(document.documentElement, {childList:true,subtree:true});
})();
</script>
