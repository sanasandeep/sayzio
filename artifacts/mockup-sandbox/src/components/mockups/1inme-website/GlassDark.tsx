import React from "react";
import {
  LayoutDashboard,
  Link as LinkIcon,
  PlusCircle,
  QrCode,
  FileText,
  MessageSquare,
  Network,
  Calendar,
  CalendarDays,
  Search,
  Sun,
  Moon,
  Bell,
  Crown,
  MousePointerClick,
  TrendingUp,
  Folder,
  Target,
  LogOut,
  MoreVertical,
  ChevronRight
} from "lucide-react";

export function GlassDark() {
  return (
    <div className="min-h-screen w-full bg-[#0a0a0f] text-slate-200 font-sans selection:bg-purple-500/30 overflow-hidden flex relative">
      {/* Ambient Background Blooms */}
      <div className="absolute top-[-20%] left-[-10%] w-[50%] h-[50%] bg-purple-600/20 blur-[120px] rounded-full pointer-events-none mix-blend-screen" />
      <div className="absolute bottom-[-10%] right-[-10%] w-[40%] h-[60%] bg-cyan-600/20 blur-[120px] rounded-full pointer-events-none mix-blend-screen" />
      <div className="absolute top-[20%] right-[20%] w-[30%] h-[30%] bg-pink-600/10 blur-[100px] rounded-full pointer-events-none mix-blend-screen" />
      
      {/* Noise Overlay */}
      <div className="absolute inset-0 opacity-[0.03] pointer-events-none mix-blend-overlay" style={{ backgroundImage: `url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E")` }}></div>

      {/* Sidebar */}
      <aside className="w-64 h-screen flex flex-col border-r border-white/10 bg-white/[0.02] backdrop-blur-3xl z-10 relative">
        <div className="p-6 flex items-center gap-2">
          <div className="w-8 h-8 rounded-xl bg-gradient-to-br from-purple-500 to-cyan-500 flex items-center justify-center shadow-[0_0_15px_rgba(168,85,247,0.4)]">
            <span className="font-bold text-white tracking-tighter text-sm">1</span>
          </div>
          <span className="text-xl font-bold tracking-tight text-white">
            1IN<span className="text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-cyan-400">ME</span>
          </span>
        </div>

        <nav className="flex-1 px-4 py-4 space-y-1 overflow-y-auto">
          <NavItem icon={<LayoutDashboard size={18} />} label="Dashboard" active />
          <NavItem icon={<LinkIcon size={18} />} label="All Links" />
          <NavItem icon={<PlusCircle size={18} />} label="Create Link" />
          <NavItem icon={<QrCode size={18} />} label="QR Codes" />
          <NavItem icon={<FileText size={18} />} label="Forms" />
          <NavItem icon={<MessageSquare size={18} />} label="Intros" />
          <NavItem icon={<Network size={18} />} label="Integrations" />
          <NavItem icon={<Calendar size={18} />} label="Events" />
          <NavItem icon={<CalendarDays size={18} />} label="Calendar Sync" />
        </nav>

        <div className="p-4">
          <div className="bg-white/[0.03] border border-white/10 rounded-2xl p-4 backdrop-blur-md relative overflow-hidden group">
            <div className="absolute inset-0 bg-gradient-to-br from-purple-500/10 to-cyan-500/10 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
            <div className="flex items-center gap-2 mb-3 relative z-10">
              <Crown size={16} className="text-purple-400" />
              <span className="font-medium text-sm text-white">Free Plan</span>
            </div>
            <p className="text-xs text-slate-400 mb-4 relative z-10">Unlock premium analytics and custom domains.</p>
            <button className="w-full py-2 bg-white/10 hover:bg-white/20 text-white text-sm font-medium rounded-xl transition-all border border-white/5 hover:border-white/20 hover:shadow-[0_0_15px_rgba(255,255,255,0.1)] relative z-10">
              Upgrade
            </button>
          </div>
        </div>

        <div className="p-4 border-t border-white/10">
          <div className="flex items-center gap-3 p-2 rounded-xl hover:bg-white/5 transition-colors cursor-pointer group">
            <div className="w-9 h-9 rounded-full bg-gradient-to-tr from-slate-700 to-slate-600 flex items-center justify-center text-white font-medium border border-white/10">
              D
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-white truncate">Demo User</p>
              <p className="text-xs text-slate-500 truncate">demo@1inme.com</p>
            </div>
            <LogOut size={16} className="text-slate-500 group-hover:text-white transition-colors" />
          </div>
        </div>
      </aside>

      {/* Main Content */}
      <main className="flex-1 flex flex-col h-screen overflow-hidden z-10">
        {/* Top Chrome */}
        <header className="h-20 border-b border-white/10 bg-white/[0.01] backdrop-blur-xl flex items-center justify-between px-8 shrink-0">
          <h1 className="text-xl font-medium text-white">Dashboard</h1>
          
          <div className="flex items-center gap-6">
            <div className="relative group">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-cyan-400 transition-colors" size={18} />
              <input 
                type="text" 
                placeholder="Search links, projects..." 
                className="w-64 bg-white/5 border border-white/10 rounded-full py-2 pl-10 pr-4 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500/50 focus:ring-1 focus:ring-cyan-500/50 focus:bg-white/10 transition-all"
              />
            </div>
            
            <div className="flex items-center gap-3">
              <button className="p-2 text-slate-400 hover:text-white hover:bg-white/10 rounded-full transition-colors">
                <Sun size={18} />
              </button>
              <button className="p-2 text-slate-400 hover:text-white hover:bg-white/10 rounded-full transition-colors relative">
                <Bell size={18} />
                <span className="absolute top-1.5 right-2 w-2 h-2 bg-pink-500 rounded-full shadow-[0_0_8px_rgba(236,72,153,0.8)]"></span>
              </button>
              <div className="h-6 w-px bg-white/10 mx-1"></div>
              <button className="h-9 px-4 bg-gradient-to-r from-purple-500 to-cyan-500 hover:from-purple-400 hover:to-cyan-400 text-white text-sm font-medium rounded-full shadow-[0_0_15px_rgba(168,85,247,0.3)] hover:shadow-[0_0_20px_rgba(103,232,249,0.4)] transition-all flex items-center gap-2">
                <PlusCircle size={16} />
                New Link
              </button>
            </div>
          </div>
        </header>

        {/* Scrollable Content */}
        <div className="flex-1 overflow-y-auto p-8">
          <div className="max-w-6xl mx-auto space-y-8">
            
            {/* Greeting */}
            <div className="bg-white/[0.03] border border-white/10 rounded-3xl p-8 backdrop-blur-2xl relative overflow-hidden group">
              <div className="absolute top-0 right-0 w-64 h-64 bg-cyan-500/10 blur-[80px] rounded-full pointer-events-none group-hover:bg-cyan-500/20 transition-colors duration-700"></div>
              
              <div className="flex items-start justify-between relative z-10">
                <div className="flex items-center gap-6">
                  <div className="w-20 h-20 rounded-2xl bg-gradient-to-br from-white/10 to-white/5 border border-white/20 flex items-center justify-center text-3xl font-light text-white shadow-xl backdrop-blur-md">
                    G
                  </div>
                  <div>
                    <div className="flex items-center gap-3 mb-2">
                      <span className="px-3 py-1 bg-white/10 border border-white/10 rounded-full text-xs font-medium text-cyan-100 backdrop-blur-md">Saturday</span>
                      <span className="px-3 py-1 bg-white/10 border border-white/10 rounded-full text-xs font-medium text-purple-100 backdrop-blur-md">0 links</span>
                    </div>
                    <h2 className="text-3xl font-medium text-white mb-1 tracking-tight">Good morning, Demo User</h2>
                    <p className="text-slate-400">Here's an overview of your link performance.</p>
                  </div>
                </div>
                <button className="h-10 px-5 bg-white/10 hover:bg-white/20 border border-white/10 hover:border-white/30 text-white text-sm font-medium rounded-xl transition-all shadow-lg backdrop-blur-md">
                  + Create Link
                </button>
              </div>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-5 gap-4">
              <StatCard title="PLAN" value="Free" icon={<Crown size={20} />} accent="purple" />
              <StatCard title="LINKS" value="27" icon={<LinkIcon size={20} />} accent="cyan" />
              <StatCard title="TOTAL CLICKS" value="391" icon={<MousePointerClick size={20} />} accent="pink" />
              <StatCard title="TODAY" value="0" icon={<TrendingUp size={20} />} accent="cyan" />
              <StatCard title="PROJECTS" value="0" icon={<Folder size={20} />} accent="purple" />
            </div>

            {/* Bottom Grid */}
            <div className="grid grid-cols-3 gap-8">
              
              {/* Recent Links */}
              <div className="col-span-2 space-y-4">
                <div className="flex items-center justify-between px-1">
                  <h3 className="text-lg font-medium text-white">Recent Links</h3>
                  <button className="text-sm text-cyan-400 hover:text-cyan-300 font-medium flex items-center gap-1 transition-colors">
                    + New
                  </button>
                </div>
                
                <div className="bg-white/[0.02] border border-white/10 rounded-3xl backdrop-blur-xl overflow-hidden shadow-2xl">
                  <div className="divide-y divide-white/5">
                    <LinkRow 
                      title="Eiffel Tower on Google Maps" 
                      slug="demo-maps-eiffel" 
                      clicks={0} 
                    />
                    <LinkRow 
                      title="Hacker News Front Page" 
                      slug="demo-news-hn" 
                      clicks={0} 
                    />
                    <LinkRow 
                      title="Laravel on GitHub" 
                      slug="demo-gh-laravel" 
                      clicks={0} 
                    />
                    <LinkRow 
                      title="QR Codes — Wikipedia" 
                      slug="demo-qr-wiki" 
                      clicks={0} 
                    />
                  </div>
                </div>
              </div>

              {/* Quick Actions */}
              <div className="space-y-4">
                <h3 className="text-lg font-medium text-white px-1">Quick Actions</h3>
                <div className="grid grid-cols-2 gap-4">
                  <QuickAction icon={<LinkIcon size={24} />} label="Shorten a URL" accent="cyan" />
                  <QuickAction icon={<Folder size={24} />} label="Create Project" accent="purple" />
                  <QuickAction icon={<Target size={24} />} label="Add Tracker" accent="pink" />
                  <QuickAction icon={<QrCode size={24} />} label="Generate QR Code" accent="cyan" />
                </div>
              </div>

            </div>
          </div>
        </div>
      </main>
    </div>
  );
}

