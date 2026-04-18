import React from "react";
import {
  Search,
  Bell,
  Crown,
  Link2,
  MousePointerClick,
  TrendingUp,
  Folder,
  Target,
  QrCode,
  LogOut,
  Plus,
  Moon
} from "lucide-react";

export function Editorial() {
  return (
    <div className="min-h-[100dvh] bg-[#fcfbf9] text-[#1a1a1a] font-sans selection:bg-[#d94f04] selection:text-white">
      <style dangerouslySetInnerHTML={{
        __html: `
          @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600&family=Inter:wght@400;500;600&display=swap');
          .font-serif { font-family: 'Playfair Display', serif; }
          .font-sans { font-family: 'Inter', sans-serif; }
        `
      }} />

      {/* Top Chrome */}
      <header className="border-b border-[#e5e3db] bg-[#fcfbf9] sticky top-0 z-20">
        <div className="max-w-7xl mx-auto px-6 lg:px-12 h-16 flex items-center justify-between">
          <div className="flex items-center gap-12">
            {/* Logo */}
            <div className="text-2xl font-serif tracking-tight font-semibold flex items-center">
              <span className="text-[#1a1a1a]">1IN</span>
              <span className="text-[#d94f04]">ME</span>
            </div>

            {/* App title */}
            <div className="hidden md:block font-serif text-lg italic text-[#666]">
              Dashboard
            </div>
            
            {/* Search */}
            <div className="hidden md:flex items-center gap-2 text-[#666] bg-[#f2f0e9] px-3 py-1.5 rounded-full border border-transparent focus-within:border-[#d94f04] focus-within:bg-white transition-colors">
              <Search className="w-4 h-4" />
              <input 
                type="text" 
                placeholder="Search links, projects..." 
                className="bg-transparent border-none outline-none text-sm w-64 placeholder:text-[#888] text-[#1a1a1a]"
              />
            </div>
          </div>

          <div className="flex items-center gap-6">
            <div className="flex items-center gap-4">
              <button className="text-[#666] hover:text-[#1a1a1a] transition-colors">
                <Moon className="w-5 h-5" />
              </button>
              <button className="text-[#666] hover:text-[#1a1a1a] transition-colors relative">
                <Bell className="w-5 h-5" />
                <span className="absolute top-0 right-0 w-2 h-2 bg-[#d94f04] rounded-full border border-[#fcfbf9]"></span>
              </button>
            </div>
            <div className="flex items-center gap-3 pl-6 border-l border-[#e5e3db]">
              <div className="w-8 h-8 rounded-full bg-[#e5e3db] flex items-center justify-center font-serif text-sm">
                DU
              </div>
              <button className="bg-[#1a1a1a] text-white px-4 py-1.5 rounded-full text-sm font-medium hover:bg-[#d94f04] transition-colors flex items-center gap-2">
                <Plus className="w-4 h-4" />
                New Link
              </button>
            </div>
          </div>
        </div>

        {/* Horizontal Nav */}
        <div className="max-w-7xl mx-auto px-6 lg:px-12 flex items-center justify-between border-t border-[#f2f0e9]">
          <nav className="flex gap-8 overflow-x-auto no-scrollbar py-3">
            <a href="#" className="text-sm font-medium text-[#1a1a1a] border-b-2 border-[#1a1a1a] pb-1 whitespace-nowrap">Dashboard</a>
            <a href="#" className="text-sm text-[#666] hover:text-[#1a1a1a] pb-1 whitespace-nowrap">All Links</a>
            <a href="#" className="text-sm text-[#666] hover:text-[#1a1a1a] pb-1 whitespace-nowrap">Create Link</a>
            <a href="#" className="text-sm text-[#666] hover:text-[#1a1a1a] pb-1 whitespace-nowrap">QR Codes</a>
            <a href="#" className="text-sm text-[#666] hover:text-[#1a1a1a] pb-1 whitespace-nowrap">Forms</a>
            <a href="#" className="text-sm text-[#666] hover:text-[#1a1a1a] pb-1 whitespace-nowrap">Intros</a>
            <a href="#" className="text-sm text-[#666] hover:text-[#1a1a1a] pb-1 whitespace-nowrap">Integrations</a>
            <a href="#" className="text-sm text-[#666] hover:text-[#1a1a1a] pb-1 whitespace-nowrap">Events</a>
            <a href="#" className="text-sm text-[#666] hover:text-[#1a1a1a] pb-1 whitespace-nowrap">Calendar Sync</a>
          </nav>
          
          <div className="flex items-center gap-4 text-sm hidden lg:flex shrink-0 ml-4">
            <div className="flex items-center gap-2 text-[#666]">
              <Crown className="w-4 h-4 text-[#d94f04]" />
              <span>Free Plan</span>
            </div>
            <button className="text-[#d94f04] font-medium hover:underline underline-offset-4">Upgrade</button>
            <div className="w-px h-4 bg-[#e5e3db]"></div>
            <button className="text-[#666] hover:text-[#1a1a1a] flex items-center gap-2">
              <LogOut className="w-4 h-4" />
              <span className="sr-only">Logout</span>
            </button>
          </div>
        </div>
      </header>

      <main className="max-w-7xl mx-auto px-6 lg:px-12 py-12 lg:py-20 grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-24">
        
        {/* Main Content Column */}
        <div className="lg:col-span-8 xl:col-span-9 space-y-16">
          
          {/* Greeting Section */}
          <section className="border-b border-[#1a1a1a] pb-12">
            <div className="flex items-center gap-3 mb-6">
              <div className="w-12 h-12 rounded-full bg-[#1a1a1a] text-white flex items-center justify-center font-serif text-xl italic">
                G
              </div>
              <div className="flex gap-2">
                <span className="px-3 py-1 border border-[#e5e3db] rounded-full text-xs font-medium tracking-wide uppercase text-[#666]">
                  Saturday
                </span>
                <span className="px-3 py-1 border border-[#e5e3db] rounded-full text-xs font-medium tracking-wide uppercase text-[#d94f04]">
                  0 links
                </span>
              </div>
            </div>
            
            <h1 className="text-5xl lg:text-7xl font-serif font-medium leading-none mb-6">
              Good morning,<br/>
              <span className="italic text-[#666]">Demo User.</span>
            </h1>
            
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
              <p className="text-lg text-[#666] max-w-md leading-relaxed">
                Here's an overview of your link performance. Your reach is growing steadily across all platforms.
              </p>
              <button className="text-sm font-medium border-b border-[#1a1a1a] pb-1 hover:text-[#d94f04] hover:border-[#d94f04] transition-colors w-fit flex-shrink-0">
                + Create Link
              </button>
            </div>
          </section>

          {/* Stats Callouts */}
          <section>
            <div className="grid grid-cols-2 md:grid-cols-5 gap-8 border-y border-[#e5e3db] py-8">
              <div className="space-y-2">
                <div className="flex items-center gap-2 text-xs font-medium tracking-widest uppercase text-[#888]">
                  <Crown className="w-3 h-3" />
                  Plan
                </div>
                <div className="text-3xl font-serif text-[#1a1a1a]">Free</div>
              </div>
              <div className="space-y-2 md:border-l border-[#e5e3db] md:pl-8">
                <div className="flex items-center gap-2 text-xs font-medium tracking-widest uppercase text-[#888]">
                  <Link2 className="w-3 h-3" />
                  Links
                </div>
                <div className="text-3xl font-serif text-[#1a1a1a]">27</div>
              </div>
              <div className="space-y-2 md:border-l border-[#e5e3db] md:pl-8">
                <div className="flex items-center gap-2 text-xs font-medium tracking-widest uppercase text-[#888]">
                  <MousePointerClick className="w-3 h-3" />
                  Total Clicks
                </div>
                <div className="text-3xl font-serif text-[#d94f04]">391</div>
              </div>
              <div className="space-y-2 md:border-l border-[#e5e3db] md:pl-8">
                <div className="flex items-center gap-2 text-xs font-medium tracking-widest uppercase text-[#888]">
                  <TrendingUp className="w-3 h-3" />
                  Today
                </div>
                <div className="text-3xl font-serif text-[#1a1a1a]">0</div>
              </div>
              <div className="space-y-2 md:border-l border-[#e5e3db] md:pl-8">
                <div className="flex items-center gap-2 text-xs font-medium tracking-widest uppercase text-[#888]">
                  <Folder className="w-3 h-3" />
                  Projects
                </div>
                <div className="text-3xl font-serif text-[#1a1a1a]">0</div>
              </div>
            </div>
          </section>

          {/* Recent Links Feed */}
          <section>
            <div className="flex items-center justify-between mb-10 border-b border-[#1a1a1a] pb-4">
              <h2 className="text-2xl font-serif font-medium">Recent Links</h2>
              <a href="#" className="text-sm font-medium hover:text-[#d94f04] transition-colors">+ New</a>
            </div>

            <div className="space-y-10">
              {[
                { title: "Eiffel Tower on Google Maps", slug: "demo-maps-eiffel", clicks: 0, type: "URL" },
                { title: "Hacker News Front Page", slug: "demo-news-hn", clicks: 0, type: "URL" },
                { title: "Laravel on GitHub", slug: "demo-gh-laravel", clicks: 0, type: "URL" },
                { title: "QR Codes — Wikipedia", slug: "demo-qr-wiki", clicks: 0, type: "URL" }
              ].map((link, i) => (
                <article key={i} className="group cursor-pointer">
                  <div className="flex items-baseline gap-4 mb-2">
                    <span className="text-xs font-bold tracking-widest uppercase text-[#d94f04]">
                      {link.type}
                    </span>
                    <span className="text-sm text-[#888]">
                      {link.clicks} clicks
                    </span>
                  </div>
                  <h3 className="text-2xl lg:text-3xl font-serif mb-2 group-hover:text-[#d94f04] transition-colors">
                    {link.title}
                  </h3>
                  <div className="text-[#666] font-mono text-sm tracking-tight border-b border-transparent group-hover:border-[#666] transition-colors w-fit">
                    yoursite.com/{link.slug}
                  </div>
                </article>
              ))}
            </div>
          </section>
        </div>

        {/* Sidebar Column */}
        <aside className="lg:col-span-4 xl:col-span-3">
          <div className="sticky top-32">
            <h2 className="text-sm font-bold tracking-widest uppercase text-[#888] mb-8 border-b border-[#e5e3db] pb-4">
              Quick Actions
            </h2>
            
            <ul className="space-y-6">
              <li>
                <a href="#" className="group flex items-start gap-4">
                  <div className="mt-1 text-[#888] group-hover:text-[#d94f04] transition-colors">
                    <Link2 className="w-5 h-5" />
                  </div>
                  <div>
                    <h3 className="font-serif text-xl group-hover:text-[#d94f04] transition-colors border-b border-[#e5e3db] group-hover:border-[#d94f04] pb-1 w-fit mb-1">
                      Shorten a URL
                    </h3>
                    <p className="text-sm text-[#666]">Create a new trackable link</p>
                  </div>
                </a>
              </li>
              <li>
                <a href="#" className="group flex items-start gap-4">
                  <div className="mt-1 text-[#888] group-hover:text-[#d94f04] transition-colors">
                    <Folder className="w-5 h-5" />
                  </div>
                  <div>
                    <h3 className="font-serif text-xl group-hover:text-[#d94f04] transition-colors border-b border-[#e5e3db] group-hover:border-[#d94f04] pb-1 w-fit mb-1">
                      Create Project
                    </h3>
                    <p className="text-sm text-[#666]">Organize your links</p>
                  </div>
                </a>
              </li>
              <li>
                <a href="#" className="group flex items-start gap-4">
                  <div className="mt-1 text-[#888] group-hover:text-[#d94f04] transition-colors">
                    <Target className="w-5 h-5" />
                  </div>
                  <div>
                    <h3 className="font-serif text-xl group-hover:text-[#d94f04] transition-colors border-b border-[#e5e3db] group-hover:border-[#d94f04] pb-1 w-fit mb-1">
                      Add Tracker
                    </h3>
                    <p className="text-sm text-[#666]">Setup conversion pixels</p>
                  </div>
                </a>
              </li>
              <li>
                <a href="#" className="group flex items-start gap-4">
                  <div className="mt-1 text-[#888] group-hover:text-[#d94f04] transition-colors">
                    <QrCode className="w-5 h-5" />
                  </div>
                  <div>
                    <h3 className="font-serif text-xl group-hover:text-[#d94f04] transition-colors border-b border-[#e5e3db] group-hover:border-[#d94f04] pb-1 w-fit mb-1">
                      Generate QR Code
                    </h3>
                    <p className="text-sm text-[#666]">For offline sharing</p>
                  </div>
                </a>
              </li>
            </ul>

            {/* Editorial "About User" / Footer equivalent in sidebar */}
            <div className="mt-16 pt-8 border-t border-[#e5e3db]">
              <div className="text-sm text-[#666] mb-1">Logged in as</div>
              <div className="font-serif text-lg">Demo User</div>
              <div className="text-sm text-[#888] font-mono tracking-tight mt-1 mb-4">demo@1inme.com</div>
              <button className="text-sm font-medium border-b border-[#1a1a1a] pb-1 hover:text-[#d94f04] hover:border-[#d94f04] transition-colors flex items-center gap-2 w-fit">
                Sign Out <LogOut className="w-3 h-3" />
              </button>
            </div>
          </div>
        </aside>

      </main>
    </div>
  );
}
