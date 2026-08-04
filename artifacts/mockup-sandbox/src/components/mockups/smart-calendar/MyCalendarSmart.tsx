import React, { useState } from 'react';
import { 
  Rss, RefreshCw, Download, Plus, ChevronDown, ChevronLeft, ChevronRight, 
  X, Clock, MapPin, Ticket, Sparkles, Settings2, Copy, Check 
} from 'lucide-react';

const convertHexToRgba = (hex: string, alpha: number) => {
  const r = parseInt(hex.slice(1, 3), 16);
  const g = parseInt(hex.slice(3, 5), 16);
  const b = parseInt(hex.slice(5, 7), 16);
  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
};

const EVENTS = [
  {
    id: 1,
    title: 'AI in FinTech Panel',
    time: '6:00 PM – 8:00 PM',
    location: 'DIFC, Dubai',
    calendarName: 'Dubai Tech Meetups',
    calendarAccent: '#3d6bff',
    tags: ['ai', 'startups', 'networking'],
    isToday: true,
    month: 'OCT',
    day: '24'
  },
  {
    id: 2,
    title: 'Founder Mix & Mingle',
    time: '8:30 PM – 10:00 PM',
    location: 'Nightjar Coffee, Dubai',
    calendarName: 'Founders Coffee',
    calendarAccent: '#f59e0b',
    tags: ['networking', 'startups'],
    isToday: true,
    month: 'OCT',
    day: '24'
  },
  {
    id: 3,
    title: 'Startup Grind: Scaling in MENA',
    time: '5:00 PM – 7:00 PM',
    location: 'In5 Tech, Dubai',
    calendarName: 'Startup Grind MENA',
    calendarAccent: '#a855f7',
    tags: ['startups'],
    isToday: false,
    month: 'OCT',
    day: '25',
    tickets: true
  },
  {
    id: 4,
    title: 'Pitch Night: Seed Stage',
    time: '7:00 PM – 9:00 PM',
    location: 'Area 2071, Dubai',
    calendarName: 'Dubai Tech Meetups',
    calendarAccent: '#3d6bff',
    tags: ['startups', 'pitch'],
    isToday: false,
    month: 'NOV',
    day: '02'
  }
];

const GROUPED_EVENTS = [
  {
    dateLabel: 'Today · October 24, 2023',
    isToday: true,
    events: [EVENTS[0], EVENTS[1]]
  },
  {
    dateLabel: 'Wednesday · October 25, 2023',
    isToday: false,
    events: [EVENTS[2]]
  },
  {
    dateLabel: 'Thursday · November 2, 2023',
    isToday: false,
    events: [EVENTS[3]]
  }
];

