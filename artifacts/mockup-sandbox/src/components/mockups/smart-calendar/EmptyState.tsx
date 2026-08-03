import React from 'react';
import { Calendar, Sparkles, Search, X, Tag, MapPin, CalendarDays, Zap } from 'lucide-react';

export function EmptyState() {
  return (
    <div className="min-h-screen bg-[#0A0A0A] text-white p-6 font-sans">
      <div className="max-w-6xl mx-auto space-y-6">
        {/* Header */}
        <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div className="flex items-center gap-3">
                    <h1 className="text-2xl font-bold text-white">MENA Tech Scene</h1>
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

        {/* Active Rules */}
        <div className="bg-white/[0.03] border border-white/10 backdrop-blur-xl rounded-2xl p-5">
            <h2 className="text-[11px] uppercase tracking-widest text-white/30 mb-3 font-semibold">Active Rules</h2>
            <div className="flex flex-wrap items-center gap-2">
                <div className="inline-flex items-center gap-2 text-xs px-3 py-1.5 rounded-full border border-blue-400/60 bg-blue-500/15 text-white">
                    <CalendarDays className="w-3.5 h-3.5 text-blue-400" />
                    Next 30 Days
                </div>
                <div className="inline-flex items-center gap-2 text-xs px-3 py-1.5 rounded-full border border-blue-400/60 bg-blue-500/15 text-white">
                    <Tag className="w-3.5 h-3.5 text-blue-400" />
                    #startups <span className="text-blue-300/50 mx-0.5">OR</span> #networking
                </div>
                <div className="inline-flex items-center gap-2 text-xs px-3 py-1.5 rounded-full border border-blue-400/60 bg-blue-500/15 text-white">
                    <MapPin className="w-3.5 h-3.5 text-blue-400" />
                    Dubai
                </div>
            </div>
        </div>

        {/* Empty State */}
        <div className="bg-white/[0.02] border border-white/5 backdrop-blur-md rounded-2xl p-16 text-center mt-8 relative overflow-hidden">
            {/* Subtle background glow */}
            <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-64 h-64 bg-blue-500/5 rounded-full blur-3xl pointer-events-none"></div>
            
            <div className="w-20 h-20 rounded-full bg-blue-500/10 border border-blue-500/20 flex items-center justify-center mx-auto mb-6 relative z-10">
                <Calendar className="w-8 h-8 text-blue-400" />
                <div className="absolute -top-1 -right-1 w-7 h-7 rounded-full bg-[#0e121a] flex items-center justify-center border border-white/5">
                    <Sparkles className="w-4 h-4 text-blue-400" />
                </div>
            </div>
            
            <div className="relative z-10">
                <h3 className="text-xl font-semibold text-white/90 mb-3">No matching events found</h3>
                <p className="text-sm text-white/50 max-w-md mx-auto leading-relaxed">
                    Your smart rules are active, but there are no matching events in the calendars you currently <strong className="text-white/70">own</strong> or <strong className="text-white/70">follow</strong>. Events will appear here automatically when they match.
                </p>
                
                <div className="flex flex-wrap items-center justify-center gap-3 mt-8">
                    <button className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-medium border border-white/10 text-white/70 hover:text-white hover:border-white/30 hover:bg-white/[0.03] transition">
                        <X className="w-4 h-4" /> Loosen Rules
                    </button>
                    <button className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-500 text-white transition shadow-[0_0_20px_rgba(61,107,255,0.3)]">
                        <Search className="w-4 h-4" /> Browse Calendars to Follow
                    </button>
                </div>
            </div>
            
            <div className="mt-16 pt-10 border-t border-white/[0.06] text-left max-w-lg mx-auto relative z-10">
                <p className="text-[11px] uppercase tracking-widest text-white/30 mb-4 text-center font-semibold">Suggested Calendars to Follow</p>
                <div className="space-y-3">
                    {/* Calendar suggestion 1 */}
                    <div className="flex items-center justify-between p-3.5 rounded-xl bg-white/[0.03] border border-white/[0.05] hover:border-white/10 transition group cursor-pointer">
                        <div className="flex items-center gap-3.5">
                            <span className="w-3.5 h-3.5 rounded-full flex-shrink-0" style={{ background: '#3d6bff' }}></span>
                            <div>
                                <p className="text-sm font-medium text-white/90 group-hover:text-white transition">Dubai Tech Meetups</p>
                                <p className="text-xs text-white/40 mt-0.5">14 upcoming events · <span className="text-blue-400/80">3 match your rules</span></p>
                            </div>
                        </div>
                        <button className="px-3.5 py-1.5 rounded-lg text-xs font-medium border border-white/10 text-white/70 hover:text-white hover:bg-white/5 transition">
                            Follow
                        </button>
                    </div>
                    {/* Calendar suggestion 2 */}
                    <div className="flex items-center justify-between p-3.5 rounded-xl bg-white/[0.03] border border-white/[0.05] hover:border-white/10 transition group cursor-pointer">
                        <div className="flex items-center gap-3.5">
                            <span className="w-3.5 h-3.5 rounded-full flex-shrink-0" style={{ background: '#10b981' }}></span>
                            <div>
                                <p className="text-sm font-medium text-white/90 group-hover:text-white transition">Startup Grind MENA</p>
                                <p className="text-xs text-white/40 mt-0.5">8 upcoming events · <span className="text-blue-400/80">1 matches your rules</span></p>
                            </div>
                        </div>
                        <button className="px-3.5 py-1.5 rounded-lg text-xs font-medium border border-white/10 text-white/70 hover:text-white hover:bg-white/5 transition">
                            Follow
                        </button>
                    </div>
                </div>
            </div>
        </div>
      </div>
    </div>
  );
}
