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
[data-text-design="bracket"] { position:relative;padding:8px 16px!important;border-left:2px solid currentColor;border-right:2px solid currentColor; }
[data-text-design="bracket"]::before,[data-text-design="bracket"]::after { content:"";position:absolute;left:0;right:0;height:5px;background:linear-gradient(currentColor,currentColor) left/8px 100% no-repeat,linear-gradient(currentColor,currentColor) right/8px 100% no-repeat; }
[data-text-design="bracket"]::before {top:0;} [data-text-design="bracket"]::after {bottom:0;}
[data-text-design="ruled"] {text-align:left;line-height:1.8!important;background:repeating-linear-gradient(transparent 0,transparent calc(1.8em - 1px),currentColor 1.8em);font-family:Georgia,serif;}
[data-text-design="ruled"] p {line-height:1.8!important;margin:0!important;}
[data-text-design="vertical"] {border-left:4px solid currentColor;padding-left:12px!important;text-align:left;letter-spacing:.08em;font-variant:small-caps;}
[data-text-design="letterpress"] {font-family:Georgia,serif;font-weight:700;letter-spacing:.03em;text-shadow:1px 1px 0 rgba(128,128,128,.35),2px 2px 0 rgba(128,128,128,.15);}
[data-text-design="double_rule"] {border-top:3px double currentColor;border-bottom:3px double currentColor;padding:10px 0!important;font-family:Georgia,serif;text-align:center;}
[data-text-design] .sz-text-word-wrap {display:inline-block;white-space:nowrap;}
[data-text-design="word_wave"] .sz-text-piece {animation:sz-text-wave 3s ease-in-out infinite;animation-delay:calc(var(--piece)*130ms);}
[data-text-design="spring"] .sz-text-piece {animation:sz-text-spring 4s cubic-bezier(.34,1.56,.64,1) infinite;animation-delay:calc(var(--piece)*40ms);}
[data-text-design="blur_reveal"] .sz-text-piece {animation:sz-text-focus 4.5s ease infinite;animation-delay:calc(var(--piece)*110ms);}
[data-text-design="word_flip"] .sz-text-piece {animation:sz-text-flip 4s ease infinite;animation-delay:calc(var(--piece)*120ms);transform-origin:center bottom;}
@keyframes sz-text-wave {0%,55%,100% {transform:translateY(0);} 70% {transform:translateY(-5px);} 85% {transform:translateY(1px);}}
@keyframes sz-text-spring {0%,60%,100% {transform:scale(1);} 70% {transform:scale(.88,1.12);} 80% {transform:scale(1.06,.94);} 90% {transform:scale(1);}}
@keyframes sz-text-focus {0% {filter:blur(3px);opacity:.4;} 25%,85%,100% {filter:blur(0);opacity:1;}}
@keyframes sz-text-flip {0% {transform:perspective(350px) rotateX(60deg);opacity:.4;} 25%,90%,100% {transform:perspective(350px) rotateX(0);opacity:1;}}
[data-text-design] .sz-text-piece { display:inline-block;white-space:pre-wrap; }
[data-text-design="wobble"] .sz-text-piece { animation:sz-text-wobble 3.5s ease-in-out infinite;animation-delay:calc(var(--piece)*45ms); }
[data-text-design="split_words"] .sz-text-piece { animation:sz-text-word 3.8s ease both infinite;animation-delay:calc(var(--piece)*90ms); }
[data-text-design="split_chars"] .sz-text-piece { animation:sz-text-char 4.5s ease both infinite;animation-delay:calc(var(--piece)*35ms); }
@keyframes sz-text-wobble { 0%,65%,100% {transform:translateY(0) rotate(0);} 75% {transform:translateY(-3px) rotate(-4deg);} 85% {transform:translateY(1px) rotate(3deg);} }
@keyframes sz-text-word { 0% {opacity:.3;transform:translateY(8px);} 18%,88%,100% {opacity:1;transform:translateY(0);} }
@keyframes sz-text-char { 0% {opacity:.3;transform:rotateX(65deg) translateY(6px);} 20%,90%,100% {opacity:1;transform:none;} }
@media(prefers-reduced-motion:reduce) { [data-text-design] .sz-text-piece {animation:none!important;transform:none!important;opacity:1!important;filter:none!important;} }
</style>
<script>
(function () {
    if (window.sayzioTextDesigns) return;
    window.sayzioTextDesigns = true;
    function prepare(root) {
        root.querySelectorAll('[data-text-design="wobble"], [data-text-design="split_words"], [data-text-design="split_chars"], [data-text-design="word_wave"], [data-text-design="spring"], [data-text-design="blur_reveal"], [data-text-design="word_flip"]').forEach(function (element) {
            if (element.dataset.textPrepared) return;
            element.dataset.textPrepared = '1';
            var walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
            var nodes = [], node, index = 0;
            while ((node = walker.nextNode())) {
                if (node.parentElement.closest('script,style,a,button,textarea')) continue;
                nodes.push(node);
            }
            nodes.forEach(function (node) {
                var byWord = ['split_words','word_wave','blur_reveal','word_flip'].indexOf(element.dataset.textDesign) !== -1;
                var fragment = document.createDocumentFragment();
                node.textContent.split(/(\s+)/).forEach(function (word) {
                    if (/^\s+$/.test(word) || !word) { fragment.appendChild(document.createTextNode(word)); return; }
                    var wordWrap = document.createElement('span');
                    wordWrap.className = 'sz-text-word-wrap';
                    (byWord ? [word] : Array.from(word)).forEach(function (part) {
                        if (index >= 240) { wordWrap.appendChild(document.createTextNode(part)); return; }
                        var span = document.createElement('span');
                        span.className = 'sz-text-piece';
                        span.style.setProperty('--piece', index++ % 24);
                        span.textContent = part;
                        wordWrap.appendChild(span);
                    });
                    fragment.appendChild(wordWrap);
                });
                node.replaceWith(fragment);
            });
        });
    }
    prepare(document);
    new MutationObserver(function () { prepare(document); }).observe(document.documentElement, {childList:true,subtree:true});
})();
</script>
