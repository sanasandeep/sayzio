const base = import.meta.env.BASE_URL;

export default function Slide73AccountSecurity() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_right,rgba(236,72,153,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Account security</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] grid grid-cols-12 gap-[3vw]">
        <div className="col-span-6 flex flex-col justify-center">
          <h2 className="font-display text-[3.6vw] font-bold leading-[1.02] tracking-tight">Modern auth, on by default.</h2>
          <p className="mt-[2.5vh] text-[1.4vw] text-slate-300 max-w-[36vw] leading-snug">Passkeys, TOTP, hardware keys, SSO via Google, Microsoft, Okta &mdash; plus session controls per device.</p>
          <div className="mt-[3vh] flex flex-col gap-[1vh] text-[1.2vw]">
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>WebAuthn passkeys with biometric unlock</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>Force re-auth on suspicious activity</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>Per-device session list with one-click revoke</span></div>
          </div>
        </div>
        <div className="col-span-6 flex items-center">
          <div className="w-full rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex flex-col gap-[1vh]">
            <div className="text-[1vw] uppercase tracking-[0.25em] text-cyan-300">Active sessions</div>
            <div className="rounded-lg bg-white/[0.04] border border-white/10 p-[1.2vw] flex items-center justify-between"><div><div className="font-display text-[1.3vw] font-semibold">MacBook Pro &middot; Berlin</div><div className="text-[1vw] text-slate-400">Chrome 138 &middot; current session</div></div><div className="text-[0.95vw] px-[0.6vw] py-[0.2vh] rounded bg-emerald-500/15 border border-emerald-400/30 text-emerald-200">this device</div></div>
            <div className="rounded-lg bg-white/[0.04] border border-white/10 p-[1.2vw] flex items-center justify-between"><div><div className="font-display text-[1.3vw] font-semibold">iPhone 17 Pro &middot; Lisbon</div><div className="text-[1vw] text-slate-400">1INME app &middot; 4 hours ago</div></div></div>
            <div className="rounded-lg bg-white/[0.04] border border-white/10 p-[1.2vw] flex items-center justify-between"><div><div className="font-display text-[1.3vw] font-semibold">iPad &middot; Tokyo</div><div className="text-[1vw] text-slate-400">Safari &middot; 3 days ago</div></div></div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>73 / 84</span></div>
    </div>
  );
}