function NavItem({ icon, label, active = false }: { icon: React.ReactNode, label: string, active?: boolean }) {
  return (
    <div className={`flex items-center gap-3 px-4 py-2.5 rounded-xl cursor-pointer transition-all duration-300 relative group overflow-hidden ${
      active ? 'bg-white/10 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-slate-200'
    }`}>
      {active && (
        <div className="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-8 bg-cyan-400 rounded-r-full shadow-[0_0_10px_rgba(34,211,238,0.8)]"></div>
      )}
      <div className={`${active ? 'text-cyan-400' : 'group-hover:text-white transition-colors'}`}>
        {icon}
      </div>
      <span className="font-medium text-sm">{label}</span>
    </div>
  );
}

function StatCard({ title, value, icon, accent }: { title: string, value: string, icon: React.ReactNode, accent: 'purple' | 'cyan' | 'pink' }) {
  const accentColors = {
    purple: 'text-purple-400 group-hover:text-purple-300 group-hover:shadow-[0_0_15px_rgba(168,85,247,0.5)]',
    cyan: 'text-cyan-400 group-hover:text-cyan-300 group-hover:shadow-[0_0_15px_rgba(34,211,238,0.5)]',
    pink: 'text-pink-400 group-hover:text-pink-300 group-hover:shadow-[0_0_15px_rgba(236,72,153,0.5)]'
  };
  
  const bgGlows = {
    purple: 'bg-purple-500/5',
    cyan: 'bg-cyan-500/5',
    pink: 'bg-pink-500/5'
  };

  return (
    <div className="bg-white/[0.02] border border-white/10 rounded-2xl p-5 backdrop-blur-xl relative overflow-hidden group hover:bg-white/[0.04] transition-all duration-300 cursor-default">
      <div className={`absolute -right-6 -top-6 w-24 h-24 ${bgGlows[accent]} rounded-full blur-[30px] group-hover:opacity-100 opacity-50 transition-opacity duration-500`}></div>
      <div className="flex justify-between items-start mb-4 relative z-10">
        <span className="text-xs font-semibold text-slate-400 tracking-wider">{title}</span>
        <div className={`p-2 rounded-xl bg-white/5 border border-white/10 transition-all duration-300 ${accentColors[accent]}`}>
          {icon}
        </div>
      </div>
      <div className="text-3xl font-light text-white tracking-tight relative z-10">{value}</div>
    </div>
  );
}

