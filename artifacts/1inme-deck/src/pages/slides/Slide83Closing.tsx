const base = import.meta.env.BASE_URL;

export default function Slide83Closing() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-50 font-body">
      <img src={`${base}hero-cover.png`} crossOrigin="anonymous" alt="" className="absolute inset-0 w-full h-full object-cover opacity-55" />
      <div className="absolute inset-0 bg-[linear-gradient(120deg,rgba(10,10,20,0.92),rgba(20,9,31,0.7)_55%,rgba(10,10,20,0.45))]" />

      <div className="relative h-full w-full px-[8vw] py-[8vh] flex flex-col justify-center">
        <span className="font-display text-[1.1vw] uppercase tracking-[0.5em] text-fuchsia-200">Closing</span>
        <h1 className="mt-[2vh] font-display text-[8.5vw] font-bold tracking-tight leading-[0.92]">One link.<span className="block bg-gradient-to-r from-violet-300 via-fuchsia-300 to-pink-200 bg-clip-text text-transparent">One identity.</span><span className="block">One platform.</span></h1>
        <p className="mt-[3vh] text-[1.6vw] text-slate-200 max-w-[55vw] leading-snug">The everything platform for the people, brands, and teams who&rsquo;d rather be remembered than scattered.</p>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-400"><span>1inme.com</span><span>83 / 84</span></div>
    </div>
  );
}
