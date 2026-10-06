@once
<style>
[data-screen-panel]:fullscreen,[data-screen-panel].menu-screen-expanded{background:var(--bg-card,#fff);color:var(--text-primary,#171721);padding:24px;overflow:auto;width:100%;max-width:none;height:100%;box-sizing:border-box}
[data-screen-panel].menu-screen-expanded{position:fixed;inset:0;z-index:10000;box-sizing:border-box}
</style>
<script>
window.menuFullscreen = async function (button) {
    var panel = button.closest('[data-screen-panel]');
    if (!panel) return;
    if (document.fullscreenElement) { await document.exitFullscreen(); return; }
    if (panel.classList.contains('menu-screen-expanded')) { panel.classList.remove('menu-screen-expanded'); return; }
    try { if (panel.requestFullscreen) { await panel.requestFullscreen(); return; } } catch (error) {}
    panel.classList.add('menu-screen-expanded');
};
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') document.querySelectorAll('.menu-screen-expanded').forEach(function (panel) { panel.classList.remove('menu-screen-expanded'); });
});
</script>
@endonce
