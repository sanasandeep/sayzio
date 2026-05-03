export default function Slide39VaultSharing() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_left,rgba(124,58,237,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><div className="flex items-center gap-[0.7vw]"><div className="h-[1.4vw] w-[1.4vw] rounded-md bg-gradient-to-br from-violet-500 to-fuchsia-500" /><span className="font-display text-[1.2vw] font-bold tracking-tight">1INME</span></div><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Vault · Sharing &amp; teams</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.6vw] font-bold leading-[1.02] tracking-tight max-w-[60vw]">Share secrets without losing control.</h2>

        <div className="mt-[5vh] grid grid-cols-3 gap-[1.5vw]">
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-cyan-300">Vaults</div><div className="mt-[1vh] font-display text-[1.7vw] font-semibold">Per team, per project</div><div className="mt-[0.8vh] text-[1.1vw] text-slate-300">Group secrets the way your org actually works.</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-violet-300">Roles</div><div className="mt-[1vh] font-display text-[1.7vw] font-semibold">Read · use · admin</div><div className="mt-[0.8vh] text-[1.1vw] text-slate-300">Grant exactly the level of access required.</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-fuchsia-300">Audit</div><div className="mt-[1vh] font-display text-[1.7vw] font-semibold">Every access logged</div><div className="mt-[0.8vh] text-[1.1vw] text-slate-300">Know who saw what, when, and from where.</div></div>
        </div>

        <p className="mt-[5vh] text-[1.2vw] text-slate-400 max-w-[55vw]">Offboarding revokes everything in one click. SSO and SCIM-ready.</p>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>39 / 84</span></div>
    </div>
  );
}
