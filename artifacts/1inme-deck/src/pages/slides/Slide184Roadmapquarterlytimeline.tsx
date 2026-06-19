const base = import.meta.env.BASE_URL;

export default function Slide184Roadmapquarterlytimeline() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.18),transparent_55%),radial-gradient(ellipse_at_bottom_left,rgba(236,72,153,0.12),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw] z-10">
        <img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" />
        <span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Quarterly view</span>
      </div>
      <div className="relative h-full w-full px-[7vw] pt-[11vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.2vw] font-bold leading-[1.04] tracking-tight">Quarterly timeline.</h2>
        <p className="mt-[2vh] text-[1.2vw] text-slate-300 max-w-[65vw]">What ships when, across the platform.</p>
        <div className="mt-[4vh] flex-1 flex flex-col">
          <div className="grid grid-cols-5 gap-[1vw] pb-[1vh]"><div className="text-[0.9vw] uppercase tracking-[0.25em] text-slate-400 ">Theme</div><div className="text-[0.9vw] uppercase tracking-[0.25em] text-slate-400 ">Q1</div><div className="text-[0.9vw] uppercase tracking-[0.25em] text-slate-400 ">Q2</div><div className="text-[0.9vw] uppercase tracking-[0.25em] text-slate-400 ">Q3</div><div className="text-[0.9vw] uppercase tracking-[0.25em] text-slate-400 ">Q4</div></div>
            <div className="grid grid-cols-5 gap-[1vw] py-[1vh] border-t border-white/10">
              <div className="font-display text-[1.1vw] font-semibold text-violet-200">AI</div>
              <div className="text-[0.95vw] text-slate-300">Companions 2.0</div><div className="text-[0.95vw] text-slate-300">AskCoach 3.0</div><div className="text-[0.95vw] text-slate-300">Voice GA</div><div className="text-[0.95vw] text-slate-300">Open Companion API</div>
            </div>
            <div className="grid grid-cols-5 gap-[1vw] py-[1vh] border-t border-white/10">
              <div className="font-display text-[1.1vw] font-semibold text-violet-200">Bio + Links</div>
              <div className="text-[0.95vw] text-slate-300">Editor v3</div><div className="text-[0.95vw] text-slate-300">Routing v3</div><div className="text-[0.95vw] text-slate-300">Bio AB tests</div><div className="text-[0.95vw] text-slate-300">Programmable bios</div>
            </div>
            <div className="grid grid-cols-5 gap-[1vw] py-[1vh] border-t border-white/10">
              <div className="font-display text-[1.1vw] font-semibold text-violet-200">Productivity</div>
              <div className="text-[0.95vw] text-slate-300">Pipeline 2.0</div><div className="text-[0.95vw] text-slate-300">Vault sharing 2.0</div><div className="text-[0.95vw] text-slate-300">Forms 2.0</div><div className="text-[0.95vw] text-slate-300">Workflows 2.0</div>
            </div>
            <div className="grid grid-cols-5 gap-[1vw] py-[1vh] border-t border-white/10">
              <div className="font-display text-[1.1vw] font-semibold text-violet-200">Mobile</div>
              <div className="text-[0.95vw] text-slate-300">NFC v2</div><div className="text-[0.95vw] text-slate-300">Dialer 2.0</div><div className="text-[0.95vw] text-slate-300">Mobile bio editor</div><div className="text-[0.95vw] text-slate-300">Offline-first</div>
            </div>
            <div className="grid grid-cols-5 gap-[1vw] py-[1vh] border-t border-white/10">
              <div className="font-display text-[1.1vw] font-semibold text-violet-200">Enterprise</div>
              <div className="text-[0.95vw] text-slate-300">SSO + SCIM</div><div className="text-[0.95vw] text-slate-300">Workspace federation</div><div className="text-[0.95vw] text-slate-300">SOC 2 Type II</div><div className="text-[0.95vw] text-slate-300">Data residency</div>
            </div>
            <div className="grid grid-cols-5 gap-[1vw] py-[1vh] border-t border-white/10">
              <div className="font-display text-[1.1vw] font-semibold text-violet-200">Marketplace</div>
              <div className="text-[0.95vw] text-slate-300">Templates v1</div><div className="text-[0.95vw] text-slate-300">Paid templates</div><div className="text-[0.95vw] text-slate-300">Apps · alpha</div><div className="text-[0.95vw] text-slate-300">Apps · GA</div>
            </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500 z-10"><span>1inme.com</span><span>185 / 189</span></div>
    </div>
  );
}
