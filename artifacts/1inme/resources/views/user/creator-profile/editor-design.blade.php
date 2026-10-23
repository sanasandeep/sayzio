<style>
.cp-editor-tabs{display:flex;gap:6px;flex-wrap:wrap;margin:0 0 20px;padding:6px;border:1px solid var(--border-soft);border-radius:16px;background:var(--bg-card)}
.cp-editor-tabs button{padding:10px 14px;border-radius:10px;font-size:13px;font-weight:650;color:var(--text-dimmed);min-height:42px}
.cp-editor-tabs button[aria-pressed=true]{background:var(--text-primary);color:var(--bg-card)}
.cp-editor-form{display:flex!important;flex-direction:column;gap:22px!important}
.cp-editor-form>fieldset,.cp-editor-panel{width:100%;min-width:0;border:1px solid var(--border-soft);border-radius:18px;padding:22px;background:var(--bg-card)}
.cp-editor-panel legend{padding:0 8px;font-size:16px;font-weight:750;color:var(--text-primary)}
.cp-editor-hint{font-size:13px;line-height:1.65;color:var(--text-dimmed);margin:0 0 18px}
.cp-choice-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.cp-choice{position:relative;cursor:pointer;min-width:0;display:block}
.cp-choice input{position:absolute;left:12px;top:12px;z-index:1;accent-color:#6467e8}
.cp-choice-body{display:flex;flex-direction:column;gap:6px;border:1px solid var(--border-soft);border-radius:14px;padding:14px;background:var(--bg-input);min-height:100%;color:var(--text-primary)}
.cp-choice input:checked+.cp-choice-body{border-color:#7777e9;box-shadow:0 0 0 2px #7777e933;background:color-mix(in srgb,#7777e9 6%,var(--bg-card))}
.cp-choice input:focus-visible+.cp-choice-body{outline:3px solid #7777e9;outline-offset:3px}
.cp-choice-body strong{font-size:13px}.cp-choice-body small{font-size:11px;line-height:1.5;color:var(--text-dimmed)}
.cp-gradient-swatch{height:52px;border-radius:9px;margin-bottom:4px}
.cp-mode-choices{display:grid;grid-template-columns:1fr 1fr;gap:12px}.cp-mode-choices .cp-choice-body{padding-left:36px}
.cp-layout-demo{position:relative;height:76px;border-radius:9px;background:linear-gradient(135deg,#cbd7fa,#ddd0ed);margin-bottom:4px;overflow:hidden;display:block}
.cp-layout-demo:after{content:'';position:absolute;top:32px;left:0;right:0;bottom:0;background:#fff8}
.cp-layout-demo i{position:absolute;left:16px;top:22px;width:24px;height:24px;background:#7c7ab0;border:3px solid #fff;border-radius:8px;z-index:1}
.cp-layout-demo b,.cp-layout-demo em{position:absolute;left:16px;right:28px;height:5px;top:54px;background:#8e92ae66;border-radius:3px;z-index:1}.cp-layout-demo em{top:64px;right:55px}
.cp-demo-centered i{left:calc(50% - 12px)}.cp-demo-centered b{left:28%;right:28%}.cp-demo-centered em{left:36%;right:36%}
.cp-demo-editorial i{left:auto;right:16px}.cp-demo-editorial{border-radius:2px}
.cp-demo-minimal:after{top:0}.cp-demo-minimal i{top:16px}.cp-demo-minimal b{left:50px;top:22px}.cp-demo-minimal em{left:50px;top:34px}
.cp-editor-share{border:1px solid var(--border-soft);border-radius:12px;padding:10px 14px;margin-bottom:18px;background:var(--bg-card);font-size:13px}.cp-editor-share summary{cursor:pointer;font-weight:650;min-height:24px}.cp-editor-share[open] summary{margin-bottom:12px}
@media(min-width:1500px){.cp-choice-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}
@media(max-width:480px){.cp-editor-form>fieldset,.cp-editor-panel{padding:16px}.cp-editor-tabs{gap:2px}.cp-editor-tabs button{padding:9px 10px;font-size:12px}.cp-choice-body{padding:10px}.cp-choice-body small{font-size:10px}}
</style>
