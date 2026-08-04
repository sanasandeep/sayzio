import React from 'react';
import { Plus, X, Tag, CalendarDays, Zap, AlertTriangle, Clock, MapPin as MapPinIcon } from 'lucide-react';

export function TooManyState() {
  return (
    <div className="min-h-screen bg-[#0A0A0A] text-white p-6 font-sans relative pb-32">
      <div className="max-w-6xl mx-auto space-y-6">
        {/* Header */}
        <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div className="flex items-center gap-3">
                    <h1 className="text-2xl font-bold text-white">Global Tech & Design</h1>
                    <span className="flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-blue-500/20 border border-blue-500/30 text-[10px] uppercase tracking-widest font-bold text-blue-400">
                        <Zap className="w-3 h-3" /> Smart
                    </span>
                </div>
                <p className="text-xs text-white/40 mt-1">Auto-syncing events from calendars you own or follow.</p>
            </div>
            <div className="flex items-center gap-2">
                <button className="px-4 py-2 rounded-xl text-sm font-medium border border-white/10 text-white/70 hover:text-white hover:border-white/30 transition">
                    Edit Rules
                </button>
            </div>
        </div>

        {/* Notice Band */}
        <div className="bg-amber-500/10 border border-amber-500/20 rounded-2xl p-5 flex items-start gap-4 backdrop-blur-md shadow-lg shadow-amber-500/5">
            <div className="w-10 h-10 rounded-full bg-amber-500/20 flex items-center justify-center shrink-0">
                <AlertTriangle className="w-5 h-5 text-amber-500" />
            </div>
            <div className="flex-1 pt-0.5">
                <h3 className="text-sm font-semibold text-amber-500">1,240 events matched</h3>
                <p className="text-xs text-amber-500/70 mt-1 max-w-2xl leading-relaxed">That's a lot of events! Your rules might be too broad. Consider narrowing them down to make this calendar more focused and easier to navigate.</p>
                <div className="flex flex-wrap items-center gap-2.5 mt-4">
                    <button className="px-3.5 py-1.5 rounded-lg text-xs font-medium border border-amber-500/30 text-amber-500 hover:bg-amber-500/20 transition inline-flex items-center gap-1.5 bg-amber-500/10">
                        <Plus className="w-3.5 h-3.5" /> Add Location
                    </button>
                    <button className="px-3.5 py-1.5 rounded-lg text-xs font-medium border border-amber-500/30 text-amber-500 hover:bg-amber-500/20 transition inline-flex items-center gap-1.5 bg-amber-500/10">
                        <CalendarDays className="w-3.5 h-3.5" /> Restrict to Next 30 Days
                    </button>
                    <button className="px-3.5 py-1.5 rounded-lg text-xs font-medium border border-amber-500/30 text-amber-500 hover:bg-amber-500/20 transition inline-flex items-center gap-1.5 bg-amber-500/10">
                        <Plus className="w-3.5 h-3.5" /> Require specific #tags
                    </button>
                </div>
            </div>
        </div>

        {/* Active Rules */}
        <div className="bg-white/[0.03] border border-white/10 backdrop-blur-xl rounded-2xl p-5">
            <h2 className="text-[11px] uppercase tracking-widest text-white/30 mb-3 font-semibold">Active Rules</h2>
            <div className="flex flex-wrap items-center gap-2">
                <div className="inline-flex items-center gap-2 text-xs px-3 py-1.5 rounded-full border border-blue-400/60 bg-blue-500/15 text-white group cursor-pointer hover:bg-blue-500/20 transition">
                    <Tag className="w-3.5 h-3.5 text-blue-400" />
                    #tech <span className="text-blue-300/50 mx-0.5">OR</span> #design
                    <X className="w-3.5 h-3.5 text-blue-300 opacity-50 group-hover:opacity-100 ml-1" />
                </div>
                <div className="inline-flex items-center gap-2 text-xs px-3 py-1.5 rounded-full border border-blue-400/60 bg-blue-500/15 text-white group cursor-pointer hover:bg-blue-500/20 transition">
                    <CalendarDays className="w-3.5 h-3.5 text-blue-400" />
                    Any date
                    <X className="w-3.5 h-3.5 text-blue-300 opacity-50 group-hover:opacity-100 ml-1" />
                </div>
                <div className="inline-flex items-center gap-2 text-xs px-3 py-1.5 rounded-full border border-white/10 text-white/60 hover:text-white hover:border-white/30 hover:bg-white/[0.05] transition cursor-pointer">
                    <Plus className="w-3.5 h-3.5" /> Add Rule
                </div>
            </div>
        </div>

        {/* Agenda View */}
        <div className="space-y-1.5 relative">
            <div className="flex items-center gap-3 pt-4 pb-2 px-1">
                <span className="text-[11px] font-semibold uppercase tracking-widest text-blue-400">
                    Today · October 24, 2023
                </span>
                <div className="flex-1 h-px bg-white/[0.06]"></div>
            </div>

            {/* Event Card 1 */}
            <div className="group bg-white/[0.03] border border-white/10 rounded-2xl p-4 flex items-start gap-4 transition hover:bg-white/[0.06] backdrop-blur-md">
                <div className="flex flex-col items-center justify-center w-12 flex-shrink-0 rounded-xl py-2.5 gap-0.5" style={{ background: '#10b98118', borderColor: '#10b98130', borderWidth: '1px' }}>
                    <span className="text-[9px] uppercase tracking-wider font-semibold" style={{ color: '#10b98199' }}>Oct</span>
                    <span className="text-[22px] font-extrabold leading-none" style={{ color: '#10b981ee' }}>24</span>
                </div>
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2 flex-wrap">
                        <h3 className="font-semibold text-white/90 group-hover:text-white transition truncate">Startup Grind MENA - Pitch Night</h3>
                        <span className="text-[10px] px-2 py-0.5 rounded-full bg-blue-500/15 text-blue-300 font-medium">Today</span>
                    </div>
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-1.5">
                        <span className="text-xs text-white/45 flex items-center gap-1">
                            <Clock className="w-3 h-3" /> 6:00 PM – 9:00 PM
                        </span>
                        <span className="text-xs flex items-center gap-1.5 text-white/35">
                            <span className="w-2 h-2 rounded-full flex-shrink-0 bg-[#10b981]"></span>
                            Startup Grind MENA
                        </span>
                        <span className="text-xs text-white/40 flex items-center gap-1">
                            <MapPinIcon className="w-3 h-3" /> Dubai
                        </span>
                    </div>
                    <div className="flex flex-wrap gap-1 mt-2">
                        <span className="text-[11px] px-2 py-0.5 rounded-full bg-blue-500/10 text-blue-300">#tech</span>
                        <span className="text-[11px] px-2 py-0.5 rounded-full bg-blue-500/10 text-blue-300">#startups</span>
                        <span className="text-[11px] px-2 py-0.5 rounded-full bg-blue-500/10 text-blue-300">#pitch</span>
                    </div>
                </div>
            </div>

            {/* Event Card 2 */}
            <div className="group bg-white/[0.03] border border-white/10 rounded-2xl p-4 flex items-start gap-4 transition hover:bg-white/[0.06] backdrop-blur-md">
                <div className="flex flex-col items-center justify-center w-12 flex-shrink-0 rounded-xl py-2.5 gap-0.5" style={{ background: '#3d6bff18', borderColor: '#3d6bff30', borderWidth: '1px' }}>
                    <span className="text-[9px] uppercase tracking-wider font-semibold" style={{ color: '#3d6bff99' }}>Oct</span>
                    <span className="text-[22px] font-extrabold leading-none" style={{ color: '#3d6bffee' }}>24</span>
                </div>
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2 flex-wrap">
                        <h3 className="font-semibold text-white/90 group-hover:text-white transition truncate">Founders Coffee (Online)</h3>
                    </div>
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-1.5">
                        <span className="text-xs text-white/45 flex items-center gap-1">
                            <Clock className="w-3 h-3" /> 10:00 AM – 11:00 AM
                        </span>
                        <span className="text-xs flex items-center gap-1.5 text-white/35">
                            <span className="w-2 h-2 rounded-full flex-shrink-0 bg-[#3d6bff]"></span>
                            Founders Coffee
                        </span>
                        <span className="text-xs text-white/40 flex items-center gap-1">
                            <MapPinIcon className="w-3 h-3" /> Online
                        </span>
                    </div>
                    <div className="flex flex-wrap gap-1 mt-2">
                        <span className="text-[11px] px-2 py-0.5 rounded-full bg-blue-500/10 text-blue-300">#design</span>
                        <span className="text-[11px] px-2 py-0.5 rounded-full bg-blue-500/10 text-blue-300">#founders</span>
                    </div>
                </div>
            </div>

            {/* Event Card 3 (Fade out visually) */}
            <div className="group bg-white/[0.03] border border-white/10 rounded-2xl p-4 flex items-start gap-4 transition hover:bg-white/[0.06] backdrop-blur-md opacity-50 relative z-0">
                <div className="flex flex-col items-center justify-center w-12 flex-shrink-0 rounded-xl py-2.5 gap-0.5" style={{ background: '#f59e0b18', borderColor: '#f59e0b30', borderWidth: '1px' }}>
                    <span className="text-[9px] uppercase tracking-wider font-semibold" style={{ color: '#f59e0b99' }}>Oct</span>
                    <span className="text-[22px] font-extrabold leading-none" style={{ color: '#f59e0bee' }}>25</span>
                </div>
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2 flex-wrap">
                        <h3 className="font-semibold text-white/90 group-hover:text-white transition truncate">UI/UX Designer Meetup</h3>
                    </div>
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-1.5">
                        <span className="text-xs text-white/45 flex items-center gap-1">
                            <Clock className="w-3 h-3" /> 7:00 PM – 9:00 PM
                        </span>
                        <span className="text-xs flex items-center gap-1.5 text-white/35">
                            <span className="w-2 h-2 rounded-full flex-shrink-0 bg-[#f59e0b]"></span>
                            Designers UAE
                        </span>
                    </div>
                </div>
            </div>

        </div>
        
        {/* Fixed bottom fade */}
        <div className="fixed bottom-0 left-0 right-0 h-48 bg-gradient-to-t from-[#0A0A0A] via-[#0A0A0A]/80 to-transparent pointer-events-none flex items-end justify-center pb-8 z-10">
            <button className="pointer-events-auto px-6 py-3 rounded-xl text-sm font-semibold bg-white/[0.08] border border-white/10 hover:bg-white/[0.12] text-white transition shadow-2xl backdrop-blur-xl flex items-center gap-2">
                Scroll all 1,240 events
            </button>
        </div>
      </div>
    </div>
  );
}
