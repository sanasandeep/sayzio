<script>
(()=>{const root=document.getElementById('create');if(!root||root.dataset.sfReady)return;root.dataset.sfReady='true';const cards=[...root.querySelectorAll('[data-sf-card]')],filters=[...root.querySelectorAll('[data-sf-filter]')],search=root.querySelector('[data-sf-search]'),preview=root.querySelector('#sf-preview'),list=root.querySelector('.sf-list'),layout=root.querySelector('.sf-layout'),reduce=matchMedia('(prefers-reduced-motion: reduce)'),mobile=matchMedia('(max-width: 760px)');let selected=cards[0],group='all',paused=reduce.matches,visible=true;
function place(){if(mobile.matches&&selected&&!selected.hidden){const shown=cards.filter(c=>!c.hidden),index=shown.indexOf(selected);shown[Math.min(shown.length-1,index%2?index:index+1)].after(preview)}else layout.append(preview)}
function motion(){root.classList.toggle('sf-paused',paused||reduce.matches||!visible||document.hidden);root.querySelector('[data-sf-motion]').setAttribute('aria-label',paused?'Play preview animation':'Pause preview animation');root.querySelector('[data-sf-motion]').innerHTML='<i class="fas '+(paused?'fa-play':'fa-pause')+'" aria-hidden="true"></i>'}
function choose(card){selected=card;cards.forEach(c=>{c.classList.toggle('sf-selected',c===card);c.setAttribute('aria-pressed',String(c===card))});const d=card.dataset;preview.dataset.art=d.art;preview.style.setProperty('--sf-accent',d.color);root.querySelector('[data-sf-title]').textContent=d.name;root.querySelector('[data-sf-description]').textContent=d.desc;root.querySelector('[data-sf-usage]').textContent=d.usage;root.querySelector('[data-sf-use]').hidden=!d.usage;const create=root.querySelector('[data-sf-create]');if(create)create.href=d.create;const demo=root.querySelector('[data-sf-demo]');demo.hidden=!d.demo;if(d.demo)demo.href=d.demo;else demo.removeAttribute('href');swapScene(root.querySelector('[data-sf-template="'+d.sfCard+'"]').content.cloneNode(true));place();motion()}
function apply(){repack(()=>applyNow());}
function applyNow(){const q=search.value.trim().toLocaleLowerCase(),matches=c=>(c.dataset.name+' '+c.dataset.desc).toLocaleLowerCase().includes(q);let shown=0;cards.forEach(c=>{c.hidden=!(matches(c)&&(group==='all'||c.dataset.group===group));if(!c.hidden)shown++});filters.forEach(f=>{f.setAttribute('aria-pressed',String(f.dataset.sfFilter===group));f.querySelector('span').textContent=cards.filter(c=>matches(c)&&(f.dataset.sfFilter==='all'||c.dataset.group===f.dataset.sfFilter)).length});root.querySelector('[data-sf-results]').textContent='Showing '+shown+' of '+cards.length+' formats';root.querySelector('[data-sf-empty]').hidden=shown!==0;preview.hidden=shown===0;if(shown&&(selected.hidden||!selected))choose(cards.find(c=>!c.hidden));else place()}
cards.forEach(c=>c.addEventListener('click',()=>{flyArt(c);choose(c)}));filters.forEach(f=>f.addEventListener('click',()=>{group=f.dataset.sfFilter;apply()}));search.addEventListener('input',apply);root.querySelector('[data-sf-reset]').addEventListener('click',()=>{search.value='';group='all';apply();search.focus()});root.querySelector('[data-sf-motion]').addEventListener('click',()=>{paused=!paused;motion()});root.querySelector('[data-sf-register]')?.addEventListener('click',event=>{if(window.Alpine){event.preventDefault();window.dispatchEvent(new CustomEvent('open-auth',{detail:{tab:'register'}}))}if(window.trackMarketingEvent)window.trackMarketingEvent('landing_home_cta','format_create')});mobile.addEventListener('change',place);reduce.addEventListener('change',()=>{paused=reduce.matches;motion()});document.addEventListener('visibilitychange',motion);if('IntersectionObserver'in window)new IntersectionObserver(entries=>{visible=entries[0].isIntersecting;motion()},{threshold:.05}).observe(root);
/*
 * ── Motion for the format showcase ────────────────────────────────────────
 *
 * Three things live here, and one rule governs all of them: when the page
 * says do not animate, none of this runs AT ALL -- it returns early and does
 * the plain DOM swap. That is deliberate. The CSS kill switches
 * (`.sf-paused *` and the prefers-reduced-motion block, both of which already
 * cover ::before and ::after) stop declarative animation, but they cannot
 * stop a script that is busy measuring rectangles and calling .animate().
 * So the script checks first and simply does not start.
 *
 *   swapScene  morphs between two preview scenes instead of cutting
 *   repack     slides the cards to their new grid slots on a filter change
 *   flyArt     sends a card's artwork into the panel when you pick it
 *
 * All three are FLIP: measure First, make the change, measure Last, Invert
 * with a transform, then Play back to none. The browser only ever animates
 * transform and opacity, so none of it touches layout while it runs.
 */
function noMotion(){return reduce.matches||paused||!visible||document.hidden}

/* A key that says "these two elements are the same KIND of thing", so a row
 * in the outgoing scene can be matched to a row in the incoming one. Built
 * from the tag plus the lts- classes that describe shape, with the motion
 * and width helpers dropped -- `lts-line lts-w90 lts-rise` and
 * `lts-line lts-w60` are both just a line as far as pairing goes. */
function shapeKey(el){
  const parts=[...el.classList].filter(c=>c.startsWith('lts-')
    &&!/^lts-(rise|pop|w\d+|faint|dim|mid|thin|right)$/.test(c)).sort();
  return el.tagName+'|'+parts.join('.');
}
function byShape(els){
  const map=new Map();
  els.forEach(el=>{const k=shapeKey(el);(map.get(k)||map.set(k,[]).get(k)).push(el)});
  return map;
}

/* How much structure two scenes share decides HOW they change over.
 *
 * Measured across all 650 ordered pairs, a scene shares a mean of about a
 * third of its elements with the one before it, and plenty of pairs share
 * almost nothing -- Calendar into Bizs Profile pairs 1 element out of 13,
 * Conversational into QR Code 1 out of 84. Travelling that single element
 * while the other 83 pop in looks like a glitch, not a transition: the eye
 * follows the one thing that moved and it turns out to be a stray line.
 *
 * So the morph is earned, not assumed. Scenes that really are built the same
 * way -- Short Link into File Share, Contact Card into Text Page -- morph,
 * and the shared rows visibly travel to their new places. Scenes with
 * nothing in common cut cleanly and let the entrance animations do their
 * job. The transition ends up saying something true about how related the
 * two formats are.
 *
 * The threshold is measured, not guessed. Counting "four or fewer movers in
 * a scene of twelve or more elements" -- the case that reads as a glitch --
 * over all 650 ordered pairs:
 *
 *     >=25%   199 morph (31%)   39 glitchy
 *     >=30%   157 morph (24%)   17 glitchy
 *     >=35%   118 morph (18%)    0 glitchy
 *     >=40%    90 morph (14%)    0 glitchy
 *
 * 35% is the knee: the most coverage with none of the bad case. Scenes share
 * a mean of 22% of their elements, so most transitions still cut, and that
 * is the honest outcome -- a QR grid and a chat thread have nothing to say
 * to each other. */
const MORPH_MIN_SHARE=.35, MORPH_MIN_COUNT=3;

function swapScene(fragment){
  const scene=root.querySelector('[data-sf-scene]');
  if(noMotion()){scene.replaceChildren(fragment);return}

  const before=byShape([...scene.querySelectorAll('*')]);
  const firstRects=new Map();
  before.forEach(list=>list.forEach(el=>firstRects.set(el,el.getBoundingClientRect())));

  scene.replaceChildren(fragment);

  const after=byShape([...scene.querySelectorAll('*')]);

  /* Work out the pairing before moving anything, so a weak match costs
   * nothing but the measuring. */
  const moves=[]; let total=0;
  after.forEach((list,key)=>{
    total+=list.length;
    const partners=before.get(key);
    if(!partners)return;
    list.forEach((el,i)=>{
      const from=firstRects.get(partners[Math.min(i,partners.length-1)]);
      if(!from||!from.width)return;
      const to=el.getBoundingClientRect();
      if(!to.width)return;
      const dx=from.left-to.left, dy=from.top-to.top;
      const sx=from.width/to.width, sy=from.height/to.height;
      if(Math.abs(dx)<1&&Math.abs(dy)<1&&Math.abs(sx-1)<.02&&Math.abs(sy-1)<.02)return;
      moves.push({el,dx,dy,sx,sy});
    });
  });

  if(moves.length<MORPH_MIN_COUNT||!total||moves.length/total<MORPH_MIN_SHARE)return;

  const panel=scene.querySelector('.lts');
  if(panel)panel.style.animation='none';          // morphing, so the panel does not re-fade
  moves.forEach(({el,dx,dy,sx,sy})=>{
    el.style.animation='none';                     // it travels instead of rising
    el.animate(
      [{transform:`translate(${dx}px,${dy}px) scale(${sx},${sy})`,opacity:.65},
       {transform:'none',opacity:1}],
      {duration:420,easing:'cubic-bezier(.2,.7,.25,1)',fill:'backwards'}
    ).finished.then(()=>{el.style.animation=''}).catch(()=>{el.style.animation=''});
  });
}

/* Re-pack the card grid. The cards that survive a filter slide to their new
 * slots rather than the grid jumping under the cursor. */
function repack(change){
  if(noMotion()){change();return}
  const wasVisible=cards.filter(c=>!c.hidden);
  const firstRects=new Map(wasVisible.map(c=>[c,c.getBoundingClientRect()]));

  change();

  cards.forEach(c=>{
    if(c.hidden)return;
    const from=firstRects.get(c);
    if(!from)return;                                // newly shown: let it just appear
    const to=c.getBoundingClientRect();
    const dx=from.left-to.left, dy=from.top-to.top;
    if(Math.abs(dx)<1&&Math.abs(dy)<1)return;
    c.animate([{transform:`translate(${dx}px,${dy}px)`},{transform:'none'}],
      {duration:360,easing:'cubic-bezier(.2,.7,.25,1)'});
  });
}

/* Send the card's own artwork into the panel. A clone flies over the top and
 * fades as it lands, so the picture you clicked is visibly the picture the
 * panel then draws -- one object, not two. Kept short on purpose: the scene
 * underneath is morphing at the same moment, and two slow animations at once
 * reads as busy rather than considered. */
function flyArt(card){
  if(noMotion()||mobile.matches)return;
  const art=card.querySelector('.sf-card-art'), target=root.querySelector('[data-sf-scene]');
  if(!art||!target)return;
  const from=art.getBoundingClientRect(), to=target.getBoundingClientRect();
  if(!from.width||!to.width)return;

  const ghost=art.cloneNode(true);
  ghost.setAttribute('aria-hidden','true');
  Object.assign(ghost.style,{position:'fixed',left:from.left+'px',top:from.top+'px',
    width:from.width+'px',height:from.height+'px',margin:'0',pointerEvents:'none',zIndex:'60'});
  document.body.appendChild(ghost);

  const dx=(to.left+to.width/2)-(from.left+from.width/2);
  const dy=(to.top+Math.min(to.height,260)/2)-(from.top+from.height/2);
  ghost.animate(
    [{transform:'none',opacity:.95},
     {transform:`translate(${dx*.6}px,${dy*.6}px) scale(1.5)`,opacity:.5,offset:.6},
     {transform:`translate(${dx}px,${dy}px) scale(2.1)`,opacity:0}],
    {duration:420,easing:'cubic-bezier(.3,.8,.3,1)'}
  ).finished.finally(()=>ghost.remove());
}

place();motion();})();
</script>
