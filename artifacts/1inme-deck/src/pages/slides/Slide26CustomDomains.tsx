const base = import.meta.env.BASE_URL;

export default function Slide26CustomDomains() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(34,211,238,0.12),transparent_55%),radial-gradient(ellipse_at_bottom_left,rgba(124,58,237,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Custom domains</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.8vw] font-bold leading-[1.02] tracking-tight max-w-[55vw]">Bring your own domain. Keep the magic.</h2>
        <p className="mt-[2vh] text-[1.4vw] text-slate-300 max-w-[55vw]">Point any domain or subdomain. SSL is auto-issued and renewed. Vanity URLs work everywhere &mdash; biolinks, short links, QR.</p>

        <div className="mt-[5vh] grid grid-cols-3 gap-[1.5vw] max-w-[80vw]">
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw]"><div className="font-display text-[1.5vw] font-semibold">CNAME in 60 seconds</div><div className="mt-[0.8vh] text-[1.1vw] text-slate-300">Plug a single record &mdash; 1INME does the rest.</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw]"><div className="font-display text-[1.5vw] font-semibold">Auto SSL renewal</div><div className="mt-[0.8vh] text-[1.1vw] text-slate-300">Free, automated, and verified end-to-end.</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw]"><div className="font-display text-[1.5vw] font-semibold">Multi-domain workspaces</div><div className="mt-[0.8vh] text-[1.1vw] text-slate-300">One brand per domain, one team to manage them.</div></div>
        </div>

        <div className="mt-[5vh] rounded-xl border border-white/10 bg-[#0d0d18] p-[1.4vw] font-mono text-[1.1vw] max-w-[60vw]"><span className="text-slate-500">CNAME</span> <span className="text-fuchsia-300">links.aurora.co</span> <span className="text-slate-500">&rarr;</span> <span className="text-violet-200">edge.1inme.com</span></div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>26 / 84</span></div>
    </div>
  );
}
