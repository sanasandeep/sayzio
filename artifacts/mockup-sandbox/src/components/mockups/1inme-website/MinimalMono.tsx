import React from 'react';
import { 
  Bell, 
  LogOut, 
  Link as LinkIcon, 
  Folder, 
  Target, 
  QrCode, 
  Plus, 
  Crown, 
  MousePointerClick, 
  TrendingUp,
  Globe
} from 'lucide-react';

export function MinimalMono() {
  return (
    <div className="flex h-screen w-full bg-white text-zinc-900 font-sans selection:bg-indigo-100 selection:text-indigo-900 overflow-hidden">
      {/* Sidebar */}
      <aside className="w-[220px] flex-shrink-0 border-r border-zinc-200 bg-white flex flex-col justify-between h-full relative z-10">
        <div className="flex flex-col h-full">
          {/* Logo Area */}
          <div className="h-12 flex items-center px-4 border-b border-zinc-200">
            <span className="font-bold text-sm tracking-tight text-zinc-900">
              1IN<span className="text-indigo-500">ME</span>
            </span>
          </div>

          {/* Navigation */}
          <div className="flex-1 overflow-y-auto py-4 px-2 space-y-0.5">
            <NavItem label="Dashboard" shortcut="G D" active />
            <NavItem label="All Links" shortcut="G L" />
            <NavItem label="Create Link" shortcut="C" />
            <NavItem label="QR Codes" shortcut="G Q" />
            <NavItem label="Forms" shortcut="G F" />
            <NavItem label="Intros" shortcut="G I" />
            <NavItem label="Integrations" shortcut="G N" />
            <NavItem label="Events" shortcut="G E" />
            <NavItem label="Calendar Sync" shortcut="G C" />
          </div>

          {/* Bottom section */}
          <div className="p-2 space-y-2 border-t border-zinc-200">
            <div className="p-3 border border-zinc-200 rounded-md bg-zinc-50/50">
              <div className="flex items-center justify-between mb-2">
                <span className="text-xs font-medium text-zinc-900 flex items-center gap-1.5">
                  <Crown className="w-3.5 h-3.5 text-zinc-500" />
                  Free Plan
                </span>
              </div>
              <button className="w-full h-7 bg-zinc-900 text-white text-xs font-medium rounded hover:bg-zinc-800 transition-colors">
                Upgrade
              </button>
            </div>

            <div className="flex items-center justify-between p-2 rounded hover:bg-zinc-100 transition-colors group cursor-pointer">
              <div className="flex items-center gap-2 overflow-hidden">
                <div className="w-5 h-5 rounded bg-zinc-200 flex items-center justify-center flex-shrink-0">
                  <span className="text-[10px] font-medium text-zinc-600">D</span>
                </div>
                <div className="flex flex-col truncate">
                  <span className="text-xs font-medium text-zinc-900 truncate">Demo User</span>
                </div>
              </div>
              <LogOut className="w-3.5 h-3.5 text-zinc-400 group-hover:text-zinc-600 opacity-0 group-hover:opacity-100 transition-all" />
            </div>
          </div>
        </div>
      </aside>

      {/* Main Content */}
      <main className="flex-1 flex flex-col min-w-0 bg-white">
        {/* Top Chrome */}
        <header className="h-12 border-b border-zinc-200 flex items-center justify-between px-4 lg:px-6 bg-white/80 backdrop-blur-sm sticky top-0 z-10">
          <div className="flex items-center gap-4">
            <h1 className="text-sm font-medium text-zinc-900">Dashboard</h1>
          </div>
          
          {/* Command Palette Mock */}
          <div className="absolute left-1/2 -translate-x-1/2 w-full max-w-md hidden md:flex">
            <button className="w-full h-8 flex items-center justify-between px-3 text-xs text-zinc-500 bg-zinc-50 border border-zinc-200 rounded hover:border-zinc-300 hover:bg-zinc-100 transition-colors">
              <span>Search or run a command...</span>
              <div className="flex items-center gap-1">
                <kbd className="font-mono text-[10px] px-1.5 py-0.5 rounded bg-zinc-200/50 border border-zinc-200 text-zinc-500">⌘</kbd>
                <kbd className="font-mono text-[10px] px-1.5 py-0.5 rounded bg-zinc-200/50 border border-zinc-200 text-zinc-500">K</kbd>
              </div>
            </button>
          </div>

          <div className="flex items-center gap-3">
            <button className="relative p-1.5 text-zinc-500 hover:text-zinc-900 transition-colors rounded hover:bg-zinc-100">
              <Bell className="w-4 h-4" />
              <span className="absolute top-1.5 right-1.5 w-1.5 h-1.5 bg-indigo-500 rounded-full border border-white"></span>
            </button>
            <div className="w-px h-4 bg-zinc-200"></div>
            <button className="h-7 px-3 bg-indigo-500 text-white text-xs font-medium rounded hover:bg-indigo-600 transition-colors flex items-center gap-1.5 shadow-sm">
              <Plus className="w-3.5 h-3.5" />
              New Link
            </button>
          </div>
        </header>

        {/* Scrollable Area */}
        <div className="flex-1 overflow-y-auto p-4 lg:p-8">
          <div className="max-w-5xl mx-auto space-y-8">
            
            {/* Greeting Single Line */}
            <div className="flex items-end justify-between border-b border-zinc-200 pb-4">
              <div className="flex items-center gap-3">
                <div className="w-8 h-8 rounded bg-zinc-100 border border-zinc-200 flex items-center justify-center">
                  <span className="text-sm font-medium text-zinc-900">G</span>
                </div>
                <div>
                  <div className="flex items-center gap-2 mb-1">
                    <span className="text-xs font-mono text-zinc-500 bg-zinc-50 border border-zinc-200 px-1.5 py-0.5 rounded">SAT</span>
                    <span className="text-xs font-mono text-zinc-500 bg-zinc-50 border border-zinc-200 px-1.5 py-0.5 rounded">0 LINKS</span>
                  </div>
                  <h2 className="text-base font-medium text-zinc-900 leading-none">
                    Good morning, Demo User
                  </h2>
                </div>
              </div>
              <button className="text-xs font-medium text-zinc-600 hover:text-zinc-900 flex items-center gap-1">
                Overview <TrendingUp className="w-3 h-3" />
              </button>
            </div>

            {/* Stat Cards - Linear style, single row, minimal */}
            <div className="flex flex-nowrap border border-zinc-200 rounded-md overflow-hidden bg-white divide-x divide-zinc-200">
              <Stat label="PLAN" value="Free" icon={<Crown className="w-3.5 h-3.5" />} />
              <Stat label="LINKS" value="27" icon={<LinkIcon className="w-3.5 h-3.5" />} />
              <Stat label="CLICKS" value="391" icon={<MousePointerClick className="w-3.5 h-3.5" />} />
              <Stat label="TODAY" value="0" icon={<TrendingUp className="w-3.5 h-3.5" />} />
              <Stat label="PROJECTS" value="0" icon={<Folder className="w-3.5 h-3.5" />} />
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
              
              {/* Recent Links - Dense tabular */}
              <div className="lg:col-span-2 space-y-3">
                <div className="flex items-center justify-between">
                  <h3 className="text-sm font-medium text-zinc-900">Recent Links</h3>
                  <button className="text-xs font-medium text-indigo-600 hover:text-indigo-700 transition-colors">
                    + New
                  </button>
                </div>
                
                <div className="border border-zinc-200 rounded-md overflow-hidden bg-white">
                  <table className="w-full text-left border-collapse">
                    <thead>
                      <tr className="border-b border-zinc-200 bg-zinc-50/50">
                        <th className="py-2 px-3 text-[10px] font-medium text-zinc-500 uppercase tracking-wider w-5"></th>
                        <th className="py-2 px-3 text-[10px] font-medium text-zinc-500 uppercase tracking-wider">Title</th>
                        <th className="py-2 px-3 text-[10px] font-medium text-zinc-500 uppercase tracking-wider hidden sm:table-cell">Short URL</th>
                        <th className="py-2 px-3 text-[10px] font-medium text-zinc-500 uppercase tracking-wider text-right">Clicks</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-zinc-200 text-sm">
                      <LinkRow 
                        title="Eiffel Tower on Google Maps" 
                        slug="demo-maps-eiffel" 
                        clicks="0" 
                      />
                      <LinkRow 
                        title="Hacker News Front Page" 
                        slug="demo-news-hn" 
                        clicks="0" 
                      />
                      <LinkRow 
                        title="Laravel on GitHub" 
                        slug="demo-gh-laravel" 
                        clicks="0" 
                      />
                      <LinkRow 
                        title="QR Codes — Wikipedia" 
                        slug="demo-qr-wiki" 
                        clicks="0" 
                      />
                    </tbody>
                  </table>
                </div>
              </div>

              {/* Quick Actions */}
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <h3 className="text-sm font-medium text-zinc-900">Quick Actions</h3>
                </div>
                <div className="flex flex-col gap-1.5">
                  <ActionItem icon={<LinkIcon className="w-4 h-4" />} label="Shorten URL" shortcut="C" />
                  <ActionItem icon={<Folder className="w-4 h-4" />} label="Create Project" shortcut="P" />
                  <ActionItem icon={<Target className="w-4 h-4" />} label="Add Tracker" shortcut="T" />
                  <ActionItem icon={<QrCode className="w-4 h-4" />} label="Generate QR Code" shortcut="Q" />
                </div>
              </div>

            </div>

          </div>
        </div>
      </main>
    </div>
  );
}

