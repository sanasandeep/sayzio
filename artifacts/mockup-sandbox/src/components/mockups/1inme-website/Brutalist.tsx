import React, { useState } from 'react';
import { 
  Search, Bell, Moon, LayoutDashboard, Link as LinkIcon, 
  PlusCircle, QrCode, FileText, Send, Puzzle, CalendarDays, 
  CalendarSync, Crown, MousePointerClick, TrendingUp, Folder, 
  Plus, LogOut, Sun, Target, ChevronRight
} from 'lucide-react';

export function Brutalist() {
  const [isDarkMode, setIsDarkMode] = useState(false);

  return (
    <div className={`min-h-screen flex w-full font-sans transition-colors duration-200 ${isDarkMode ? 'bg-[#1a1a1a] text-white' : 'bg-[#f4f4f0] text-black'}`}>
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700;900&display=swap');
        
        .font-brutal {
          font-family: 'Space Grotesk', sans-serif;
        }
        
        .brutal-border {
          border: 3px solid #000;
        }
        
        .brutal-shadow {
          box-shadow: 6px 6px 0px 0px #000;
          transition: all 0.2s ease;
        }
        
        .brutal-shadow:hover {
          transform: translate(2px, 2px);
          box-shadow: 4px 4px 0px 0px #000;
        }

        .brutal-shadow:active {
          transform: translate(6px, 6px);
          box-shadow: 0px 0px 0px 0px #000;
        }

        .dark-brutal-border {
          border: 3px solid #fff;
        }

        .dark-brutal-shadow {
          box-shadow: 6px 6px 0px 0px #fff;
          transition: all 0.2s ease;
        }

        .dark-brutal-shadow:hover {
          transform: translate(2px, 2px);
          box-shadow: 4px 4px 0px 0px #fff;
        }

        .dark-brutal-shadow:active {
          transform: translate(6px, 6px);
          box-shadow: 0px 0px 0px 0px #fff;
        }
      `}</style>

      {/* SIDEBAR */}
      <aside className={`w-72 flex-shrink-0 flex flex-col border-r-[4px] ${isDarkMode ? 'border-white bg-[#111]' : 'border-black bg-[#fff]'} font-brutal`}>
        <div className={`p-6 border-b-[4px] ${isDarkMode ? 'border-white' : 'border-black'}`}>
          <h1 className="text-4xl font-black uppercase tracking-tighter flex items-center">
            <span className={isDarkMode ? 'text-white' : 'text-black'}>1IN</span>
            <span className="text-[#ff3b30] ml-1">ME</span>
          </h1>
        </div>

        <nav className="flex-1 overflow-y-auto p-4 space-y-2">
          <NavItem icon={<LayoutDashboard size={22} strokeWidth={3} />} label="Dashboard" active isDark={isDarkMode} />
          <NavItem icon={<LinkIcon size={22} strokeWidth={3} />} label="All Links" isDark={isDarkMode} />
          <NavItem icon={<PlusCircle size={22} strokeWidth={3} />} label="Create Link" isDark={isDarkMode} />
          <NavItem icon={<QrCode size={22} strokeWidth={3} />} label="QR Codes" isDark={isDarkMode} />
          <NavItem icon={<FileText size={22} strokeWidth={3} />} label="Forms" isDark={isDarkMode} />
          <NavItem icon={<Send size={22} strokeWidth={3} />} label="Intros" isDark={isDarkMode} />
          <NavItem icon={<Puzzle size={22} strokeWidth={3} />} label="Integrations" isDark={isDarkMode} />
          <NavItem icon={<CalendarDays size={22} strokeWidth={3} />} label="Events" isDark={isDarkMode} />
          <NavItem icon={<CalendarSync size={22} strokeWidth={3} />} label="Calendar Sync" isDark={isDarkMode} />
        </nav>

        <div className={`p-4 border-t-[4px] ${isDarkMode ? 'border-white' : 'border-black'}`}>
          <div className={`bg-[#fde047] text-black brutal-border brutal-shadow p-4 mb-4 rounded-none`}>
            <div className="flex items-center gap-2 font-bold mb-1 uppercase tracking-tight">
              <Crown size={20} strokeWidth={3} />
              Free Plan
            </div>
            <p className="text-sm font-bold mb-3">Limits applied.</p>
            <button className="w-full bg-[#ff3b30] text-white font-black uppercase py-2 brutal-border brutal-shadow rounded-none">
              Upgrade
            </button>
          </div>

          <div className="flex items-center justify-between font-bold">
            <div className="flex items-center gap-3">
              <div className={`w-10 h-10 ${isDarkMode ? 'bg-white text-black' : 'bg-black text-white'} rounded-none flex items-center justify-center font-black text-xl brutal-border`}>
                D
              </div>
              <div className="leading-tight">
                <div className="text-sm uppercase tracking-tight">Demo User</div>
                <div className="text-xs opacity-70">demo@1inme.com</div>
              </div>
            </div>
            <button className="hover:text-[#ff3b30] transition-colors">
              <LogOut size={22} strokeWidth={3} />
            </button>
          </div>
        </div>
      </aside>

      {/* MAIN CONTENT */}
      <main className="flex-1 flex flex-col min-w-0 font-brutal h-screen overflow-hidden">
        {/* TOP CHROME */}
        <header className={`h-20 flex-shrink-0 flex items-center justify-between px-8 border-b-[4px] ${isDarkMode ? 'border-white bg-[#111]' : 'border-black bg-white'} z-10 relative`}>
          <h2 className="text-2xl font-black uppercase tracking-tight">Dashboard</h2>

          <div className="flex items-center gap-6">
            <div className="relative">
              <Search size={22} strokeWidth={3} className={`absolute left-3 top-1/2 -translate-y-1/2 ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`} />
              <input 
                type="text" 
                placeholder="Search links, projects..." 
                className={`w-72 py-3 pl-11 pr-4 bg-[#f4f4f0] text-black placeholder-gray-500 outline-none brutal-border focus:brutal-shadow rounded-none font-bold`}
              />
            </div>

            <button 
              onClick={() => setIsDarkMode(!isDarkMode)}
              className={`p-3 ${isDarkMode ? 'bg-gray-800 text-white dark-brutal-border dark-brutal-shadow' : 'bg-[#e2e8f0] text-black brutal-border brutal-shadow'} rounded-none`}
            >
              {isDarkMode ? <Sun size={22} strokeWidth={3} /> : <Moon size={22} strokeWidth={3} />}
            </button>

            <button className={`relative p-3 ${isDarkMode ? 'bg-[#ff3b30] text-white dark-brutal-border dark-brutal-shadow' : 'bg-[#ff3b30] text-white brutal-border brutal-shadow'} rounded-none`}>
              <Bell size={22} strokeWidth={3} />
              <span className="absolute -top-2 -right-2 w-5 h-5 bg-[#a3e635] brutal-border rounded-none flex items-center justify-center text-[10px] font-black text-black">1</span>
            </button>

            <div className={`h-12 w-[4px] ${isDarkMode ? 'bg-white' : 'bg-black'}`}></div>

            <button className={`flex items-center gap-2 px-6 py-3 bg-[#60a5fa] text-black font-black uppercase tracking-tight ${isDarkMode ? 'dark-brutal-border dark-brutal-shadow' : 'brutal-border brutal-shadow'} rounded-none`}>
              <Plus size={22} strokeWidth={3} />
              New Link
            </button>
          </div>
        </header>

        {/* DASHBOARD CONTENT */}
        <div className="flex-1 overflow-y-auto p-8">
          <div className="max-w-6xl mx-auto space-y-10">
            
            {/* GREETING CARD */}
            <div className={`p-8 ${isDarkMode ? 'bg-[#222] dark-brutal-border' : 'bg-[#c084fc] brutal-border brutal-shadow'} relative`}>
              <div className="flex justify-between items-start">
                <div className="flex gap-6 items-center">
                  <div className={`w-24 h-24 bg-[#a3e635] text-black flex items-center justify-center text-5xl font-black brutal-border brutal-shadow transform -rotate-6`}>
                    G
                  </div>
                  <div>
                    <div className="flex gap-3 mb-3">
                      <span className="px-3 py-1 bg-black text-white text-sm font-black uppercase brutal-border border-black">Saturday</span>
                      <span className="px-3 py-1 bg-white text-black text-sm font-black uppercase brutal-border border-black">0 links</span>
                    </div>
                    <h2 className={`text-4xl font-black uppercase tracking-tighter ${isDarkMode ? 'text-white' : 'text-black'} mb-1`}>
                      Good morning, Demo User
                    </h2>
                    <p className={`text-lg font-bold ${isDarkMode ? 'text-gray-300' : 'text-black'}`}>
                      Here's an overview of your link performance.
                    </p>
                  </div>
                </div>
                <button className={`px-6 py-3 bg-[#a3e635] text-black font-black uppercase brutal-border brutal-shadow flex items-center gap-2`}>
                  <Plus size={20} strokeWidth={3} />
                  Create Link
                </button>
              </div>
            </div>

            {/* STAT CARDS */}
            <div className="grid grid-cols-5 gap-6">
              <StatCard title="Plan" value="Free" icon={<Crown size={28} strokeWidth={3} />} color="bg-[#fde047]" isDark={isDarkMode} />
              <StatCard title="Links" value="27" icon={<LinkIcon size={28} strokeWidth={3} />} color="bg-[#a3e635]" isDark={isDarkMode} />
              <StatCard title="Total Clicks" value="391" icon={<MousePointerClick size={28} strokeWidth={3} />} color="bg-[#f472b6]" isDark={isDarkMode} />
              <StatCard title="Today" value="0" icon={<TrendingUp size={28} strokeWidth={3} />} color="bg-[#60a5fa]" isDark={isDarkMode} />
              <StatCard title="Projects" value="0" icon={<Folder size={28} strokeWidth={3} />} color="bg-[#fb923c]" isDark={isDarkMode} />
            </div>

            <div className="grid grid-cols-3 gap-10">
              {/* RECENT LINKS */}
              <div className="col-span-2">
                <div className="flex justify-between items-end mb-6">
                  <h3 className="text-3xl font-black uppercase tracking-tight">Recent Links</h3>
                  <a href="#" className={`font-bold flex items-center gap-1 ${isDarkMode ? 'text-[#a3e635]' : 'text-blue-600'} hover:underline`}>
                    <Plus size={18} strokeWidth={3} />
                    NEW
                  </a>
                </div>
                
                <div className="space-y-4">
                  <LinkRow 
                    title="Eiffel Tower on Google Maps" 
                    slug="demo-maps-eiffel" 
                    clicks={0} 
                    type="URL" 
                    color="bg-[#a3e635]"
                    isDark={isDarkMode}
                  />
                  <LinkRow 
                    title="Hacker News Front Page" 
                    slug="demo-news-hn" 
                    clicks={0} 
                    type="URL" 
                    color="bg-[#f472b6]"
                    isDark={isDarkMode}
                  />
                  <LinkRow 
                    title="Laravel on GitHub" 
                    slug="demo-gh-laravel" 
                    clicks={0} 
                    type="URL" 
                    color="bg-[#60a5fa]"
                    isDark={isDarkMode}
                  />
                  <LinkRow 
                    title="QR Codes — Wikipedia" 
                    slug="demo-qr-wiki" 
                    clicks={0} 
                    type="URL" 
                    color="bg-[#fde047]"
                    isDark={isDarkMode}
                  />
                </div>
              </div>

              {/* QUICK ACTIONS */}
              <div>
                <h3 className="text-3xl font-black uppercase tracking-tight mb-6">Quick Actions</h3>
                <div className="space-y-4">
                  <ActionBtn icon={<LinkIcon size={24} strokeWidth={3} />} label="Shorten a URL" isDark={isDarkMode} />
                  <ActionBtn icon={<Folder size={24} strokeWidth={3} />} label="Create Project" isDark={isDarkMode} />
                  <ActionBtn icon={<Target size={24} strokeWidth={3} />} label="Add Tracker" isDark={isDarkMode} />
                  <ActionBtn icon={<QrCode size={24} strokeWidth={3} />} label="Generate QR Code" isDark={isDarkMode} />
                </div>
              </div>
            </div>

          </div>
        </div>
      </main>
    </div>
  );
}

function NavItem({ icon, label, active = false, isDark }: { icon: React.ReactNode, label: string, active?: boolean, isDark: boolean }) {
  const baseClasses = "flex items-center gap-3 px-4 py-3 font-bold uppercase tracking-tight transition-all duration-200 brutal-border";
  
  if (active) {
    return (
      <a href="#" className={`${baseClasses} bg-[#a3e635] text-black brutal-shadow translate-x-[2px] translate-y-[2px]`}>
        {icon}
        {label}
      </a>
    );
  }
  
  return (
    <a href="#" className={`${baseClasses} ${isDark ? 'bg-[#222] text-white hover:bg-[#333]' : 'bg-white text-black hover:bg-[#f4f4f0]'} hover:brutal-shadow`}>
      {icon}
      {label}
    </a>
  );
}

function StatCard({ title, value, icon, color, isDark }: { title: string, value: string, icon: React.ReactNode, color: string, isDark: boolean }) {
  return (
    <div className={`p-5 ${color} text-black brutal-border brutal-shadow flex flex-col justify-between`}>
      <div className="flex justify-between items-start mb-4">
        <h4 className="font-black uppercase tracking-tight text-sm opacity-90">{title}</h4>
        <div className="bg-white p-2 brutal-border rounded-full border-black">
          {icon}
        </div>
      </div>
      <div className="text-5xl font-black tracking-tighter">{value}</div>
    </div>
  );
}

function LinkRow({ title, slug, clicks, type, color, isDark }: { title: string, slug: string, clicks: number, type: string, color: string, isDark: boolean }) {
  return (
    <div className={`flex items-center justify-between p-4 ${isDark ? 'bg-[#222] text-white dark-brutal-border' : 'bg-white text-black brutal-border brutal-shadow'} group hover:-translate-y-1 transition-transform`}>
      <div className="flex items-center gap-4">
        <div className={`w-12 h-12 ${color} text-black brutal-border flex items-center justify-center font-black text-lg border-black`}>
          {type[0]}
        </div>
        <div>
          <h4 className="font-black text-xl mb-1">{title}</h4>
          <a href="#" className="text-sm font-bold text-blue-600 hover:underline flex items-center gap-1">
            yoursite.com/{slug}
          </a>
        </div>
      </div>
      <div className="flex items-center gap-6">
        <div className="text-right">
          <div className="font-black text-xl">{clicks}</div>
          <div className="text-xs uppercase font-bold opacity-70">Clicks</div>
        </div>
        <div className={`px-3 py-1 ${isDark ? 'bg-white text-black' : 'bg-black text-white'} font-black uppercase text-xs brutal-border`}>
          {type}
        </div>
      </div>
    </div>
  );
}

function ActionBtn({ icon, label, isDark }: { icon: React.ReactNode, label: string, isDark: boolean }) {
  return (
    <button className={`w-full flex items-center gap-4 p-4 ${isDark ? 'bg-[#222] text-white dark-brutal-border dark-brutal-shadow' : 'bg-white text-black brutal-border brutal-shadow'} hover:bg-[#a3e635] hover:text-black transition-colors group text-left`}>
      <div className={`p-2 bg-white text-black brutal-border border-black group-hover:rotate-12 transition-transform`}>
        {icon}
      </div>
      <span className="font-black text-lg uppercase tracking-tight flex-1">{label}</span>
      <ChevronRight size={24} strokeWidth={3} className="opacity-50 group-hover:opacity-100 group-hover:translate-x-1 transition-all" />
    </button>
  );
}