export function MyCalendarSmart() {
  const [subOpen, setSubOpen] = useState(false);
  const [syncOpen, setSyncOpen] = useState(false);
  const [exportOpen, setExportOpen] = useState(false);
  
  return (
    <div className="min-h-screen bg-[#0a0a0c] text-white p-4 md:p-8 font-sans antialiased selection:bg-blue-500/30">
      <div className="max-w-6xl mx-auto space-y-6">
        {/* Header */}
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold text-white">My Calendar</h1>
            <p className="text-xs text-white/40 mt-0.5">Everything from the calendars you own and follow, in one place.</p>
          </div>
          <div className="flex items-center gap-2">
             <div className="relative">
                <button 
                  onClick={() => { setSubOpen(!subOpen); setSyncOpen(false); setExportOpen(false); }}
                  className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium border border-white/10 text-white/70 hover:text-white hover:border-white/30 transition">
                  <Rss className="w-3.5 h-3.5" /> Subscribe
                  <ChevronDown className={`w-3 h-3 opacity-60 transition-transform ${subOpen ? 'rotate-180' : ''}`} />
                </button>
             </div>
             <div className="relative">
                <button 
                  onClick={() => { setSyncOpen(!syncOpen); setSubOpen(false); setExportOpen(false); }}
                  className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium border border-white/10 text-white/70 hover:text-white hover:border-white/30 transition">
                  <RefreshCw className="w-3.5 h-3.5" /> Sync
                  <ChevronDown className={`w-3 h-3 opacity-60 transition-transform ${syncOpen ? 'rotate-180' : ''}`} />
                </button>
             </div>
             <div className="relative">
                <button 
                  onClick={() => { setExportOpen(!exportOpen); setSubOpen(false); setSyncOpen(false); }}
                  className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium border border-white/10 text-white/70 hover:text-white hover:border-white/30 transition">
                  <Download className="w-3.5 h-3.5" /> Export
                  <ChevronDown className={`w-3 h-3 opacity-60 transition-transform ${exportOpen ? 'rotate-180' : ''}`} />
                </button>
             </div>
             <button className="px-4 py-2 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-500 text-white transition inline-flex items-center">
                 <Plus className="w-3.5 h-3.5 mr-1" /> New calendar
             </button>
          </div>
        </div>

        {/* View Switcher + Date */}
        <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="inline-flex p-1 rounded-xl bg-white/[0.03] border border-white/10 backdrop-blur-md">
                {['Month', 'Week', 'Day', 'Agenda'].map((view) => (
                  <button 
                    key={view}
                    className={`px-3.5 py-1.5 rounded-lg text-sm font-medium transition ${view === 'Agenda' ? 'bg-blue-600 text-white shadow-sm' : 'text-white/60 hover:text-white'}`}
                  >
                    {view}
                  </button>
                ))}
            </div>

            <div className="flex items-center gap-2">
                <button className="px-3 py-1.5 rounded-lg text-xs font-medium border border-white/10 text-white/60 hover:text-white hover:border-white/30 transition">Today</button>
                <button className="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-white/10 text-white/60 hover:text-white hover:border-white/30 transition"><ChevronLeft className="w-3.5 h-3.5" /></button>
                <span className="text-sm font-semibold text-white min-w-[10rem] text-center">Upcoming</span>
                <button className="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-white/10 text-white/60 hover:text-white hover:border-white/30 transition"><ChevronRight className="w-3.5 h-3.5" /></button>
            </div>
        </div>

        {/* Filters */}
        <div className="bg-white/[0.03] backdrop-blur-md border border-white/10 rounded-2xl p-5">
            <div className="grid grid-cols-1 md:grid-cols-6 gap-3 items-center">
                <div className="md:col-span-2">
                    <input type="text" defaultValue="" placeholder="Search events…" className="w-full h-10 border border-white/10 rounded-xl px-3 py-0 text-sm leading-none focus:ring-2 focus:ring-blue-500/40 bg-black/30 text-white placeholder-white/30 outline-none" />
                </div>
                <div>
                    <select className="w-full h-10 border border-white/10 rounded-xl px-3 py-0 text-sm leading-none focus:ring-2 focus:ring-blue-500/40 bg-black/30 text-white outline-none appearance-none">
                        <option value="all">All sources</option>
                        <option value="owned">Owned by me</option>
                        <option value="followed">Following</option>
                    </select>
                </div>
                <div>
                    <select defaultValue="radar" className="w-full h-10 border border-white/10 rounded-xl px-3 py-0 text-sm leading-none focus:ring-2 focus:ring-blue-500/40 bg-black/30 text-white outline-none appearance-none">
                        <option value="">All calendars</option>
                        <option value="radar">Startup Radar (Smart)</option>
                    </select>
                </div>
                <div>
                    <input type="date" className="w-full h-10 border border-white/10 rounded-xl px-3 py-0 text-sm leading-none focus:ring-2 focus:ring-blue-500/40 bg-black/30 text-white outline-none" style={{ colorScheme: 'dark' }} />
                </div>
                <div>
                    <input type="date" className="w-full h-10 border border-white/10 rounded-xl px-3 py-0 text-sm leading-none focus:ring-2 focus:ring-blue-500/40 bg-black/30 text-white outline-none" style={{ colorScheme: 'dark' }} />
                </div>
            </div>
            <div className="flex flex-wrap items-center gap-3 mt-3">
                <span className="text-[11px] px-2 py-1 rounded-full bg-blue-500/10 text-blue-300">#startups</span>
                <label className="flex items-center gap-2 text-xs text-white/50 cursor-pointer">
                    <input type="checkbox" className="rounded text-blue-500 bg-black/30 border border-white/20" /> Include past events
                </label>
                <div className="ml-auto flex gap-2">
                    <button className="px-4 py-2 rounded-xl text-sm text-white/60 hover:text-white transition">Reset</button>
                    <button className="px-5 py-2 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-500 text-white transition">Apply</button>
                </div>
            </div>
        </div>

        {/* Visual Chips */}
        <div className="space-y-3">
            <div className="flex flex-wrap items-center gap-2">
                <span className="text-[11px] uppercase tracking-wide text-white/30 mr-1">Calendars</span>
                
                <button className="inline-flex items-center gap-2 text-xs px-3 py-1.5 rounded-full border transition border-blue-400/60 bg-blue-500/15 text-white">
                    <Sparkles className="w-3 h-3 text-blue-400" />
                    <span className="font-medium">Startup Radar</span>
                    <span className="text-white/40">24 · smart</span>
                    <X className="w-3 h-3 text-white/50 hover:text-white" />
                </button>

                <button className="inline-flex items-center gap-2 text-xs px-3 py-1.5 rounded-full border transition border-white/10 text-white/60 hover:text-white hover:border-white/30">
                    <span className="w-2.5 h-2.5 rounded-full flex-shrink-0 bg-[#3d6bff]"></span>
                    Dubai Tech Meetups
                    <span className="text-white/30">12 · owned</span>
                </button>
                
                <button className="inline-flex items-center gap-2 text-xs px-3 py-1.5 rounded-full border transition border-white/10 text-white/60 hover:text-white hover:border-white/30">
                    <span className="w-2.5 h-2.5 rounded-full flex-shrink-0 bg-[#a855f7]"></span>
                    Startup Grind MENA
                    <span className="text-white/30">8 · following</span>
                </button>

                <button className="inline-flex items-center gap-2 text-xs px-3 py-1.5 rounded-full border transition border-white/10 text-white/60 hover:text-white hover:border-white/30">
                    <span className="w-2.5 h-2.5 rounded-full flex-shrink-0 bg-[#f59e0b]"></span>
                    Founders Coffee
                    <span className="text-white/30">4 · following</span>
                </button>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <span className="text-[11px] uppercase tracking-wide text-white/30 mr-1">Tags</span>
                <button className="text-[11px] px-2.5 py-1 rounded-full border transition border-blue-400/60 bg-blue-500/20 text-white inline-flex items-center gap-1">
                    #startups <X className="w-2.5 h-2.5 opacity-70" />
                </button>
                <button className="text-[11px] px-2.5 py-1 rounded-full border transition border-white/10 bg-blue-500/5 text-blue-300 hover:bg-blue-500/15">
                    #networking
                </button>
                <button className="text-[11px] px-2.5 py-1 rounded-full border transition border-white/10 bg-blue-500/5 text-blue-300 hover:bg-blue-500/15">
                    #ai
                </button>
                <button className="text-[11px] px-2.5 py-1 rounded-full border transition border-white/10 bg-blue-500/5 text-blue-300 hover:bg-blue-500/15">
                    #design
                </button>
            </div>
        </div>

        {/* Agenda View */}
        <div className="space-y-1.5">
            {/* Smart Rules Strip */}
            <div className="bg-[#3d6bff]/[0.04] border border-[#3d6bff]/20 rounded-2xl p-3 flex items-center justify-between gap-4 mb-3">
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                    <Sparkles className="w-4 h-4 text-[#3d6bff] shrink-0" />
                    <span className="text-white/70">Auto-includes:</span>
                    <span className="text-blue-300 font-medium">#startups</span>
                    <span className="text-white/30">·</span>
                    <span className="text-blue-300 font-medium">#networking</span>
                    <span className="text-white/30">·</span>
                    <span className="text-blue-300 font-medium">Dubai</span>
                    <span className="text-white/30">·</span>
                    <span className="text-blue-300 font-medium">next 90 days</span>
                    <span className="text-white/40 text-xs ml-1">(from owned & followed calendars)</span>
                </div>
                <button className="flex-shrink-0 flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-medium border border-[#3d6bff]/30 text-blue-300 hover:bg-[#3d6bff]/10 transition">
                    <Settings2 className="w-3.5 h-3.5" />
                    Edit rules
                </button>
            </div>

            {/* Events */}
            {GROUPED_EVENTS.map((group, i) => (
                <React.Fragment key={i}>
                    <div className="flex items-center gap-3 pt-3 first:pt-0 pb-1 px-1">
                        <span className={`text-[11px] font-semibold uppercase tracking-widest ${group.isToday ? 'text-blue-400' : 'text-white/40'}`}>
                            {group.dateLabel}
                        </span>
                        <div className="flex-1 h-px bg-white/[0.06]"></div>
                    </div>

                    {group.events.map((ev) => {
                       return (
                        <div key={ev.id} className="group bg-white/[0.03] backdrop-blur-sm border border-white/10 rounded-2xl p-4 flex items-start gap-4 transition hover:bg-white/[0.05]">
                            <div className="flex flex-col items-center justify-center w-12 flex-shrink-0 rounded-xl py-2.5 gap-0.5"
                                 style={{ 
                                   backgroundColor: convertHexToRgba(ev.calendarAccent, 0.1), 
                                   borderColor: convertHexToRgba(ev.calendarAccent, 0.2),
                                   borderWidth: '1px'
                                 }}>
                                <span className="text-[9px] uppercase tracking-wider font-semibold" style={{ color: convertHexToRgba(ev.calendarAccent, 0.8) }}>{ev.month}</span>
                                <span className="text-[22px] font-extrabold leading-none" style={{ color: ev.calendarAccent }}>{ev.day}</span>
                            </div>
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-2 flex-wrap">
                                    <h3 className="font-semibold text-white/90 group-hover:text-white transition truncate">{ev.title}</h3>
                                    {ev.isToday && (
                                        <span className="text-[10px] px-2 py-0.5 rounded-full bg-blue-500/15 text-blue-300 font-medium">Today</span>
                                    )}
                                </div>
                                <div className="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-1.5">
                                    <span className="text-xs text-white/45 flex items-center gap-1">
                                        <Clock className="w-3 h-3 opacity-70" />
                                        {ev.time}
                                    </span>
                                    <span className="text-xs flex items-center gap-1.5 text-white/35">
                                        <span className="w-2 h-2 rounded-full flex-shrink-0" style={{ backgroundColor: ev.calendarAccent }}></span>
                                        {ev.calendarName}
                                    </span>
                                    <span className="text-xs text-white/40 flex items-center gap-1">
                                        <MapPin className="w-3 h-3 opacity-70" />
                                        {ev.location}
                                    </span>
                                </div>
                                <div className="flex flex-wrap gap-1 mt-2">
                                    {ev.tags.map(t => (
                                        <button key={t} className="text-[11px] px-2 py-0.5 rounded-full bg-blue-500/10 text-blue-300/80 hover:bg-blue-500/20 hover:text-blue-300 transition">
                                            #{t}
                                        </button>
                                    ))}
                                </div>
                            </div>
                            {ev.tickets && (
                                <button className="flex-shrink-0 px-3 py-1.5 rounded-lg text-xs font-medium border border-white/10 text-white/60 hover:text-white hover:border-white/30 transition flex items-center gap-1.5">
                                    <Ticket className="w-3.5 h-3.5" /> Tickets
                                </button>
                            )}
                        </div>
                       );
                    })}
                </React.Fragment>
            ))}
        </div>
      </div>
    </div>
  );
}