function NavItem({ label, shortcut, active = false }: { label: string, shortcut: string, active?: boolean }) {
  return (
    <button 
      className={`group w-full flex items-center justify-between px-2 py-1.5 rounded text-sm transition-colors ${
        active 
          ? 'bg-zinc-100 text-zinc-900 font-medium' 
          : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50'
      }`}
    >
      <span>{label}</span>
      <div className="flex gap-1 opacity-0 group-hover:opacity-100 md:opacity-100 transition-opacity">
        {shortcut.split(' ').map((key, i) => (
          <kbd key={i} className="font-mono text-[9px] text-zinc-400 bg-zinc-50 border border-zinc-200 px-1 py-0.5 rounded leading-none">
            {key}
          </kbd>
        ))}
      </div>
    </button>
  );
}

function Stat({ label, value, icon }: { label: string, value: string, icon: React.ReactNode }) {
  return (
    <div className="flex-1 p-4 flex flex-col justify-between min-w-[120px]">
      <div className="flex items-center gap-1.5 text-[10px] font-medium text-zinc-500 mb-3">
        {icon}
        <span className="uppercase tracking-wider">{label}</span>
      </div>
      <div className="text-2xl font-mono tracking-tight text-zinc-900">
        {value}
      </div>
    </div>
  );
}

