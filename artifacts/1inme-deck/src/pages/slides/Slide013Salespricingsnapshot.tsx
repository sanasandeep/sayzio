const base = import.meta.env.BASE_URL;

export default function Slide013Salespricingsnapshot() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.18),transparent_55%),radial-gradient(ellipse_at_bottom_left,rgba(236,72,153,0.12),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw] z-10">
        <img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="Sayzio" className="h-[2.4vw] w-auto" />
        <span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Pricing</span>
      </div>
      <div className="relative h-full w-full px-[7vw] pt-[11vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.4vw] font-bold leading-[1.04] tracking-tight">Pricing, at a glance.</h2>
        <p className="mt-[2vh] text-[1.3vw] text-slate-300 max-w-[65vw]">Pick the plan that matches the team you have today.</p>
        <div className="mt-[4vh] grid grid-cols-4 gap-[1.4vw]">
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex flex-col">
            <div className="flex items-center justify-between"><div className="font-display text-[1.5vw] font-semibold ">Free</div></div>
            <div className="mt-[0.5vh] font-display text-[2.8vw] font-bold leading-none">$0</div>
            <div className="text-[0.95vw] text-slate-400">forever</div>
            <div className="mt-[2vh] flex flex-col gap-[0.5vh] text-[0.95vw] text-slate-200"><div>&middot; 1 Link in Bio</div><div>&middot; 5 short links</div><div>&middot; Basic AI Coach</div><div>&middot; Vault: 25 secrets</div></div>
          </div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex flex-col">
            <div className="flex items-center justify-between"><div className="font-display text-[1.5vw] font-semibold ">Pro</div></div>
            <div className="mt-[0.5vh] font-display text-[2.8vw] font-bold leading-none">$12</div>
            <div className="text-[0.95vw] text-slate-400">per month</div>
            <div className="mt-[2vh] flex flex-col gap-[0.5vh] text-[0.95vw] text-slate-200"><div>&middot; Unlimited links</div><div>&middot; 5,000 AI credits</div><div>&middot; 1 custom domain</div><div>&middot; No Sayzio branding</div></div>
          </div>
          <div className="rounded-2xl border border-fuchsia-400/50 bg-gradient-to-br from-fuchsia-500/15 to-blue-500/10 p-[1.6vw] flex flex-col">
            <div className="flex items-center justify-between"><div className="font-display text-[1.5vw] font-semibold text-fuchsia-200">Studio</div><div className="px-[0.6vw] py-[0.2vh] text-[0.8vw] rounded bg-fuchsia-500/30 text-fuchsia-100">popular</div></div>
            <div className="mt-[0.5vh] font-display text-[2.8vw] font-bold leading-none">$29</div>
            <div className="text-[0.95vw] text-slate-400">per month</div>
            <div className="mt-[2vh] flex flex-col gap-[0.5vh] text-[0.95vw] text-slate-200"><div>&middot; 3 workspaces · 5 seats</div><div>&middot; 20,000 AI credits</div><div>&middot; 3 domains</div><div>&middot; Bookings + payments</div></div>
          </div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex flex-col">
            <div className="flex items-center justify-between"><div className="font-display text-[1.5vw] font-semibold ">Business</div></div>
            <div className="mt-[0.5vh] font-display text-[2.8vw] font-bold leading-none">$99</div>
            <div className="text-[0.95vw] text-slate-400">per month</div>
            <div className="mt-[2vh] flex flex-col gap-[0.5vh] text-[0.95vw] text-slate-200"><div>&middot; Unlimited workspaces</div><div>&middot; White label, SSO</div><div>&middot; SCIM + SOC 2 evidence</div><div>&middot; Priority SLA</div></div>
          </div>
        </div>
        <p className="mt-[3vh] text-[1vw] text-slate-500">Annual billing saves 20%. Education and non-profit pricing on request.</p>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500 z-10"><span>1inme.com</span><span>13 / 189</span></div>
    </div>
  );
}
