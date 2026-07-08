<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\BgTemplate;
use Illuminate\Database\Seeder;

class BgTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $i => $tpl) {
            BgTemplate::updateOrCreate(
                ['slug' => $tpl['slug']],
                array_merge($tpl, ['sort_order' => $i])
            );
        }
    }

    /** @return array<int, array<string,mixed>> */
    public function templates(): array
    {
        return [
            [
                'name' => 'Aurora Borealis',
                'slug' => 'aurora-borealis',
                'preview_color' => 'linear-gradient(135deg, #0a0612, #1a0533, #0d1b2a)',
                'category' => 'animated',
                'css' => '
.bg-template-aurora-borealis { position:fixed; inset:0; z-index:-1; overflow:hidden; }
.bg-template-aurora-borealis::before, .bg-template-aurora-borealis::after {
  content:""; position:absolute; width:200%; height:200%;
  top:-50%; left:-50%;
  animation: auroraRotate 20s linear infinite;
}
.bg-template-aurora-borealis::before {
  background: radial-gradient(ellipse at 20% 50%, rgba(139,92,246,0.25) 0%, transparent 50%),
              radial-gradient(ellipse at 80% 20%, rgba(59,130,246,0.2) 0%, transparent 50%),
              radial-gradient(ellipse at 40% 80%, rgba(168,85,247,0.2) 0%, transparent 50%);
}
.bg-template-aurora-borealis::after {
  background: radial-gradient(ellipse at 60% 40%, rgba(6,182,212,0.15) 0%, transparent 50%),
              radial-gradient(ellipse at 30% 70%, rgba(236,72,153,0.12) 0%, transparent 50%);
  animation-duration: 25s; animation-direction: reverse;
}
@keyframes auroraRotate { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }',
                'js' => null,
            ],
            [
                'name' => 'Floating Particles',
                'slug' => 'floating-particles',
                'preview_color' => 'linear-gradient(180deg, #0a0a1a, #1a1a3e)',
                'category' => 'animated',
                'css' => '
.bg-template-floating-particles { position:fixed; inset:0; z-index:-1; }
.bg-particle {
  position:absolute; width:3px; height:3px; border-radius:50%;
  background:rgba(139,92,246,0.6); animation:particleFloat linear infinite;
  box-shadow: 0 0 6px rgba(139,92,246,0.3);
}
@keyframes particleFloat {
  0% { transform:translateY(100vh) scale(0); opacity:0; }
  10% { opacity:1; }
  90% { opacity:1; }
  100% { transform:translateY(-10vh) scale(1); opacity:0; }
}',
                'js' => '
(function(){
  var c=document.querySelector(".bg-template-floating-particles");
  if(!c) return;
  for(var i=0;i<40;i++){
    var p=document.createElement("div");
    p.className="bg-particle";
    p.style.left=Math.random()*100+"%";
    p.style.animationDuration=(8+Math.random()*12)+"s";
    p.style.animationDelay=Math.random()*10+"s";
    p.style.width=p.style.height=(1+Math.random()*3)+"px";
    var hue=260+Math.random()*60;
    p.style.background="hsla("+hue+",70%,60%,0.5)";
    p.style.boxShadow="0 0 6px hsla("+hue+",70%,60%,0.3)";
    c.appendChild(p);
  }
})();',
            ],
            [
                'name' => 'Mesh Gradient',
                'slug' => 'mesh-gradient',
                'preview_color' => 'linear-gradient(135deg, #1a0533, #0d1b2a, #1a1a3e)',
                'category' => 'animated',
                'css' => '
.bg-template-mesh-gradient { position:fixed; inset:0; z-index:-1; }
.bg-template-mesh-gradient::before {
  content:""; position:absolute; inset:0;
  background:
    radial-gradient(at 0% 0%, rgba(139,92,246,0.3) 0px, transparent 50%),
    radial-gradient(at 100% 0%, rgba(59,130,246,0.2) 0px, transparent 50%),
    radial-gradient(at 100% 100%, rgba(236,72,153,0.25) 0px, transparent 50%),
    radial-gradient(at 0% 100%, rgba(6,182,212,0.2) 0px, transparent 50%);
  animation: meshMove 15s ease-in-out infinite alternate;
}
@keyframes meshMove {
  0% { filter:hue-rotate(0deg) blur(0px); }
  50% { filter:hue-rotate(30deg) blur(1px); }
  100% { filter:hue-rotate(-20deg) blur(0px); }
}',
                'js' => null,
            ],
            [
                'name' => 'Starfield',
                'slug' => 'starfield',
                'preview_color' => 'linear-gradient(180deg, #000011, #0a0a2e)',
                'category' => 'animated',
                'css' => '
.bg-template-starfield { position:fixed; inset:0; z-index:-1; overflow:hidden; }
.star-layer {
  position:absolute; inset:0;
  background-image: radial-gradient(1px 1px at 10% 20%, rgba(255,255,255,0.8) 50%, transparent),
    radial-gradient(1px 1px at 30% 60%, rgba(255,255,255,0.6) 50%, transparent),
    radial-gradient(1px 1px at 50% 10%, rgba(255,255,255,0.7) 50%, transparent),
    radial-gradient(1px 1px at 70% 80%, rgba(255,255,255,0.5) 50%, transparent),
    radial-gradient(1px 1px at 90% 40%, rgba(255,255,255,0.9) 50%, transparent);
  background-size: 200px 200px;
  animation: starTwinkle 4s ease-in-out infinite alternate;
}
.star-layer:nth-child(2) { background-size:300px 300px; animation-duration:6s; opacity:0.7; }
.star-layer:nth-child(3) { background-size:400px 400px; animation-duration:8s; opacity:0.5; animation-delay:1s; }
@keyframes starTwinkle { 0%{opacity:0.5} 100%{opacity:1} }',
                'js' => '
(function(){
  var c=document.querySelector(".bg-template-starfield");
  if(!c) return;
  for(var i=0;i<3;i++){
    var l=document.createElement("div");
    l.className="star-layer";
    c.appendChild(l);
  }
})();',
            ],
            [
                'name' => 'Neon Waves',
                'slug' => 'neon-waves',
                'preview_color' => 'linear-gradient(180deg, #0a0612, #0d0d2b)',
                'category' => 'animated',
                'css' => '
.bg-template-neon-waves { position:fixed; inset:0; z-index:-1; overflow:hidden; }
.neon-wave {
  position:absolute; bottom:-50%; left:-10%; width:120%; height:100%;
  border-radius:43%; opacity:0.15;
  animation: neonWaveFloat linear infinite;
}
.neon-wave:nth-child(1) { background:rgba(139,92,246,0.4); animation-duration:12s; }
.neon-wave:nth-child(2) { background:rgba(59,130,246,0.3); animation-duration:16s; animation-delay:-3s; bottom:-55%; }
.neon-wave:nth-child(3) { background:rgba(236,72,153,0.25); animation-duration:20s; animation-delay:-6s; bottom:-60%; }
@keyframes neonWaveFloat { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }',
                'js' => '
(function(){
  var c=document.querySelector(".bg-template-neon-waves");
  if(!c) return;
  for(var i=0;i<3;i++){
    var w=document.createElement("div");
    w.className="neon-wave";
    c.appendChild(w);
  }
})();',
            ],
            [
                'name' => 'Gradient Flow',
                'slug' => 'gradient-flow',
                'preview_color' => 'linear-gradient(135deg, #667eea, #764ba2)',
                'category' => 'animated',
                'css' => '
.bg-template-gradient-flow {
  position:fixed; inset:0; z-index:-1;
  background: linear-gradient(-45deg, #0a0612, #1a0533, #0d1b2a, #1a1a3e, #2d1b69);
  background-size: 400% 400%;
  animation: gradientFlow 15s ease infinite;
}
@keyframes gradientFlow {
  0% { background-position:0% 50%; }
  50% { background-position:100% 50%; }
  100% { background-position:0% 50%; }
}',
                'js' => null,
            ],
            [
                'name' => 'Cyber Grid',
                'slug' => 'cyber-grid',
                'preview_color' => 'linear-gradient(180deg, #0a0a1a, #1a0a3a)',
                'category' => 'animated',
                'css' => '
.bg-template-cyber-grid { position:fixed; inset:0; z-index:-1; overflow:hidden; }
.bg-template-cyber-grid::before {
  content:""; position:absolute; inset:0;
  background-image:
    linear-gradient(rgba(139,92,246,0.06) 1px, transparent 1px),
    linear-gradient(90deg, rgba(139,92,246,0.06) 1px, transparent 1px);
  background-size: 50px 50px;
  animation: gridPulse 4s ease-in-out infinite;
  perspective: 500px;
  transform: perspective(500px) rotateX(60deg);
  transform-origin: center top;
}
.bg-template-cyber-grid::after {
  content:""; position:absolute; inset:0;
  background: radial-gradient(ellipse at 50% 0%, rgba(139,92,246,0.1) 0%, transparent 70%);
}
@keyframes gridPulse {
  0%,100% { opacity:0.5; }
  50% { opacity:1; }
}',
                'js' => null,
            ],
            [
                'name' => 'Smoke & Fog',
                'slug' => 'smoke-fog',
                'preview_color' => 'linear-gradient(180deg, #0a0612, #1a1a2e)',
                'category' => 'animated',
                'css' => '
.bg-template-smoke-fog { position:fixed; inset:0; z-index:-1; overflow:hidden; }
.smoke-blob {
  position:absolute; border-radius:50%; filter:blur(80px);
  animation: smokeFloat ease-in-out infinite alternate;
}
.smoke-blob:nth-child(1) { width:500px; height:500px; top:-100px; left:-100px; background:rgba(139,92,246,0.12); animation-duration:12s; }
.smoke-blob:nth-child(2) { width:400px; height:400px; bottom:-50px; right:-100px; background:rgba(236,72,153,0.1); animation-duration:15s; animation-delay:-3s; }
.smoke-blob:nth-child(3) { width:350px; height:350px; top:30%; left:40%; background:rgba(6,182,212,0.08); animation-duration:18s; animation-delay:-6s; }
.smoke-blob:nth-child(4) { width:450px; height:450px; bottom:10%; left:10%; background:rgba(59,130,246,0.1); animation-duration:14s; animation-delay:-2s; }
@keyframes smokeFloat {
  0% { transform:translate(0,0) scale(1); }
  100% { transform:translate(40px,30px) scale(1.15); }
}',
                'js' => '
(function(){
  var c=document.querySelector(".bg-template-smoke-fog");
  if(!c) return;
  for(var i=0;i<4;i++){
    var b=document.createElement("div");
    b.className="smoke-blob";
    c.appendChild(b);
  }
})();',
            ],
            [
                'name' => 'Prism Light',
                'slug' => 'prism-light',
                'preview_color' => 'linear-gradient(135deg, #0f0c29, #302b63, #24243e)',
                'category' => 'animated',
                'css' => '
.bg-template-prism-light { position:fixed; inset:0; z-index:-1; overflow:hidden; }
.prism-ray {
  position:absolute; width:2px; height:100vh;
  top:0; opacity:0;
  animation: prismShine 6s ease-in-out infinite;
}
.prism-ray::after {
  content:""; position:absolute; inset:0;
  background:linear-gradient(180deg, transparent, currentColor, transparent);
}
@keyframes prismShine {
  0% { opacity:0; transform:translateX(-100px) skewX(-15deg); }
  50% { opacity:0.3; }
  100% { opacity:0; transform:translateX(100vw) skewX(-15deg); }
}',
                'js' => '
(function(){
  var c=document.querySelector(".bg-template-prism-light");
  if(!c) return;
  var colors=["#8b5cf6","#ec4899","#06b6d4","#3b82f6","#a855f7","#f59e0b"];
  for(var i=0;i<8;i++){
    var r=document.createElement("div");
    r.className="prism-ray";
    r.style.left=Math.random()*100+"%";
    r.style.color=colors[i%colors.length];
    r.style.animationDelay=(Math.random()*8)+"s";
    r.style.animationDuration=(4+Math.random()*4)+"s";
    c.appendChild(r);
  }
})();',
            ],
            [
                'name' => 'Deep Ocean',
                'slug' => 'deep-ocean',
                'preview_color' => 'linear-gradient(180deg, #000428, #004e92)',
                'category' => 'animated',
                'css' => '
.bg-template-deep-ocean { position:fixed; inset:0; z-index:-1; overflow:hidden;
  background:linear-gradient(180deg, #000428, #001a4e, #004e92);
}
.ocean-caustic {
  position:absolute; inset:0;
  background:
    radial-gradient(ellipse at 20% 80%, rgba(6,182,212,0.08) 0%, transparent 50%),
    radial-gradient(ellipse at 80% 20%, rgba(59,130,246,0.06) 0%, transparent 50%);
  animation: causticShift 10s ease-in-out infinite alternate;
}
.ocean-bubble {
  position:absolute; border-radius:50%; border:1px solid rgba(6,182,212,0.15);
  animation: bubbleRise linear infinite;
}
@keyframes causticShift {
  0% { filter:hue-rotate(0deg); transform:scale(1); }
  100% { filter:hue-rotate(20deg); transform:scale(1.05); }
}
@keyframes bubbleRise {
  0% { transform:translateY(100vh) scale(0); opacity:0; }
  20% { opacity:0.6; }
  100% { transform:translateY(-20px) scale(1); opacity:0; }
}',
                'js' => '
(function(){
  var c=document.querySelector(".bg-template-deep-ocean");
  if(!c) return;
  var cs=document.createElement("div");
  cs.className="ocean-caustic";
  c.appendChild(cs);
  for(var i=0;i<15;i++){
    var b=document.createElement("div");
    b.className="ocean-bubble";
    var s=2+Math.random()*6;
    b.style.width=b.style.height=s+"px";
    b.style.left=Math.random()*100+"%";
    b.style.animationDuration=(6+Math.random()*10)+"s";
    b.style.animationDelay=Math.random()*8+"s";
    c.appendChild(b);
  }
})();',
            ],
        ];
    }
}