function LinkRow({ title, slug, clicks }: { title: string, slug: string, clicks: string }) {
  return (
    <tr className="group hover:bg-zinc-50/50 transition-colors cursor-pointer">
      <td className="py-2.5 px-3">
        <Globe className="w-3.5 h-3.5 text-zinc-400 group-hover:text-zinc-600" />
      </td>
      <td className="py-2.5 px-3">
        <div className="font-medium text-zinc-900 truncate max-w-[200px] sm:max-w-xs">{title}</div>
      </td>
      <td className="py-2.5 px-3 hidden sm:table-cell">
        <div className="font-mono text-xs text-zinc-500 bg-zinc-100 border border-zinc-200 px-1.5 py-0.5 rounded inline-block truncate max-w-[200px]">
          1in.me/{slug}
        </div>
      </td>
      <td className="py-2.5 px-3 text-right">
        <span className="font-mono text-xs font-medium text-zinc-600">{clicks}</span>
      </td>
    </tr>
  );
}

function ActionItem({ icon, label, shortcut }: { icon: React.ReactNode, label: string, shortcut: string }) {
  return (
    <button className="w-full flex items-center justify-between p-3 border border-zinc-200 rounded-md bg-white hover:border-zinc-300 hover:bg-zinc-50 transition-all group">
      <div className="flex items-center gap-3 text-zinc-700 group-hover:text-zinc-900">
        {icon}
        <span className="text-sm font-medium">{label}</span>
      </div>
      <kbd className="font-mono text-[10px] text-zinc-400 bg-zinc-50 border border-zinc-200 px-1.5 py-0.5 rounded transition-colors group-hover:bg-white group-hover:border-zinc-300">
        {shortcut}
      </kbd>
    </button>
  );
}
