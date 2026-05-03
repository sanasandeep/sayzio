export default function Slide14ApiSdk() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_left,rgba(34,211,238,0.14),transparent_55%),radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.2),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><div className="flex items-center gap-[0.7vw]"><div className="h-[1.4vw] w-[1.4vw] rounded-md bg-gradient-to-br from-violet-500 to-fuchsia-500" /><span className="font-display text-[1.2vw] font-bold tracking-tight">1INME</span></div><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Platforms · Public API</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] grid grid-cols-12 gap-[3vw]">
        <div className="col-span-6 flex flex-col justify-center">
          <span className="text-[1vw] uppercase tracking-[0.3em] text-cyan-300">Public API &amp; SDK</span>
          <h2 className="mt-[1.5vh] font-display text-[3.8vw] font-bold leading-[1.02] tracking-tight">Build on top of your own identity.</h2>
          <p className="mt-[2.5vh] text-[1.4vw] text-slate-300 max-w-[40vw] leading-snug">A typed REST API, OpenAPI spec, signed webhooks, and per-workspace API keys. Anything you can do in the app, you can automate.</p>
          <div className="mt-[3vh] flex flex-col gap-[1vh] text-[1.2vw]">
            <div className="flex items-center gap-[0.8vw]"><span className="text-cyan-300">&rarr;</span><span>Read &amp; write biolinks, links, contacts</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-cyan-300">&rarr;</span><span>Trigger AI Coach &amp; Mind sessions</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-cyan-300">&rarr;</span><span>Stream click and conversion events</span></div>
          </div>
        </div>
        <div className="col-span-6 flex items-center">
          <div className="w-full rounded-2xl border border-white/10 bg-[#0d0d18] p-[1.4vw] font-mono text-[1.05vw] leading-[1.55] text-slate-300">
            <div className="flex items-center gap-[0.6vw] pb-[1vh] border-b border-white/10"><div className="h-[0.7vw] w-[0.7vw] rounded-full bg-rose-400/60" /><div className="h-[0.7vw] w-[0.7vw] rounded-full bg-amber-300/60" /><div className="h-[0.7vw] w-[0.7vw] rounded-full bg-emerald-400/60" /><div className="ml-auto text-slate-500 text-[0.85vw]">api.1inme.com</div></div>
            <div className="pt-[1.5vh]">
              <div><span className="text-fuchsia-300">POST</span> <span className="text-violet-200">/v1/links</span></div>
              <div className="text-slate-500">Authorization: Bearer ime_live_***</div>
              <div className="mt-[1vh] text-slate-200">{"{"}</div>
              <div className="pl-[1.5vw]"><span className="text-cyan-300">"slug"</span>: <span className="text-emerald-300">"launch"</span>,</div>
              <div className="pl-[1.5vw]"><span className="text-cyan-300">"target"</span>: <span className="text-emerald-300">"https://1inme.com/v2"</span>,</div>
              <div className="pl-[1.5vw]"><span className="text-cyan-300">"rules"</span>: [{"{"} <span className="text-cyan-300">"country"</span>: <span className="text-emerald-300">"US"</span>, <span className="text-cyan-300">"to"</span>: <span className="text-emerald-300">"/us"</span> {"}"}]</div>
              <div className="text-slate-200">{"}"}</div>
              <div className="mt-[1.5vh] text-emerald-300">200 OK &mdash; link created</div>
            </div>
          </div>
        </div>
      </div>

      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>14 / 84</span></div>
    </div>
  );
}
