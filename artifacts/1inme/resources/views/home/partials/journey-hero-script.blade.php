<script>
/*
 * The hero scene's only script: it decides WHETHER the scene animates, not
 * how. Every movement is CSS keyframes, so with this file absent, blocked or
 * broken the scene still renders -- it just runs continuously.
 *
 * The scene is paused whenever it is off screen, the tab is hidden, the
 * viewer asked for reduced motion, or they pressed pause. A hero that keeps
 * animating in a background tab is a battery cost for nothing.
 *
 * There is deliberately no geometry here. The previous version of this panel
 * measured elements and drew SVG between them; the folder map did the same
 * and put every wire up to 39px off its card. The rails in this scene live in
 * a fixed 600x520 viewBox over a fixed-ratio stage, so they cannot drift.
 */
(()=>{const root=document.getElementById('hero'),board=document.getElementById('sayzio-journey');if(!root||!board)return;
const toggle=board.querySelector('[data-journey-toggle]'),reduce=window.matchMedia('(prefers-reduced-motion: reduce)');
let playing=!reduce.matches,visible=true;
function sync(){
  const run=playing&&visible&&!document.hidden;
  board.toggleAttribute('data-playing',run);
  if(!toggle)return;
  toggle.setAttribute('aria-label',playing?'Pause the animation':'Play the animation');
  toggle.innerHTML='<i class="fas '+(playing?'fa-pause':'fa-play')+'" aria-hidden="true"></i>';
}
toggle&&toggle.addEventListener('click',()=>{playing=!playing;sync()});
root.querySelector('[data-journey-replay]')?.addEventListener('click',()=>{
  playing=!reduce.matches;sync();
  if(window.trackMarketingEvent)window.trackMarketingEvent('landing_home_cta','hero_journey_demo');
});
document.addEventListener('visibilitychange',sync);
reduce.addEventListener('change',()=>{playing=!reduce.matches;sync()});
if('IntersectionObserver'in window)new IntersectionObserver(e=>{visible=e[0].isIntersecting;sync()},{threshold:.1}).observe(board);
sync();
})();
</script>
