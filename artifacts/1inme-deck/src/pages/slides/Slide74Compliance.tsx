const base = import.meta.env.BASE_URL;

export default function Slide74Compliance() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Compliance &amp; data residency</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.4vw] font-bold leading-[1.02] tracking-tight max-w-[55vw]">Where your data lives is your choice.</h2>

        <div className="mt-[5vh] grid grid-cols-4 gap-[1.5vw]">
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw]"><div className="font-display text-[1.5vw] font-semibold">US East</div><div className="text-[1vw] text-slate-400 mt-[0.4vh]">Virginia, USA</div><div className="text-[0.95vw] text-emerald-300 mt-[0.6vh]">Available</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw]"><div className="font-display text-[1.5vw] font-semibold">EU Central</div><div className="text-[1vw] text-slate-400 mt-[0.4vh]">Frankfurt, DE</div><div className="text-[0.95vw] text-emerald-300 mt-[0.6vh]">Available</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw]"><div className="font-display text-[1.5vw] font-semibold">UK</div><div className="text-[1vw] text-slate-400 mt-[0.4vh]">London, UK</div><div className="text-[0.95vw] text-emerald-300 mt-[0.6vh]">Available</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw]"><div className="font-display text-[1.5vw] font-semibold">APAC</div><div className="text-[1vw] text-slate-400 mt-[0.4vh]">Tokyo, JP</div><div className="text-[0.95vw] text-emerald-300 mt-[0.6vh]">Available</div></div>
        </div>

        <div className="mt-[5vh] flex flex-wrap gap-[1vw] max-w-[70vw]">
          <span className="px-[1.2vw] py-[0.6vh] rounded-full border border-white/15 bg-white/5 text-[1.1vw]">SOC 2 Type II</span>
          <span className="px-[1.2vw] py-[0.6vh] rounded-full border border-white/15 bg-white/5 text-[1.1vw]">ISO 27001</span>
          <span className="px-[1.2vw] py-[0.6vh] rounded-full border border-white/15 bg-white/5 text-[1.1vw]">GDPR</span>
          <span className="px-[1.2vw] py-[0.6vh] rounded-full border border-white/15 bg-white/5 text-[1.1vw]">CCPA</span>
          <span className="px-[1.2vw] py-[0.6vh] rounded-full border border-white/15 bg-white/5 text-[1.1vw]">LGPD</span>
          <span className="px-[1.2vw] py-[0.6vh] rounded-full border border-white/15 bg-white/5 text-[1.1vw]">PIPEDA</span>
          <span className="px-[1.2vw] py-[0.6vh] rounded-full border border-white/15 bg-white/5 text-[1.1vw]">HIPAA-ready</span>
          <span className="px-[1.2vw] py-[0.6vh] rounded-full border border-white/15 bg-white/5 text-[1.1vw]">PCI-DSS via Stripe</span>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>74 / 84</span></div>
    </div>
  );
}
