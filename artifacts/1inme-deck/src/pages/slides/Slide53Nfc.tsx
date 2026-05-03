const base = import.meta.env.BASE_URL;

export default function Slide53Nfc() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(34,211,238,0.14),transparent_55%),radial-gradient(ellipse_at_bottom_right,rgba(124,58,237,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Mobile · NFC</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] grid grid-cols-12 gap-[3vw]">
        <div className="col-span-6 flex flex-col justify-center">
          <h2 className="font-display text-[3.8vw] font-bold leading-[1.02] tracking-tight">Tap, share, done.</h2>
          <p className="mt-[2.5vh] text-[1.4vw] text-slate-300 max-w-[36vw] leading-snug">Touch any phone or NFC card to share your full identity in under a second &mdash; no app install required for the receiver.</p>
          <div className="mt-[3vh] flex flex-col gap-[1vh] text-[1.2vw]">
            <div className="flex items-center gap-[0.8vw]"><span className="text-cyan-300">&rarr;</span><span>Programmable 1INME NFC cards</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-cyan-300">&rarr;</span><span>Phone-to-phone bump on iOS &amp; Android</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-cyan-300">&rarr;</span><span>Switch identity per event or context</span></div>
          </div>
        </div>
        <div className="col-span-6 flex items-center justify-center">
          <div className="relative w-[36vh] h-[36vh] grid place-items-center">
            <div className="absolute inset-0 rounded-full border-[0.6vh] border-violet-400/30" />
            <div className="absolute inset-[3vh] rounded-full border-[0.6vh] border-fuchsia-400/40" />
            <div className="absolute inset-[7vh] rounded-full border-[0.6vh] border-cyan-300/50" />
            <div className="h-[14vh] w-[20vh] rounded-2xl bg-gradient-to-br from-violet-500 to-fuchsia-500 grid place-items-center font-display text-[1.4vw] font-bold tracking-tight text-white">1INME</div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>53 / 84</span></div>
    </div>
  );
}
