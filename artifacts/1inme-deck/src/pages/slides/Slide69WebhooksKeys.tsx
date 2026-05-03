const base = import.meta.env.BASE_URL;

export default function Slide69WebhooksKeys() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_left,rgba(34,211,238,0.14),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Webhooks &amp; API keys</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] grid grid-cols-12 gap-[3vw]">
        <div className="col-span-5 flex flex-col justify-center">
          <h2 className="font-display text-[3.4vw] font-bold leading-[1.02] tracking-tight">Send the events that matter, signed.</h2>
          <p className="mt-[2.5vh] text-[1.3vw] text-slate-300 max-w-[28vw] leading-snug">Subscribe to any event, retry on failure, replay on demand &mdash; with HMAC signatures and per-key scopes.</p>
        </div>
        <div className="col-span-7 flex items-center">
          <div className="w-full rounded-2xl border border-white/10 bg-[#0d0d18] p-[1.6vw] font-mono text-[1.05vw] leading-[1.55]">
            <div className="text-slate-500 text-[0.95vw]">POST /webhooks/aurora-launch</div>
            <div className="mt-[1vh] text-slate-200">{"{"}</div>
            <div className="pl-[1.5vw]"><span className="text-cyan-300">"event"</span>: <span className="text-emerald-300">"link.click"</span>,</div>
            <div className="pl-[1.5vw]"><span className="text-cyan-300">"id"</span>: <span className="text-emerald-300">"evt_28KqL4..."</span>,</div>
            <div className="pl-[1.5vw]"><span className="text-cyan-300">"timestamp"</span>: <span className="text-amber-300">1746210984</span>,</div>
            <div className="pl-[1.5vw]"><span className="text-cyan-300">"data"</span>: {"{"}</div>
            <div className="pl-[3vw]"><span className="text-cyan-300">"slug"</span>: <span className="text-emerald-300">"launch"</span>,</div>
            <div className="pl-[3vw]"><span className="text-cyan-300">"country"</span>: <span className="text-emerald-300">"US"</span>,</div>
            <div className="pl-[3vw]"><span className="text-cyan-300">"device"</span>: <span className="text-emerald-300">"ios"</span>,</div>
            <div className="pl-[3vw]"><span className="text-cyan-300">"target"</span>: <span className="text-emerald-300">"https://1inme.com/v2"</span></div>
            <div className="pl-[1.5vw]">{"}"}</div>
            <div className="text-slate-200">{"}"}</div>
            <div className="mt-[1vh] text-slate-500">x-1inme-signature: t=1746210984, v1=8d2...</div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>69 / 84</span></div>
    </div>
  );
}
