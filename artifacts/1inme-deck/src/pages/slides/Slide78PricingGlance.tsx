export default function Slide78PricingGlance() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.2),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><div className="flex items-center gap-[0.7vw]"><div className="h-[1.4vw] w-[1.4vw] rounded-md bg-gradient-to-br from-violet-500 to-fuchsia-500" /><span className="font-display text-[1.2vw] font-bold tracking-tight">1INME</span></div><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Plans at a glance</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.4vw] font-bold leading-[1.02] tracking-tight max-w-[55vw]">Replace your stack with one line item.</h2>

        <div className="mt-[5vh] rounded-2xl border border-white/10 bg-white/[0.04] overflow-hidden">
          <div className="grid grid-cols-5 px-[1.5vw] py-[1.4vh] text-[1vw] text-slate-400 uppercase tracking-[0.2em] border-b border-white/10"><div>Capability</div><div>Free</div><div>Pro</div><div>Studio</div><div>Business</div></div>
          <div className="grid grid-cols-5 px-[1.5vw] py-[1.4vh] text-[1.05vw] border-b border-white/5 items-center"><div>Biolinks</div><div>1</div><div>Unlimited</div><div>Unlimited</div><div>Unlimited</div></div>
          <div className="grid grid-cols-5 px-[1.5vw] py-[1.4vh] text-[1.05vw] border-b border-white/5 items-center"><div>Custom domains</div><div>&mdash;</div><div>1</div><div>3</div><div>Unlimited</div></div>
          <div className="grid grid-cols-5 px-[1.5vw] py-[1.4vh] text-[1.05vw] border-b border-white/5 items-center"><div>AI credits per month</div><div>500</div><div>5,000</div><div>20,000</div><div>100,000</div></div>
          <div className="grid grid-cols-5 px-[1.5vw] py-[1.4vh] text-[1.05vw] border-b border-white/5 items-center"><div>Vault secrets</div><div>25</div><div>500</div><div>5,000</div><div>Unlimited</div></div>
          <div className="grid grid-cols-5 px-[1.5vw] py-[1.4vh] text-[1.05vw] border-b border-white/5 items-center"><div>Team seats</div><div>1</div><div>1</div><div>5</div><div>Unlimited</div></div>
          <div className="grid grid-cols-5 px-[1.5vw] py-[1.4vh] text-[1.05vw] border-b border-white/5 items-center"><div>White-label</div><div>&mdash;</div><div>&mdash;</div><div>&mdash;</div><div>Yes</div></div>
          <div className="grid grid-cols-5 px-[1.5vw] py-[1.4vh] text-[1.05vw] border-b border-white/5 items-center"><div>SSO &amp; SCIM</div><div>&mdash;</div><div>&mdash;</div><div>&mdash;</div><div>Yes</div></div>
          <div className="grid grid-cols-5 px-[1.5vw] py-[1.4vh] text-[1.15vw] items-center font-display font-semibold"><div className="text-slate-300">Monthly price</div><div>$0</div><div>$12</div><div className="text-fuchsia-200">$29</div><div>$99</div></div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>78 / 84</span></div>
    </div>
  );
}
