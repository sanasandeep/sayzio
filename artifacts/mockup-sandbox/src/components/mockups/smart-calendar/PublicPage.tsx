import React, { useState } from 'react';
import { 
  Zap, 
  Calendar as CalendarIcon, 
  Clock, 
  MapPin, 
  Ticket, 
  Rss, 
  Plus, 
  Hash,
  ChevronDown,
  Check,
  Share,
  Info,
  Filter
} from 'lucide-react';

const EVENTS = [
  {
    id: 1,
    title: "Middle East AI & Data Summit",
    month: "Nov",
    day: "15",
    time: "9:00 AM – 5:00 PM",
    calendar: "Dubai Tech Meetups",
    calendarAccent: "#3d6bff",
    location: "Dubai World Trade Centre",
    tags: ["ai", "networking", "startups"],
    hasTickets: true,
    isToday: true,
  },
  {
    id: 2,
    title: "Founders Breakfast: Scaling SaaS",
    month: "Nov",
    day: "18",
    time: "8:30 AM – 10:30 AM",
    calendar: "Founders Coffee",
    calendarAccent: "#eab308",
    location: "DIFC, Dubai",
    tags: ["startups", "networking"],
    hasTickets: false,
    isToday: false,
  },
  {
    id: 3,
    title: "Design Systems in Fintech",
    month: "Nov",
    day: "19",
    time: "4:00 PM – 5:30 PM",
    calendar: "Dubai Tech Meetups",
    calendarAccent: "#3d6bff",
    location: "Online",
    tags: ["design", "networking"],
    hasTickets: false,
    isToday: false,
  },
  {
    id: 4,
    title: "Startup Grind MENA Pitch Night",
    month: "Nov",
    day: "20",
    time: "6:00 PM – 9:00 PM",
    calendar: "Startup Grind MENA",
    calendarAccent: "#ef4444",
    location: "in5 Tech, Dubai",
    tags: ["startups", "design"],
    hasTickets: true,
    isToday: false,
  }
];

