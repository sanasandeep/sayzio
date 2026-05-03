export default function Slide60SubscriptionTiers() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.2),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><div className="flex items-center gap-[0.7vw]"><div className="h-[1.4vw] w-[1.4vw] rounded-md bg-gradient-to-br from-violet-500 to-fuchsia-500" /><span className="font-display text-[1.2vw] font-bold tracking-tight">1INME</span></div><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Subscription tiers</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.4vw] font-bold leading-[1.02] tracking-tight max-w-[60vw]">A plan for every stage.</h2>

        <div className="mt-[5vh] grid grid-cols-4 gap-[1.5vw]">
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw] flex flex-col"><div className="font-display text-[1.6vw] font-semibold">Free</div><div className="mt-[0.5vh] font-display text-[3.2vw] font-bold leading-none">$0</div><div className="text-[1vw] text-slate-400">forever</div><div className="mt-[2vh] flex flex-col gap-[0.6vh] text-[1vw] text-slate-300"><div>1 biolink</div><div>Basic AI Coach</div><div>5 short links</div><div>Vault for 25 secrets</div></div></div>
          <div className="rounded-2xl border border-violet-400/40 bg-violet-500/10 p-[1.8vw] flex flex-col"><div className="font-display text-[1.6vw] font-semibold text-violet-200">Pro</div><div className="mt-[0.5vh] font-display text-[3.2vw] font-bold leading-none">$12</div><div className="text-[1vw] text-slate-400">per month</div><div className="mt-[2vh] flex flex-col gap-[0.6vh] text-[1vw] text-slate-200"><div>Unlimited biolinks &amp; links</div><div>5,000 AI credits / month</div><div>1 custom domain</div><div>Removed branding</div></div></div>
          <div className="rounded-2xl border border-fuchsia-400/50 bg-gradient-to-br from-fuchsia-500/15 to-violet-500/10 p-[1.8vw] flex flex-col"><div className="flex items-center justify-between"><div className="font-display text-[1.6vw] font-semibold text-fuchsia-200">Studio</div><div className="px-[0.6vw] py-[0.2vh] text-[0.85vw] rounded bg-fuchsia-500/30 text-fuchsia-100">popular</div></div><div className="mt-[0.5vh] font-display text-[3.2vw] font-bold leading-none">$29</div><div className="text-[1vw] text-slate-400">per month</div><div className="mt-[2vh] flex flex-col gap-[0.6vh] text-[1vw] text-slate-200"><div>3 workspaces &middot; 5 seats</div><div>20,000 AI credits / month</div><div>3 custom domains</div><div>Bookings &amp; payments</div></div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw] flex flex-col"><div className="font-display text-[1.6vw] font-semibold">Business</div><div className="mt-[0.5vh] font-display text-[3.2vw] font-bold leading-none">$99</div><div className="text-[1vw] text-slate-400">per month</div><div className="mt-[2vh] flex flex-col gap-[0.6vh] text-[1vw] text-slate-300"><div>Unlimited workspaces &amp; seats</div><div>White-label &amp; SSO</div><div>SCIM &middot; SOC2 evidence</div><div>Priority support &amp; SLA</div></div></div>
        </div>

        <p className="mt-[4vh] text-[1.1vw] text-slate-400">Annual billing saves 20%. Education and non-profit pricing on request.</p>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>60 / 84</span></div>
    </div>
  );
}
