<script>
(()=>{const root=document.getElementById('hero'),board=document.getElementById('sayzio-journey');if(!root||!board)return;
const buttons=[...board.querySelectorAll('[data-journey-step]')],descriptions=[...board.querySelectorAll('[data-journey-description]')],toggle=board.querySelector('[data-journey-toggle]'),reduce=window.matchMedia('(prefers-reduced-motion: reduce)');let index=0,playing=!reduce.matches,timer=null,visible=true;
function show(next){index=next;buttons.forEach((b,i)=>{b.setAttribute('aria-pressed',String(i===index));b.closest('li').classList.toggle('is-active',i===index)});descriptions.forEach((p,i)=>p.hidden=i!==index)}
function stop(){if(timer)clearInterval(timer);timer=null}
function sync(){stop();board.toggleAttribute('data-playing',playing&&visible&&!document.hidden);toggle.setAttribute('aria-label',playing?'Pause journey animation':'Play journey animation');toggle.innerHTML='<i class="fas '+(playing?'fa-pause':'fa-play')+'" aria-hidden="true"></i>';if(playing&&visible&&!document.hidden)timer=setInterval(()=>show((index+1)%buttons.length),3200)}
buttons.forEach((b,i)=>b.addEventListener('click',()=>{playing=false;show(i);sync()}));toggle.addEventListener('click',()=>{playing=!playing;sync()});root.querySelector('[data-journey-replay]')?.addEventListener('click',()=>{show(0);playing=!reduce.matches;sync();if(window.trackMarketingEvent)window.trackMarketingEvent('landing_home_cta','hero_journey_demo')});document.addEventListener('visibilitychange',sync);reduce.addEventListener('change',()=>{playing=!reduce.matches;sync()});if('IntersectionObserver'in window)new IntersectionObserver(entries=>{visible=entries[0].isIntersecting;sync()},{threshold:.15}).observe(board);sync();
})();
</script>
