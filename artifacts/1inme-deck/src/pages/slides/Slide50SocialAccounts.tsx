const base = import.meta.env.BASE_URL;

export default function Slide50SocialAccounts() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_right,rgba(124,58,237,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Social Accounts hub</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.6vw] font-bold leading-[1.02] tracking-tight max-w-[60vw]">Every platform, one publishing surface.</h2>
        <p className="mt-[2vh] text-[1.3vw] text-slate-300 max-w-[55vw]">Connect your accounts, draft once, schedule everywhere &mdash; and pull engagement back into 1INME analytics.</p>

        <div className="mt-[5vh] grid grid-cols-6 gap-[1.2vw]">
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.4vw] text-center"><div className="font-display text-[1.3vw] font-semibold">Instagram</div><div className="mt-[0.4vh] text-[0.95vw] text-emerald-300">Connected</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.4vw] text-center"><div className="font-display text-[1.3vw] font-semibold">TikTok</div><div className="mt-[0.4vh] text-[0.95vw] text-emerald-300">Connected</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.4vw] text-center"><div className="font-display text-[1.3vw] font-semibold">YouTube</div><div className="mt-[0.4vh] text-[0.95vw] text-emerald-300">Connected</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.4vw] text-center"><div className="font-display text-[1.3vw] font-semibold">LinkedIn</div><div className="mt-[0.4vh] text-[0.95vw] text-emerald-300">Connected</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.4vw] text-center"><div className="font-display text-[1.3vw] font-semibold">X</div><div className="mt-[0.4vh] text-[0.95vw] text-emerald-300">Connected</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.4vw] text-center"><div className="font-display text-[1.3vw] font-semibold">Threads</div><div className="mt-[0.4vh] text-[0.95vw] text-slate-400">Add</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.4vw] text-center"><div className="font-display text-[1.3vw] font-semibold">Pinterest</div><div className="mt-[0.4vh] text-[0.95vw] text-slate-400">Add</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.4vw] text-center"><div className="font-display text-[1.3vw] font-semibold">Bluesky</div><div className="mt-[0.4vh] text-[0.95vw] text-slate-400">Add</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.4vw] text-center"><div className="font-display text-[1.3vw] font-semibold">Facebook</div><div className="mt-[0.4vh] text-[0.95vw] text-slate-400">Add</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.4vw] text-center"><div className="font-display text-[1.3vw] font-semibold">Mastodon</div><div className="mt-[0.4vh] text-[0.95vw] text-slate-400">Add</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.4vw] text-center"><div className="font-display text-[1.3vw] font-semibold">Spotify</div><div className="mt-[0.4vh] text-[0.95vw] text-slate-400">Add</div></div>
          <div className="rounded-2xl border border-fuchsia-400/40 bg-fuchsia-500/10 p-[1.4vw] text-center text-fuchsia-200"><div className="font-display text-[1.3vw] font-semibold">+ 14 more</div><div className="mt-[0.4vh] text-[0.95vw]">Browse all</div></div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>50 / 84</span></div>
    </div>
  );
}
