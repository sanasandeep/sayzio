const base = import.meta.env.BASE_URL;

export default function Slide08ProductOverview() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_right,rgba(124,58,237,0.22),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Product overview</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[4.2vw] font-bold leading-[1.02] tracking-tight max-w-[60vw]">Eleven modules. One coherent product.</h2>

        <div className="mt-[5vh] grid grid-cols-4 gap-[1.2vw]">
          <div className="rounded-xl border border-white/10 bg-white/[0.04] p-[1.4vw]"><div className="font-display text-[1.4vw] font-semibold">Biolinks</div><div className="text-[1vw] text-slate-400 mt-[0.5vh]">Owned profile pages</div></div>
          <div className="rounded-xl border border-white/10 bg-white/[0.04] p-[1.4vw]"><div className="font-display text-[1.4vw] font-semibold">Dynamic Links</div><div className="text-[1vw] text-slate-400 mt-[0.5vh]">Smart short URLs</div></div>
          <div className="rounded-xl border border-white/10 bg-white/[0.04] p-[1.4vw]"><div className="font-display text-[1.4vw] font-semibold">QR Studio</div><div className="text-[1vw] text-slate-400 mt-[0.5vh]">Branded, trackable codes</div></div>
          <div className="rounded-xl border border-white/10 bg-white/[0.04] p-[1.4vw]"><div className="font-display text-[1.4vw] font-semibold">AI Ecosystem</div><div className="text-[1vw] text-slate-400 mt-[0.5vh]">Companions, Minds, Coach</div></div>
          <div className="rounded-xl border border-white/10 bg-white/[0.04] p-[1.4vw]"><div className="font-display text-[1.4vw] font-semibold">Vault</div><div className="text-[1vw] text-slate-400 mt-[0.5vh]">Encrypted secret store</div></div>
          <div className="rounded-xl border border-white/10 bg-white/[0.04] p-[1.4vw]"><div className="font-display text-[1.4vw] font-semibold">Productivity</div><div className="text-[1vw] text-slate-400 mt-[0.5vh]">Tasks, forms, files</div></div>
          <div className="rounded-xl border border-white/10 bg-white/[0.04] p-[1.4vw]"><div className="font-display text-[1.4vw] font-semibold">Calendar &amp; CRM</div><div className="text-[1vw] text-slate-400 mt-[0.5vh]">Bookings &amp; contacts</div></div>
          <div className="rounded-xl border border-white/10 bg-white/[0.04] p-[1.4vw]"><div className="font-display text-[1.4vw] font-semibold">Creator Feed</div><div className="text-[1vw] text-slate-400 mt-[0.5vh]">Native social layer</div></div>
          <div className="rounded-xl border border-white/10 bg-white/[0.04] p-[1.4vw]"><div className="font-display text-[1.4vw] font-semibold">Mobile-only</div><div className="text-[1vw] text-slate-400 mt-[0.5vh]">NFC, dialer</div></div>
          <div className="rounded-xl border border-white/10 bg-white/[0.04] p-[1.4vw]"><div className="font-display text-[1.4vw] font-semibold">Analytics</div><div className="text-[1vw] text-slate-400 mt-[0.5vh]">Engagement &amp; funnels</div></div>
          <div className="rounded-xl border border-white/10 bg-white/[0.04] p-[1.4vw]"><div className="font-display text-[1.4vw] font-semibold">Integrations</div><div className="text-[1vw] text-slate-400 mt-[0.5vh]">Apps, webhooks, pixels</div></div>
          <div className="rounded-xl border border-fuchsia-400/40 bg-fuchsia-500/10 p-[1.4vw]"><div className="font-display text-[1.4vw] font-semibold text-fuchsia-200">Security</div><div className="text-[1vw] text-fuchsia-100/80 mt-[0.5vh]">Privacy &amp; compliance</div></div>
        </div>
      </div>

      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>08 / 84</span></div>
    </div>
  );
}
