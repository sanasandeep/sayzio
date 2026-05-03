const base = import.meta.env.BASE_URL;

export default function Slide49CreatorFeed() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(124,58,237,0.2),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Creator Feed</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] grid grid-cols-12 gap-[3vw]">
        <div className="col-span-6 flex flex-col justify-center">
          <h2 className="font-display text-[3.6vw] font-bold leading-[1.02] tracking-tight">Your audience, on your terms.</h2>
          <p className="mt-[2.5vh] text-[1.4vw] text-slate-300 max-w-[36vw] leading-snug">Post text, photos, audio, or short video directly to the people who care &mdash; no algorithm, no platform tax.</p>
          <div className="mt-[3vh] flex flex-col gap-[1vh] text-[1.2vw]">
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>Followers see your posts in chronological order</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>Free, paid, and members-only tiers</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>Web, mobile push, and email digest</span></div>
          </div>
        </div>
        <div className="col-span-6 flex items-center">
          <div className="w-full rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex flex-col gap-[1.4vh]">
            <div className="flex items-center gap-[1vw]"><div className="h-[2.6vw] w-[2.6vw] rounded-full bg-gradient-to-br from-violet-400 to-fuchsia-400" /><div><div className="font-display text-[1.3vw] font-semibold">Aurora Labs</div><div className="text-[0.95vw] text-slate-400">@aurora &middot; 12 minutes ago</div></div></div>
            <div className="text-[1.2vw] text-slate-200">v2 of the design system is live. Three new themes, fully token-driven, and 30% lighter on first paint.</div>
            <div className="rounded-xl h-[18vh] bg-gradient-to-br from-violet-500/30 via-fuchsia-500/20 to-rose-500/15 border border-white/10" />
            <div className="flex items-center gap-[2vw] text-[1vw] text-slate-400"><span>318 likes</span><span>42 replies</span><span>9 reposts</span></div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>49 / 84</span></div>
    </div>
  );
}
