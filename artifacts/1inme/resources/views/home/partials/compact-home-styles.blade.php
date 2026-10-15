<style>
.ch-trust-dot{display:inline-block;width:6px;height:6px;border-radius:50%;background:#18bd88;margin-right:7px;vertical-align:middle}

.ch-section,.ch-trust{--ch-text:#edf0ff;--ch-muted:#abb5cf;--ch-panel:#141b30;--ch-soft:#1c2540;--ch-border:#ffffff19;--ch-accent:#aaa3ff;color:var(--ch-text);box-sizing:border-box}html.light-mode .ch-section,html.light-mode .ch-trust{--ch-text:#141c31;--ch-muted:#63708b;--ch-panel:#fff;--ch-soft:#f5f6fc;--ch-border:#18284816;--ch-accent:#5845d8}
.ch-section{max-width:1280px;margin:0 auto;padding:64px 40px}
.ch-trust{max-width:1200px;margin:0 auto;padding:24px 0;display:flex;justify-content:center;gap:32px;flex-wrap:wrap;border-bottom:1px solid var(--ch-border);font-size:13px;color:var(--ch-muted)}
.ch-trust b{color:var(--ch-text)}
.ch-heading{max-width:730px;margin:0 auto 30px;text-align:center}
.ch-eyebrow{font-size:11px;font-weight:750;letter-spacing:.13em;color:var(--ch-accent)}
.ch-section h2{font-size:clamp(28px,3.2vw,42px);line-height:1.14;letter-spacing:-.035em;font-weight:750;margin:12px 0 16px;color:var(--ch-text)}
.ch-section p{font-size:15px;line-height:1.7;color:var(--ch-muted);margin:0}
.ch-demo{border:1px solid var(--ch-border);border-radius:24px;overflow:hidden;background:var(--ch-panel);box-shadow:0 20px 60px #20185409}
.ch-tabs{display:flex;gap:8px;justify-content:center;padding:16px;border-bottom:1px solid var(--ch-border);background:var(--ch-soft)}
.ch-tabs button{display:flex;align-items:center;justify-content:center;gap:9px;background:transparent;border:1px solid transparent;border-radius:12px;padding:11px 20px;color:var(--ch-muted);font-size:14px;font-weight:650;cursor:pointer;transition:background .2s,color .2s,box-shadow .2s}
.ch-tabs button[aria-selected=true]{background:var(--ch-panel);color:var(--ch-accent);border-color:var(--ch-border);box-shadow:0 3px 12px #2720550b}
.ch-tabs button:focus-visible,.ch-section a:focus-visible,.ch-section summary:focus-visible{outline:3px solid var(--ch-accent);outline-offset:3px}
.ch-panel{display:grid;grid-template-columns:1fr 1.1fr;align-items:center;gap:48px;padding:38px 44px;}
.ch-panel[hidden]{display:none!important}
.ch-panel-copy h3{font-size:26px;font-weight:700;letter-spacing:-.025em;line-height:1.2;margin:18px 0 14px;color:var(--ch-text)}
.ch-chip{display:inline-block;color:var(--ch-accent);font-size:12px;font-weight:650}
.ch-panel-copy a,.ch-link{display:inline-flex;gap:12px;align-items:center;color:var(--ch-accent);font-weight:650;font-size:14px;margin-top:22px;text-decoration:none}
.ch-product{background:var(--ch-soft);border:1px solid var(--ch-border);border-radius:17px;padding:18px;box-shadow:0 16px 32px #20185408}
.ch-product-top{display:flex;justify-content:space-between;gap:12px;align-items:center;font-size:12px;font-weight:650;margin-bottom:18px}
.ch-product-top i{color:var(--ch-accent);margin-right:5px}
.ch-product-top small,.ch-muted{color:var(--ch-muted);font-size:11px;line-height:1.6}
.ch-record{background:var(--ch-panel);border:1px solid var(--ch-border);border-radius:12px;padding:20px}
.ch-status{font-size:11px;display:inline-block;color:var(--ch-accent);font-weight:650;border-radius:20px;padding:4px 9px;background:var(--ch-soft)}
.ch-person{display:flex;gap:12px;align-items:center;margin:18px 0}
.ch-avatar{flex-shrink:0;width:42px;height:42px;display:grid;place-items:center;border-radius:14px;background:linear-gradient(145deg,#6e65ed,#ac5dc8);color:#fff;font-weight:750;font-size:13px}
.ch-record h4{font-size:16px;font-weight:700;margin:0 0 4px;color:var(--ch-text)}
.ch-record p{font-size:12px}
.ch-record-line{font-size:12px;color:var(--ch-muted);display:flex;align-items:center;gap:8px}
.ch-record-line i{color:#19aa86}
.ch-next{border-top:1px solid var(--ch-border);margin-top:17px;padding-top:14px;display:grid;gap:5px}
.ch-next small{font-size:9px;letter-spacing:.1em;color:var(--ch-muted)}
.ch-next strong{font-size:12px;color:var(--ch-text)}
.ch-path{display:flex;justify-content:center;align-items:center;gap:10px;margin-top:16px;font-size:10px;color:var(--ch-muted)}
.ch-path b{color:var(--ch-accent)}
.ch-demo .ch-caption{font-size:11px;padding:0 24px 20px;text-align:center}
.ch-ai-scene{background:radial-gradient(ellipse at 50% 30%,#8668ef16,transparent 75%);padding:14px 0}
.ch-context{font-size:11px;color:var(--ch-muted);text-align:center;margin-bottom:20px}
.ch-context i{color:var(--ch-accent);margin-right:6px}
.ch-prompt{padding:14px 18px;margin-left:30px;background:var(--ch-soft);border:1px solid var(--ch-border);border-radius:15px 15px 3px 15px;font-size:13px}
.ch-answer{display:flex;gap:12px;padding:22px;margin:15px 20px 0 0;border:1px solid var(--ch-border);border-radius:15px;background:var(--ch-panel);box-shadow:0 12px 30px #2018540c}
.ch-spark{color:var(--ch-accent);font-size:24px}
.ch-answer strong{font-size:15px;color:var(--ch-text)}
.ch-answer p{font-size:12px;margin:8px 0 14px}
.ch-panel-copy .ch-muted{display:block;margin-top:20px}
.ch-pricing,.ch-faq{display:grid;grid-template-columns:1fr 1.3fr;gap:60px;align-items:center}
.ch-prices{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px}
.ch-prices article{background:var(--ch-panel);padding:26px;border:1px solid var(--ch-border);border-radius:18px}
.ch-prices article>span{color:var(--ch-muted);font-size:13px}
.ch-prices h3{font-size:28px;margin:18px 0 12px;font-weight:750;letter-spacing:-.03em}
.ch-prices h3 small{font-size:12px;font-weight:400;letter-spacing:0;color:var(--ch-muted)}
.ch-prices p{font-size:12px}
.ch-faq{align-items:start;border-top:1px solid var(--ch-border)}
.ch-faq details{border-bottom:1px solid var(--ch-border);padding:18px 0}
.ch-faq details:first-child{padding-top:0}
.ch-faq summary{display:flex;justify-content:space-between;gap:14px;cursor:pointer;font-size:14px;font-weight:650;list-style:none}
.ch-faq summary::-webkit-details-marker{display:none}
.ch-faq summary span{color:var(--ch-accent)}
.ch-faq details[open] summary span{transform:rotate(45deg)}
.ch-faq details p{font-size:13px;padding:14px 20px 0 0}
.ch-final{text-align:center;padding-top:48px;padding-bottom:72px;background:radial-gradient(ellipse at 50% 70%,#8463ed10,transparent 65%)}
.ch-button{display:inline-flex;align-items:center;gap:20px;margin-top:24px;background:linear-gradient(110deg,#4655ee,#7845dd);color:white!important;border-radius:13px;padding:15px 24px;font-weight:700;font-size:14px;text-decoration:none;box-shadow:0 8px 24px #6550df23;transition:transform .2s,box-shadow .2s}
.ch-button:hover{transform:translateY(-2px);box-shadow:0 12px 26px #6550df35}
@media(max-width:760px){.ch-section{padding:44px 24px}
.ch-trust{margin:0 24px;gap:12px 20px;padding:20px 0;font-size:11px}
.ch-panel{grid-template-columns:1fr;gap:24px;padding:24px}
.ch-panel-copy h3{font-size:23px}
.ch-tabs{padding:10px;gap:3px}
.ch-tabs button{flex:1;gap:6px;padding:10px 5px;font-size:12px}
.ch-tabs i{display:none}
.ch-pricing,.ch-faq{grid-template-columns:1fr;gap:26px}
.ch-path{gap:7px}
.ch-product{padding:12px}
.ch-record{padding:16px}
.ch-product-top small{font-size:9px}
.ch-heading{margin-bottom:24px}
.ch-final{padding-bottom:48px}}
@media(prefers-reduced-motion:reduce){.ch-panel{animation:none}
.ch-tabs button,.ch-button{transition:none}
.ch-button:hover{transform:none}}
</style>
