export default function Slide72PrivacyDesign() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(124,58,237,0.2),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><div className="flex items-center gap-[0.7vw]"><div className="h-[1.4vw] w-[1.4vw] rounded-md bg-gradient-to-br from-violet-500 to-fuchsia-500" /><span className="font-display text-[1.2vw] font-bold tracking-tight">1INME</span></div><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Privacy by design</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.6vw] font-bold leading-[1.02] tracking-tight max-w-[60vw]">Your data is yours. Receipts attached.</h2>

        <div className="mt-[5vh] grid grid-cols-3 gap-[1.5vw]">
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-violet-300">Encryption</div><div className="mt-[1vh] font-display text-[1.6vw] font-semibold">AES-256 + TLS 1.3</div><div className="text-[1.05vw] text-slate-300 mt-[0.5vh]">At rest, in transit, and end-to-end for the Vault.</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-fuchsia-300">Privacy</div><div className="mt-[1vh] font-display text-[1.6vw] font-semibold">GDPR, CCPA, LGPD</div><div className="text-[1.05vw] text-slate-300 mt-[0.5vh]">DPA available, DSR portal built in.</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-cyan-300">Compliance</div><div className="mt-[1vh] font-display text-[1.6vw] font-semibold">SOC 2 Type II</div><div className="text-[1.05vw] text-slate-300 mt-[0.5vh]">Annual audit. Evidence pack on request.</div></div>
        </div>

        <p className="mt-[5vh] text-[1.2vw] text-slate-400 max-w-[60vw]">No selling of personal data. Ever. AI training opt-in, off by default.</p>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>72 / 84</span></div>
    </div>
  );
}