function LinkRow({ title, slug, clicks }: { title: string, slug: string, clicks: number }) {
  return (
    <div className="p-5 hover:bg-white/[0.03] transition-colors group flex items-center justify-between relative overflow-hidden">
      <div className="absolute inset-0 bg-gradient-to-r from-cyan-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none"></div>
      
      <div className="flex items-center gap-4 relative z-10">
        <div className="w-10 h-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center text-slate-400 group-hover:text-cyan-400 transition-colors group-hover:border-cyan-500/30">
          <LinkIcon size={18} />
        </div>
        <div>
          <h4 className="text-base font-medium text-white mb-1 group-hover:text-cyan-50 transition-colors">{title}</h4>
          <a href="#" className="text-sm text-cyan-400/80 hover:text-cyan-400 flex items-center gap-1">
            yoursite.com/{slug}
          </a>
        </div>
      </div>

      <div className="flex items-center gap-6 relative z-10">
        <div className="text-right">
          <div className="text-sm font-medium text-white">{clicks} clicks</div>
          <div className="text-xs text-slate-500 uppercase tracking-wider mt-1">URL</div>
        </div>
        <button className="p-2 text-slate-500 hover:text-white hover:bg-white/10 rounded-lg transition-colors opacity-0 group-hover:opacity-100">
          <MoreVertical size={18} />
        </button>
      </div>
    </div>
  );
}

