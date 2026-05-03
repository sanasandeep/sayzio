const base = import.meta.env.BASE_URL;

export default function Slide84ThankYou() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-50 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(circle_at_30%_30%,rgba(124,58,237,0.4),transparent_55%),radial-gradient(circle_at_75%_75%,rgba(236,72,153,0.35),transparent_55%)]" />
      <div className="absolute inset-0 bg-[linear-gradient(180deg,transparent,rgba(0,0,0,0.45))]" />

      <div className="relative h-full w-full px-[8vw] py-[8vh] flex flex-col">
        <div className="flex items-center gap-[1vw]">
          <img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.6vw] w-auto" />
        </div>

        <div className="my-auto">
          <h1 className="font-display text-[10vw] font-bold tracking-tight leading-[0.92]">Thank you.</h1>
          <p className="mt-[3vh] text-[1.7vw] text-slate-200 max-w-[55vw] leading-snug">Questions, partnerships, or a deeper walkthrough &mdash; we&rsquo;d love to talk.</p>
        </div>

        <div className="grid grid-cols-3 gap-[2vw] max-w-[70vw]">
          <div><div className="text-[0.95vw] uppercase tracking-[0.3em] text-fuchsia-200">Web</div><div className="mt-[0.5vh] font-display text-[1.6vw] font-semibold">1inme.com</div></div>
          <div><div className="text-[0.95vw] uppercase tracking-[0.3em] text-fuchsia-200">Email</div><div className="mt-[0.5vh] font-display text-[1.6vw] font-semibold">hello@1inme.com</div></div>
          <div><div className="text-[0.95vw] uppercase tracking-[0.3em] text-fuchsia-200">Social</div><div className="mt-[0.5vh] font-display text-[1.6vw] font-semibold">@1inme</div></div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] right-[5vw] text-[0.9vw] text-slate-400">84 / 84</div>
    </div>
  );
}
