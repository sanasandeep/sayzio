const base = import.meta.env.BASE_URL;

export default function Slide46Calendar() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_left,rgba(124,58,237,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Calendar &amp; bookings</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] grid grid-cols-12 gap-[3vw]">
        <div className="col-span-5 flex flex-col justify-center">
          <h2 className="font-display text-[3.4vw] font-bold leading-[1.02] tracking-tight">Bookings without the back-and-forth.</h2>
          <p className="mt-[2vh] text-[1.3vw] text-slate-300 max-w-[28vw] leading-snug">Two-way sync with Google, Outlook, and iCloud. Round-robin, paid sessions, group events &mdash; all native.</p>
        </div>
        <div className="col-span-7 flex items-center">
          <div className="w-full rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw]">
            <div className="flex items-center justify-between"><div className="font-display text-[1.5vw] font-semibold">May 2026</div><div className="text-[1vw] text-slate-400">Pacific Time</div></div>
            <div className="mt-[2vh] grid grid-cols-7 gap-[0.4vw] text-center text-[0.95vw] text-slate-400">
              <div>S</div><div>M</div><div>T</div><div>W</div><div>T</div><div>F</div><div>S</div>
            </div>
            <div className="mt-[0.5vh] grid grid-cols-7 gap-[0.4vw] text-center text-[1vw]">
              <div className="aspect-square grid place-items-center text-slate-600">28</div><div className="aspect-square grid place-items-center text-slate-600">29</div><div className="aspect-square grid place-items-center text-slate-600">30</div><div className="aspect-square grid place-items-center">1</div><div className="aspect-square grid place-items-center">2</div><div className="aspect-square grid place-items-center bg-violet-500/20 rounded-md">3</div><div className="aspect-square grid place-items-center">4</div>
              <div className="aspect-square grid place-items-center">5</div><div className="aspect-square grid place-items-center bg-fuchsia-500/30 rounded-md text-fuchsia-100 font-semibold">6</div><div className="aspect-square grid place-items-center">7</div><div className="aspect-square grid place-items-center bg-violet-500/20 rounded-md">8</div><div className="aspect-square grid place-items-center">9</div><div className="aspect-square grid place-items-center">10</div><div className="aspect-square grid place-items-center">11</div>
              <div className="aspect-square grid place-items-center">12</div><div className="aspect-square grid place-items-center">13</div><div className="aspect-square grid place-items-center bg-violet-500/20 rounded-md">14</div><div className="aspect-square grid place-items-center bg-violet-500/20 rounded-md">15</div><div className="aspect-square grid place-items-center">16</div><div className="aspect-square grid place-items-center">17</div><div className="aspect-square grid place-items-center">18</div>
              <div className="aspect-square grid place-items-center">19</div><div className="aspect-square grid place-items-center">20</div><div className="aspect-square grid place-items-center">21</div><div className="aspect-square grid place-items-center bg-violet-500/20 rounded-md">22</div><div className="aspect-square grid place-items-center">23</div><div className="aspect-square grid place-items-center">24</div><div className="aspect-square grid place-items-center">25</div>
              <div className="aspect-square grid place-items-center">26</div><div className="aspect-square grid place-items-center bg-fuchsia-500/30 rounded-md text-fuchsia-100 font-semibold">27</div><div className="aspect-square grid place-items-center">28</div><div className="aspect-square grid place-items-center">29</div><div className="aspect-square grid place-items-center">30</div><div className="aspect-square grid place-items-center">31</div><div />
            </div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>46 / 84</span></div>
    </div>
  );
}
