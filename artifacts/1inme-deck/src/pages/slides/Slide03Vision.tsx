export default function Slide03Vision() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.28),transparent_55%),radial-gradient(ellipse_at_bottom_left,rgba(236,72,153,0.2),transparent_55%)]" />

      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]">
        <div className="flex items-center gap-[0.7vw]"><div className="h-[1.4vw] w-[1.4vw] rounded-md bg-gradient-to-br from-violet-500 to-fuchsia-500" /><span className="font-display text-[1.2vw] font-bold tracking-tight">1INME</span></div>
        <span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Vision</span>
      </div>

      <div className="relative h-full w-full px-[8vw] flex flex-col justify-center">
        <span className="text-[1vw] uppercase tracking-[0.3em] text-violet-300">Our vision</span>
        <h2 className="mt-[2vh] font-display text-[6vw] font-bold leading-[1.0] tracking-tight max-w-[78vw]">Your identity is the new homepage of the internet.</h2>
        <p className="mt-[3vh] text-[1.6vw] text-slate-300 max-w-[60vw] leading-snug">1INME turns who you are into a single, intelligent surface &mdash; one place for the people, links, knowledge, and tools that move your day forward.</p>
      </div>

      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>03 / 84</span></div>
    </div>
  );
}
