const base = import.meta.env.BASE_URL;

export default function Slide34VoiceAssistant() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(124,58,237,0.22),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">AI · Voice</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] grid grid-cols-12 gap-[3vw]">
        <div className="col-span-6 flex flex-col justify-center">
          <span className="text-[1vw] uppercase tracking-[0.3em] text-violet-300">Voice Assistant</span>
          <h2 className="mt-[1.5vh] font-display text-[3.8vw] font-bold leading-[1.02] tracking-tight">Hands-free, on the move.</h2>
          <p className="mt-[2.5vh] text-[1.4vw] text-slate-300 max-w-[36vw] leading-snug">Talk to any Mind from your phone. Drafts, follow-ups, calendar, CRM &mdash; all by voice, all in real-time.</p>
          <div className="mt-[3vh] flex flex-col gap-[1vh] text-[1.2vw]">
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>Sub-300ms voice latency</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>18 voices, 40 languages</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>Whisper-grade transcription</span></div>
          </div>
        </div>
        <div className="col-span-6 flex items-center justify-center">
          <div className="relative h-[44vh] w-[44vh] rounded-full bg-gradient-to-br from-violet-500/30 via-fuchsia-500/30 to-rose-500/20 grid place-items-center">
            <div className="absolute inset-[3vh] rounded-full border border-white/10 animate-none" />
            <div className="absolute inset-[7vh] rounded-full border border-white/10 animate-none" />
            <div className="h-[18vh] w-[18vh] rounded-full bg-[#0a0a14] border border-white/15 grid place-items-center"><div className="flex items-end gap-[0.4vw] h-[6vh]"><div className="w-[0.7vw] h-[40%] rounded bg-fuchsia-300" /><div className="w-[0.7vw] h-[80%] rounded bg-fuchsia-300" /><div className="w-[0.7vw] h-[60%] rounded bg-fuchsia-300" /><div className="w-[0.7vw] h-[95%] rounded bg-fuchsia-300" /><div className="w-[0.7vw] h-[55%] rounded bg-fuchsia-300" /><div className="w-[0.7vw] h-[75%] rounded bg-fuchsia-300" /></div></div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>34 / 84</span></div>
    </div>
  );
}
