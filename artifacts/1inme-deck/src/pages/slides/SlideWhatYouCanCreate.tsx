const base = import.meta.env.BASE_URL;

export default function SlideWhatYouCanCreate() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.18),transparent_55%),radial-gradient(ellipse_at_bottom_left,rgba(236,72,153,0.12),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw] z-10">
        <img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="Sayzio" className="h-[2.4vw] w-auto" />
        <span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400"></span>
      </div>
      <div className="relative h-full w-full px-[7vw] pt-[10vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.4vw] font-bold leading-[1.04] tracking-tight max-w-[70vw]">What you can create.</h2>
        <p className="mt-[1.6vh] text-[1.3vw] text-slate-300 max-w-[68vw]">Twelve link types, grouped four ways — every one lives at a single 1inme.com link.</p>

        <div className="mt-[3.5vh] grid grid-cols-4 gap-[1.4vw] flex-1 content-start">

          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.3vw] flex flex-col">
            <div className="text-[0.85vw] uppercase tracking-[0.22em] text-violet-300">Everyday links</div>
            <div className="mt-[0.8vh] text-[0.92vw] text-slate-400 leading-snug">Quick, single-purpose links you can share anywhere in seconds.</div>
            <div className="mt-[1.6vh] flex items-start gap-[0.7vw]">
              <span className="mt-[0.4vh] h-[0.9vw] w-[0.9vw] rounded-md bg-violet-400 shrink-0" />
              <div>
                <div className="font-display text-[1.05vw] font-semibold leading-tight">Short Link</div>
                <div className="text-[0.85vw] text-slate-400 leading-snug">Shorten any URL with a custom alias and click tracking.</div>
              </div>
            </div>
            <div className="mt-[1.3vh] flex items-start gap-[0.7vw]">
              <span className="mt-[0.4vh] h-[0.9vw] w-[0.9vw] rounded-md bg-emerald-400 shrink-0" />
              <div>
                <div className="font-display text-[1.05vw] font-semibold leading-tight">File Share</div>
                <div className="text-[0.85vw] text-slate-400 leading-snug">Share a downloadable file behind a short link.</div>
              </div>
            </div>
            <div className="mt-[1.3vh] flex items-start gap-[0.7vw]">
              <span className="mt-[0.4vh] h-[0.9vw] w-[0.9vw] rounded-md bg-amber-400 shrink-0" />
              <div>
                <div className="font-display text-[1.05vw] font-semibold leading-tight">Event</div>
                <div className="text-[0.85vw] text-slate-400 leading-snug">A calendar event visitors can add in a single tap.</div>
              </div>
            </div>
            <div className="mt-[1.3vh] flex items-start gap-[0.7vw]">
              <span className="mt-[0.4vh] h-[0.9vw] w-[0.9vw] rounded-md bg-cyan-400 shrink-0" />
              <div>
                <div className="font-display text-[1.05vw] font-semibold leading-tight">Contact Card</div>
                <div className="text-[0.85vw] text-slate-400 leading-snug">A digital business card visitors can save instantly.</div>
              </div>
            </div>
          </div>

          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.3vw] flex flex-col">
            <div className="text-[0.85vw] uppercase tracking-[0.22em] text-pink-300">Pages & mini-sites</div>
            <div className="mt-[0.8vh] text-[0.92vw] text-slate-400 leading-snug">Full, customizable pages that live at a single link — no website needed.</div>
            <div className="mt-[1.6vh] flex items-start gap-[0.7vw]">
              <span className="mt-[0.4vh] h-[0.9vw] w-[0.9vw] rounded-md bg-pink-400 shrink-0" />
              <div>
                <div className="font-display text-[1.05vw] font-semibold leading-tight">Link in Bio</div>
                <div className="text-[0.85vw] text-slate-400 leading-snug">A mini-site of your links, blocks and media on one page.</div>
              </div>
            </div>
            <div className="mt-[1.3vh] flex items-start gap-[0.7vw]">
              <span className="mt-[0.4vh] h-[0.9vw] w-[0.9vw] rounded-md bg-fuchsia-400 shrink-0" />
              <div>
                <div className="font-display text-[1.05vw] font-semibold leading-tight">Slides</div>
                <div className="text-[0.85vw] text-slate-400 leading-snug">Present a swipeable deck of slides from a single link.</div>
              </div>
            </div>
            <div className="mt-[1.3vh] flex items-start gap-[0.7vw]">
              <span className="mt-[0.4vh] h-[0.9vw] w-[0.9vw] rounded-md bg-orange-400 shrink-0" />
              <div>
                <div className="font-display text-[1.05vw] font-semibold leading-tight">Restaurant Menu</div>
                <div className="text-[0.85vw] text-slate-400 leading-snug">A digital menu with sections, items and prices.</div>
              </div>
            </div>
            <div className="mt-[1.3vh] flex items-start gap-[0.7vw]">
              <span className="mt-[0.4vh] h-[0.9vw] w-[0.9vw] rounded-md bg-indigo-400 shrink-0" />
              <div>
                <div className="font-display text-[1.05vw] font-semibold leading-tight">Resume / Portfolio</div>
                <div className="text-[0.85vw] text-slate-400 leading-snug">A shareable resume / portfolio page with PDF download.</div>
              </div>
            </div>
          </div>

          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.3vw] flex flex-col">
            <div className="text-[0.85vw] uppercase tracking-[0.22em] text-rose-300">Business & monetization</div>
            <div className="mt-[0.8vh] text-[0.92vw] text-slate-400 leading-snug">Grow your reputation and earn from your audience.</div>
            <div className="mt-[1.6vh] flex items-start gap-[0.7vw]">
              <span className="mt-[0.4vh] h-[0.9vw] w-[0.9vw] rounded-md bg-rose-400 shrink-0" />
              <div>
                <div className="font-display text-[1.05vw] font-semibold leading-tight">Bizs Profile</div>
                <div className="text-[0.85vw] text-slate-400 leading-snug">A themeable home that automatically shows all your posts, tiers & tips — no linking needed.</div>
              </div>
            </div>
            <div className="mt-[1.3vh] flex items-start gap-[0.7vw]">
              <span className="mt-[0.4vh] h-[0.9vw] w-[0.9vw] rounded-md bg-yellow-400 shrink-0" />
              <div>
                <div className="font-display text-[1.05vw] font-semibold leading-tight">Reviews Page</div>
                <div className="text-[0.85vw] text-slate-400 leading-snug">Collect and showcase reviews from your audience.</div>
              </div>
            </div>
          </div>

          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.3vw] flex flex-col">
            <div className="text-[0.85vw] uppercase tracking-[0.22em] text-teal-300">AI-powered</div>
            <div className="mt-[0.8vh] text-[0.92vw] text-slate-400 leading-snug">Let AI answer and guide your visitors for you.</div>
            <div className="mt-[1.6vh] flex items-start gap-[0.7vw]">
              <span className="mt-[0.4vh] h-[0.9vw] w-[0.9vw] rounded-md bg-teal-400 shrink-0" />
              <div>
                <div className="font-display text-[1.05vw] font-semibold leading-tight">AI Chatbot</div>
                <div className="text-[0.85vw] text-slate-400 leading-snug">An AI assistant that answers your visitors for you.</div>
              </div>
            </div>
            <div className="mt-[1.3vh] flex items-start gap-[0.7vw]">
              <span className="mt-[0.4vh] h-[0.9vw] w-[0.9vw] rounded-md bg-sky-400 shrink-0" />
              <div>
                <div className="font-display text-[1.05vw] font-semibold leading-tight">Conversational</div>
                <div className="text-[0.85vw] text-slate-400 leading-snug">A guided, chat-style page that responds as visitors tap.</div>
              </div>
            </div>
          </div>

        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500 z-10"><span>1inme.com</span><span>26 / 189</span></div>
    </div>
  );
}