function QuickAction({ icon, label, accent }: { icon: React.ReactNode, label: string, accent: 'cyan' | 'purple' | 'pink' }) {
  const accentGradients = {
    cyan: 'from-cyan-500/20 to-transparent group-hover:from-cyan-500/40',
    purple: 'from-purple-500/20 to-transparent group-hover:from-purple-500/40',
    pink: 'from-pink-500/20 to-transparent group-hover:from-pink-500/40'
  };
  
  const textColors = {
    cyan: 'text-cyan-400',
    purple: 'text-purple-400',
    pink: 'text-pink-400'
  };

  return (
    <div className="bg-white/[0.02] border border-white/10 rounded-2xl p-6 backdrop-blur-xl hover:bg-white/[0.04] transition-all duration-300 cursor-pointer group relative overflow-hidden flex flex-col items-center justify-center text-center h-32">
      <div className={`absolute inset-0 bg-gradient-to-b ${accentGradients[accent]} opacity-0 group-hover:opacity-100 transition-opacity duration-500`}></div>
      <div className={`mb-3 ${textColors[accent]} relative z-10 transform group-hover:scale-110 group-hover:-translate-y-1 transition-all duration-300`}>
        {icon}
      </div>
      <span className="text-sm font-medium text-slate-300 group-hover:text-white transition-colors relative z-10">{label}</span>
    </div>
  );
}