export function PublicPage() {
  const [following, setFollowing] = useState(false);
  const [showSubscribe, setShowSubscribe] = useState(false);
  const [copied, setCopied] = useState(false);

  const handleCopy = () => {
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  return (
    <div className="min-h-[100dvh] bg-[#0c0d12] text-white font-sans selection:bg-[#3d6bff]/30 selection:text-white">
      {/* Navbar Minimal */}
      <nav className="border-b border-white/[0.06] bg-[#0c0d12]/80 backdrop-blur-xl sticky top-0 z-40">
        <div className="max-w-4xl mx-auto px-4 h-14 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <div className="w-6 h-6 rounded-md bg-[#3d6bff] flex items-center justify-center">
              <span className="text-white text-xs font-bold leading-none">1</span>
            </div>
            <span className="text-sm font-semibold tracking-wide text-white/90">1INME</span>
          </div>
          <div className="flex items-center gap-4">
            <a href="#" className="text-xs font-medium text-white/50 hover:text-white transition">Sign in</a>
            <a href="#" className="text-xs font-semibold bg-white text-black px-3 py-1.5 rounded-lg hover:bg-white/90 transition">Create your own</a>
          </div>
        </div>
      </nav>

      <main className="max-w-3xl mx-auto px-4 py-12 md:py-16">
        
        {/* Header Section */}
        <div className="space-y-6 mb-12">
          
          <div className="flex items-start justify-between gap-6 flex-wrap md:flex-nowrap">
            <div className="space-y-3 flex-1 min-w-[280px]">
              <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-[#3d6bff]/15 border border-[#3d6bff]/30 text-[#3d6bff] text-[11px] font-bold tracking-widest uppercase">
                <Zap className="w-3 h-3 fill-current" />
                <span>Smart Calendar</span>
              </div>
              
              <h1 className="text-3xl md:text-4xl font-extrabold tracking-tight text-white leading-tight">
                Dubai Tech &amp; Startups
              </h1>
              
              <p className="text-white/50 text-sm md:text-base leading-relaxed max-w-xl">
                A continuously updating schedule of the best technology and startup events in Dubai. Curated automatically from top community sources.
              </p>
              
              <div className="flex items-center gap-2 text-sm text-white/60 pt-1">
                <img 
                  src="https://api.dicebear.com/9.x/notionists/svg?seed=Alex&backgroundColor=3d6bff" 
                  alt="Avatar" 
                  className="w-6 h-6 rounded-full border border-white/10 bg-black"
                />
                <span>Maintained by <strong className="text-white/90 font-medium">Alex Curates</strong></span>
              </div>
            </div>

            <div className="flex flex-col gap-3 w-full md:w-auto shrink-0 mt-2 md:mt-0">
              <button 
                onClick={() => setFollowing(!following)}
                className={`flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl text-sm font-semibold transition ${
                  following 
                    ? 'bg-white/10 text-white hover:bg-white/15 border border-white/10' 
                    : 'bg-[#3d6bff] hover:bg-[#3d6bff]/90 text-white'
                }`}
              >
                {following ? (
                  <>
                    <Check className="w-4 h-4" /> Following
                  </>
                ) : (
                  <>
                    <Plus className="w-4 h-4" /> Follow in 1INME
                  </>
                )}
              </button>

              <div className="relative">
                <button 
                  onClick={() => setShowSubscribe(!showSubscribe)}
                  className="w-full flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl text-sm font-medium border border-white/10 text-white/70 hover:text-white hover:border-white/30 transition bg-white/[0.03]"
                >
                  <Rss className="w-4 h-4" /> Subscribe (ICS)
                  <ChevronDown className={`w-3 h-3 opacity-60 transition-transform ${showSubscribe ? 'rotate-180' : ''}`} />
                </button>
                
                {showSubscribe && (
                  <div className="absolute right-0 md:left-0 md:right-auto mt-2 w-80 p-4 rounded-2xl bg-[#141223]/95 backdrop-blur-xl border border-white/10 shadow-2xl z-20">
                    <p className="text-sm font-semibold text-white">Subscribe to calendar</p>
                    <p className="text-xs text-white/50 mt-1 mb-4 leading-relaxed">
                      Add this to Apple Calendar, Google Calendar, or Outlook to automatically sync these events.
                    </p>
                    <div className="flex gap-2">
                      <input 
                        type="text" 
                        readOnly 
                        value="https://1in.me/c/dubai-tech.ics"
                        className="flex-1 bg-black/40 border border-white/10 rounded-xl px-3 text-xs text-white/80 focus:outline-none focus:ring-2 focus:ring-[#3d6bff]/50"
                      />
                      <button 
                        onClick={handleCopy}
                        className="shrink-0 px-3 py-2 rounded-xl text-xs font-semibold bg-white/10 hover:bg-white/20 transition flex items-center gap-1.5"
                      >
                        {copied ? <Check className="w-3.5 h-3.5 text-green-400" /> : 'Copy'}
                      </button>
                    </div>
                  </div>
                )}
              </div>
            </div>
          </div>
          
          {/* Rules Summary Box */}
          <div className="bg-white/[0.03] border border-white/10 rounded-2xl p-4 md:p-5 relative overflow-hidden group">
            {/* Subtle highlight effect */}
            <div className="absolute top-0 right-0 w-64 h-64 bg-[#3d6bff]/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2 pointer-events-none" />
            
            <div className="flex items-start gap-4">
              <div className="mt-0.5 shrink-0 w-8 h-8 rounded-full bg-[#3d6bff]/10 flex items-center justify-center border border-[#3d6bff]/20">
                <Filter className="w-4 h-4 text-[#3d6bff]" />
              </div>
              <div>
                <h3 className="text-xs font-bold uppercase tracking-widest text-white/40 mb-2">How it works</h3>
                <p className="text-sm text-white/80 leading-relaxed max-w-2xl">
                  This calendar automatically includes events matching <strong className="text-white font-medium">#startups</strong>, <strong className="text-white font-medium">#networking</strong>, <strong className="text-white font-medium">#ai</strong>, or <strong className="text-white font-medium">#design</strong> located in <strong className="text-white font-medium">Dubai</strong> or <strong className="text-white font-medium">Online</strong> over the next 90 days.
                </p>
                <div className="mt-3 flex items-center gap-1.5 text-xs text-white/40">
                  <Info className="w-3.5 h-3.5" />
                  <span>Sourced safely from calendars Alex owns or follows. Always up to date.</span>
                </div>
              </div>
            </div>
          </div>

        </div>

        {/* Agenda View */}
        <div className="space-y-8">
          
          {/* Group 1 */}
          <div className="space-y-2">
            <div className="flex items-center gap-3 pt-2 pb-1 px-1">
              <span className="text-[11px] font-semibold uppercase tracking-widest text-[#3d6bff]">
                Today &middot; November 15, 2023
              </span>
              <div className="flex-1 h-px bg-white/[0.06]"></div>
            </div>
            
            {/* Event Card */}
            {EVENTS.filter(e => e.isToday).map(event => (
              <div key={event.id} className="group bg-white/[0.03] border border-white/[0.05] rounded-2xl p-4 flex items-start gap-4 transition hover:bg-white/[0.06] hover:border-white/[0.08]">
                
                {/* Date Badge */}
                <div className="flex flex-col items-center justify-center w-14 flex-shrink-0 rounded-xl py-2.5 gap-0.5 bg-[#3d6bff]/10 border border-[#3d6bff]/20">
                  <span className="text-[9px] uppercase tracking-wider font-bold text-[#3d6bff]/80">
                    {event.month}
                  </span>
                  <span className="text-2xl font-extrabold leading-none text-[#3d6bff] drop-shadow-sm">
                    {event.day}
                  </span>
                </div>
                
                {/* Content */}
                <div className="min-w-0 flex-1">
                  <div className="flex items-center gap-2 flex-wrap">
                    <h3 className="text-base font-semibold text-white/90 group-hover:text-white transition truncate">
                      {event.title}
                    </h3>
                    <span className="text-[10px] px-2 py-0.5 rounded-full bg-[#3d6bff]/15 text-[#3d6bff] border border-[#3d6bff]/20 font-medium">
                      Today
                    </span>
                  </div>
                  
                  <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5 mt-2">
                    <span className="text-xs text-white/45 flex items-center gap-1.5">
                      <Clock className="w-3.5 h-3.5 opacity-70" />
                      {event.time}
                    </span>
                    <span className="text-xs flex items-center gap-1.5 text-white/45">
                      <span className="w-2 h-2 rounded-full flex-shrink-0" style={{ backgroundColor: event.calendarAccent }}></span>
                      {event.calendar}
                    </span>
                    <span className="text-xs text-white/45 flex items-center gap-1.5">
                      <MapPin className="w-3.5 h-3.5 opacity-70" />
                      {event.location}
                    </span>
                  </div>
                  
                  <div className="flex flex-wrap gap-1.5 mt-3">
                    {event.tags.map(tag => (
                      <span key={tag} className="text-[10px] px-2.5 py-1 rounded-full bg-[#3d6bff]/10 text-[#3d6bff]/80 border border-[#3d6bff]/10 font-medium">
                        #{tag}
                      </span>
                    ))}
                  </div>
                </div>

                {event.hasTickets && (
                  <a href="#" className="flex-shrink-0 px-3 py-1.5 rounded-xl text-xs font-semibold bg-white/5 border border-white/10 text-white/70 hover:text-white hover:bg-white/10 transition flex items-center gap-1.5 mt-1 md:mt-0">
                    <Ticket className="w-3.5 h-3.5" /> <span className="hidden md:inline">Tickets</span>
                  </a>
                )}
              </div>
            ))}
          </div>

          {/* Group 2 */}
          <div className="space-y-2">
            <div className="flex items-center gap-3 pt-6 pb-1 px-1">
              <span className="text-[11px] font-semibold uppercase tracking-widest text-white/40">
                Saturday, November 18, 2023
              </span>
              <div className="flex-1 h-px bg-white/[0.06]"></div>
            </div>
            
            {/* Event Card */}
            {EVENTS.filter(e => e.day === "18").map(event => (
              <div key={event.id} className="group bg-white/[0.03] border border-white/[0.05] rounded-2xl p-4 flex items-start gap-4 transition hover:bg-white/[0.06] hover:border-white/[0.08]">
                
                <div className="flex flex-col items-center justify-center w-14 flex-shrink-0 rounded-xl py-2.5 gap-0.5 bg-[#eab308]/10 border border-[#eab308]/20">
                  <span className="text-[9px] uppercase tracking-wider font-bold text-[#eab308]/80">
                    {event.month}
                  </span>
                  <span className="text-2xl font-extrabold leading-none text-[#eab308] drop-shadow-sm">
                    {event.day}
                  </span>
                </div>
                
                <div className="min-w-0 flex-1">
                  <div className="flex items-center gap-2 flex-wrap">
                    <h3 className="text-base font-semibold text-white/90 group-hover:text-white transition truncate">
                      {event.title}
                    </h3>
                  </div>
                  
                  <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5 mt-2">
                    <span className="text-xs text-white/45 flex items-center gap-1.5">
                      <Clock className="w-3.5 h-3.5 opacity-70" />
                      {event.time}
                    </span>
                    <span className="text-xs flex items-center gap-1.5 text-white/45">
                      <span className="w-2 h-2 rounded-full flex-shrink-0" style={{ backgroundColor: event.calendarAccent }}></span>
                      {event.calendar}
                    </span>
                    <span className="text-xs text-white/45 flex items-center gap-1.5">
                      <MapPin className="w-3.5 h-3.5 opacity-70" />
                      {event.location}
                    </span>
                  </div>
                  
                  <div className="flex flex-wrap gap-1.5 mt-3">
                    {event.tags.map(tag => (
                      <span key={tag} className="text-[10px] px-2.5 py-1 rounded-full bg-[#3d6bff]/10 text-[#3d6bff]/80 border border-[#3d6bff]/10 font-medium">
                        #{tag}
                      </span>
                    ))}
                  </div>
                </div>
              </div>
            ))}
          </div>

          {/* Group 3 */}
          <div className="space-y-2">
            <div className="flex items-center gap-3 pt-6 pb-1 px-1">
              <span className="text-[11px] font-semibold uppercase tracking-widest text-white/40">
                Sunday, November 19, 2023
              </span>
              <div className="flex-1 h-px bg-white/[0.06]"></div>
            </div>
            
            {/* Event Card */}
            {EVENTS.filter(e => e.day === "19").map(event => (
              <div key={event.id} className="group bg-white/[0.03] border border-white/[0.05] rounded-2xl p-4 flex items-start gap-4 transition hover:bg-white/[0.06] hover:border-white/[0.08]">
                
                <div className="flex flex-col items-center justify-center w-14 flex-shrink-0 rounded-xl py-2.5 gap-0.5 bg-[#3d6bff]/10 border border-[#3d6bff]/20">
                  <span className="text-[9px] uppercase tracking-wider font-bold text-[#3d6bff]/80">
                    {event.month}
                  </span>
                  <span className="text-2xl font-extrabold leading-none text-[#3d6bff] drop-shadow-sm">
                    {event.day}
                  </span>
                </div>
                
                <div className="min-w-0 flex-1">
                  <div className="flex items-center gap-2 flex-wrap">
                    <h3 className="text-base font-semibold text-white/90 group-hover:text-white transition truncate">
                      {event.title}
                    </h3>
                  </div>
                  
                  <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5 mt-2">
                    <span className="text-xs text-white/45 flex items-center gap-1.5">
                      <Clock className="w-3.5 h-3.5 opacity-70" />
                      {event.time}
                    </span>
                    <span className="text-xs flex items-center gap-1.5 text-white/45">
                      <span className="w-2 h-2 rounded-full flex-shrink-0" style={{ backgroundColor: event.calendarAccent }}></span>
                      {event.calendar}
                    </span>
                    <span className="text-xs text-white/45 flex items-center gap-1.5">
                      <MapPin className="w-3.5 h-3.5 opacity-70" />
                      {event.location}
                    </span>
                  </div>
                  
                  <div className="flex flex-wrap gap-1.5 mt-3">
                    {event.tags.map(tag => (
                      <span key={tag} className="text-[10px] px-2.5 py-1 rounded-full bg-[#3d6bff]/10 text-[#3d6bff]/80 border border-[#3d6bff]/10 font-medium">
                        #{tag}
                      </span>
                    ))}
                  </div>
                </div>
              </div>
            ))}
          </div>

          {/* Group 4 */}
          <div className="space-y-2">
            <div className="flex items-center gap-3 pt-6 pb-1 px-1">
              <span className="text-[11px] font-semibold uppercase tracking-widest text-white/40">
                Monday, November 20, 2023
              </span>
              <div className="flex-1 h-px bg-white/[0.06]"></div>
            </div>
            
            {/* Event Card */}
            {EVENTS.filter(e => e.day === "20").map(event => (
              <div key={event.id} className="group bg-white/[0.03] border border-white/[0.05] rounded-2xl p-4 flex items-start gap-4 transition hover:bg-white/[0.06] hover:border-white/[0.08]">
                
                <div className="flex flex-col items-center justify-center w-14 flex-shrink-0 rounded-xl py-2.5 gap-0.5 bg-[#ef4444]/10 border border-[#ef4444]/20">
                  <span className="text-[9px] uppercase tracking-wider font-bold text-[#ef4444]/80">
                    {event.month}
                  </span>
                  <span className="text-2xl font-extrabold leading-none text-[#ef4444] drop-shadow-sm">
                    {event.day}
                  </span>
                </div>
                
                <div className="min-w-0 flex-1">
                  <div className="flex items-center gap-2 flex-wrap">
                    <h3 className="text-base font-semibold text-white/90 group-hover:text-white transition truncate">
                      {event.title}
                    </h3>
                  </div>
                  
                  <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5 mt-2">
                    <span className="text-xs text-white/45 flex items-center gap-1.5">
                      <Clock className="w-3.5 h-3.5 opacity-70" />
                      {event.time}
                    </span>
                    <span className="text-xs flex items-center gap-1.5 text-white/45">
                      <span className="w-2 h-2 rounded-full flex-shrink-0" style={{ backgroundColor: event.calendarAccent }}></span>
                      {event.calendar}
                    </span>
                    <span className="text-xs text-white/45 flex items-center gap-1.5">
                      <MapPin className="w-3.5 h-3.5 opacity-70" />
                      {event.location}
                    </span>
                  </div>
                  
                  <div className="flex flex-wrap gap-1.5 mt-3">
                    {event.tags.map(tag => (
                      <span key={tag} className="text-[10px] px-2.5 py-1 rounded-full bg-[#3d6bff]/10 text-[#3d6bff]/80 border border-[#3d6bff]/10 font-medium">
                        #{tag}
                      </span>
                    ))}
                  </div>
                </div>

                {event.hasTickets && (
                  <a href="#" className="flex-shrink-0 px-3 py-1.5 rounded-xl text-xs font-semibold bg-white/5 border border-white/10 text-white/70 hover:text-white hover:bg-white/10 transition flex items-center gap-1.5 mt-1 md:mt-0">
                    <Ticket className="w-3.5 h-3.5" /> <span className="hidden md:inline">Tickets</span>
                  </a>
                )}
              </div>
            ))}
          </div>

        </div>

        {/* Footer info */}
        <div className="mt-16 pt-8 border-t border-white/[0.06] flex items-center justify-between text-xs text-white/30">
          <p>© {new Date().getFullYear()} 1INME Calendar</p>
          <div className="flex items-center gap-4">
            <a href="#" className="hover:text-white/60 transition">Report Abuse</a>
            <a href="#" className="hover:text-white/60 transition">Terms</a>
          </div>
        </div>
      </main>
    </div>
  );
}
