const base = import.meta.env.BASE_URL;

export default function Slide09Ecosystem() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(124,58,237,0.25),transparent_60%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Ecosystem map</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col items-center justify-center">
        <h2 className="font-display text-[3.4vw] font-bold leading-[1.02] tracking-tight text-center max-w-[55vw]">Identity at the center. Capabilities orbit around it.</h2>

        <div className="mt-[5vh] relative w-[55vw] h-[44vh]">
          <div className="absolute inset-0 grid place-items-center">
            <div className="h-[14vw] w-[14vw] rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 grid place-items-center shadow-[0_0_8vw_rgba(124,58,237,0.4)]">
              <div className="text-center"><div className="font-display text-[2.2vw] font-bold">YOU</div><div className="text-[0.9vw] uppercase tracking-[0.3em] text-white/80">1inme.com/you</div></div>
            </div>
          </div>
          <div className="absolute top-[2vh] left-[6vw] rounded-xl border border-white/15 bg-white/5 px-[1.2vw] py-[0.8vh] text-[1.05vw] font-medium">Biolinks</div>
          <div className="absolute top-[1vh] right-[6vw] rounded-xl border border-white/15 bg-white/5 px-[1.2vw] py-[0.8vh] text-[1.05vw] font-medium">Dynamic Links</div>
          <div className="absolute top-[40%] left-[1vw] rounded-xl border border-white/15 bg-white/5 px-[1.2vw] py-[0.8vh] text-[1.05vw] font-medium">Vault</div>
          <div className="absolute top-[40%] right-[1vw] rounded-xl border border-white/15 bg-white/5 px-[1.2vw] py-[0.8vh] text-[1.05vw] font-medium">AI Minds</div>
          <div className="absolute bottom-[6vh] left-[3vw] rounded-xl border border-white/15 bg-white/5 px-[1.2vw] py-[0.8vh] text-[1.05vw] font-medium">Calendar &amp; CRM</div>
          <div className="absolute bottom-[6vh] right-[3vw] rounded-xl border border-white/15 bg-white/5 px-[1.2vw] py-[0.8vh] text-[1.05vw] font-medium">Productivity</div>
          <div className="absolute bottom-[1vh] left-[18vw] rounded-xl border border-white/15 bg-white/5 px-[1.2vw] py-[0.8vh] text-[1.05vw] font-medium">Analytics</div>
          <div className="absolute bottom-[1vh] right-[18vw] rounded-xl border border-white/15 bg-white/5 px-[1.2vw] py-[0.8vh] text-[1.05vw] font-medium">Integrations</div>
        </div>
      </div>

      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>09 / 84</span></div>
    </div>
  );
}
