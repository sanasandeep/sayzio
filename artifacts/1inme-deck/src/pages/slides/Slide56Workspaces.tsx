const base = import.meta.env.BASE_URL;

export default function Slide56Workspaces() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(124,58,237,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Workspaces &amp; roles</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.6vw] font-bold leading-[1.02] tracking-tight max-w-[55vw]">Personal, brand, and agency &mdash; in one account.</h2>
        <p className="mt-[2vh] text-[1.3vw] text-slate-300 max-w-[55vw]">Switch contexts in a click. Each workspace has its own identity, billing, members, and data &mdash; all isolated.</p>

        <div className="mt-[5vh] grid grid-cols-3 gap-[1.5vw]">
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-violet-300">Owner</div><div className="mt-[1vh] font-display text-[1.6vw] font-semibold">Full control</div><div className="text-[1.05vw] text-slate-300 mt-[0.5vh]">Billing, domain, white-label, members.</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-fuchsia-300">Editor</div><div className="mt-[1vh] font-display text-[1.6vw] font-semibold">Build &amp; publish</div><div className="text-[1.05vw] text-slate-300 mt-[0.5vh]">All content, no billing or member changes.</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-cyan-300">Viewer</div><div className="mt-[1vh] font-display text-[1.6vw] font-semibold">Read &amp; report</div><div className="text-[1.05vw] text-slate-300 mt-[0.5vh]">See analytics &amp; share, can&rsquo;t edit.</div></div>
        </div>

        <p className="mt-[5vh] text-[1.2vw] text-slate-400 max-w-[55vw]">SSO and SCIM-ready. Custom roles available on Business tier.</p>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>56 / 84</span></div>
    </div>
  );
}
