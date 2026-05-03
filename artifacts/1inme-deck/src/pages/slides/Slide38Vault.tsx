const base = import.meta.env.BASE_URL;

export default function Slide38Vault() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(34,211,238,0.14),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Vault</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] grid grid-cols-12 gap-[3vw]">
        <div className="col-span-6 flex flex-col justify-center">
          <h2 className="font-display text-[3.8vw] font-bold leading-[1.02] tracking-tight">Encrypted on your device. Sealed in the cloud.</h2>
          <p className="mt-[2.5vh] text-[1.4vw] text-slate-300 max-w-[36vw] leading-snug">AES-256 client-side encryption, per-secret keys, zero-knowledge sync. Even we can&rsquo;t read it.</p>
          <div className="mt-[3vh] flex flex-col gap-[1vh] text-[1.2vw]">
            <div className="flex items-center gap-[0.8vw]"><span className="text-cyan-300">&rarr;</span><span>Passwords, API keys, certificates, notes</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-cyan-300">&rarr;</span><span>One-time send links with expiry</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-cyan-300">&rarr;</span><span>Browser autofill on web &amp; mobile</span></div>
          </div>
        </div>
        <div className="col-span-6 flex items-center">
          <div className="w-full rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex flex-col gap-[1vh]">
            <div className="rounded-lg bg-white/[0.04] border border-white/10 p-[1.2vw] flex items-center gap-[1vw]"><div className="h-[2.4vw] w-[2.4vw] rounded-md bg-gradient-to-br from-cyan-400 to-emerald-300 grid place-items-center font-display text-[1.1vw] font-bold text-[#0a0a14]">A</div><div><div className="font-display text-[1.3vw] font-semibold">aurora-prod-db</div><div className="text-[1vw] text-slate-400">Postgres &middot; updated 2 days ago</div></div></div>
            <div className="rounded-lg bg-white/[0.04] border border-white/10 p-[1.2vw] flex items-center gap-[1vw]"><div className="h-[2.4vw] w-[2.4vw] rounded-md bg-gradient-to-br from-violet-400 to-fuchsia-400 grid place-items-center font-display text-[1.1vw] font-bold">S</div><div><div className="font-display text-[1.3vw] font-semibold">stripe-live-key</div><div className="text-[1vw] text-slate-400">API key &middot; shared with Finance</div></div></div>
            <div className="rounded-lg bg-white/[0.04] border border-white/10 p-[1.2vw] flex items-center gap-[1vw]"><div className="h-[2.4vw] w-[2.4vw] rounded-md bg-gradient-to-br from-fuchsia-400 to-rose-400 grid place-items-center font-display text-[1.1vw] font-bold">N</div><div><div className="font-display text-[1.3vw] font-semibold">notion-team-token</div><div className="text-[1vw] text-slate-400">Token &middot; auto-rotates monthly</div></div></div>
            <div className="rounded-lg bg-white/[0.04] border border-white/10 p-[1.2vw] flex items-center gap-[1vw]"><div className="h-[2.4vw] w-[2.4vw] rounded-md bg-gradient-to-br from-amber-400 to-rose-400 grid place-items-center font-display text-[1.1vw] font-bold">L</div><div><div className="font-display text-[1.3vw] font-semibold">linkedin-prefs</div><div className="text-[1vw] text-slate-400">Login &middot; biometric unlock</div></div></div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>38 / 84</span></div>
    </div>
  );
}
