import React, { useState } from 'react';
import { 
  ArrowLeft, 
  Sparkles, 
  Calendar as CalendarIcon, 
  MapPin, 
  Clock, 
  X, 
  Plus, 
  ChevronDown, 
  Check, 
  Search,
  Filter,
  Info
} from 'lucide-react';

export function CreateFlow() {
  const [calendarType, setCalendarType] = useState<'regular' | 'smart'>('smart');
  
  // Dummy state for rules
  const [tags, setTags] = useState(['startups', 'networking']);
  const [sources, setSources] = useState(['following']); // 'owned', 'following', 'all'
  const [keyword, setKeyword] = useState('');
  const [location, setLocation] = useState('Dubai');
  const [dateRule, setDateRule] = useState('next-90'); // 'all', 'next-30', 'next-90', 'weekends'
  
  const [isTagInputOpen, setIsTagInputOpen] = useState(false);
  const [tagInput, setTagInput] = useState('');

  const handleAddTag = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && tagInput.trim()) {
      e.preventDefault();
      if (!tags.includes(tagInput.trim().toLowerCase())) {
        setTags([...tags, tagInput.trim().toLowerCase()]);
      }
      setTagInput('');
      setIsTagInputOpen(false);
    }
  };

  const removeTag = (tagToRemove: string) => {
    setTags(tags.filter(t => t !== tagToRemove));
  };

  return (
    <div className="min-h-screen bg-[#0d0c12] text-white font-sans selection:bg-blue-500/30">
      {/* Background gradients for visual depth */}
      <div className="fixed inset-0 pointer-events-none overflow-hidden">
        <div className="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-blue-600/10 blur-[120px] rounded-full mix-blend-screen"></div>
        <div className="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-indigo-600/10 blur-[120px] rounded-full mix-blend-screen"></div>
      </div>

      <div className="max-w-6xl mx-auto px-4 py-8 md:py-12 relative z-10">
        
        {/* Header */}
        <div className="flex items-center gap-4 mb-8">
          <button className="text-white/30 hover:text-white/70 transition w-10 h-10 flex items-center justify-center rounded-full hover:bg-white/5">
            <ArrowLeft className="w-5 h-5" />
          </button>
          <div>
            <h1 className="text-2xl font-bold text-white tracking-tight">Create Calendar</h1>
            <p className="text-xs text-white/40 mt-1">Step 2 of 2 &middot; <button className="text-blue-400 hover:underline">change type</button></p>
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
          
          {/* Left Column - Form & Rules */}
          <div className="lg:col-span-7 space-y-6">
            
            {/* Kind Selector */}
            <div className="grid grid-cols-2 gap-3">
              <button 
                onClick={() => setCalendarType('regular')}
                className={`relative p-4 rounded-2xl border text-left transition-all ${
                  calendarType === 'regular' 
                    ? 'bg-white/[0.05] border-white/20 shadow-[0_0_20px_rgba(255,255,255,0.03)]' 
                    : 'bg-white/[0.02] border-white/5 hover:border-white/10 hover:bg-white/[0.03] opacity-60 hover:opacity-100'
                }`}
              >
                <div className="flex items-start justify-between mb-2">
                  <div className={`w-8 h-8 rounded-xl flex items-center justify-center ${calendarType === 'regular' ? 'bg-white/10 text-white' : 'bg-white/5 text-white/50'}`}>
                    <CalendarIcon className="w-4 h-4" />
                  </div>
                  {calendarType === 'regular' && <div className="w-4 h-4 rounded-full bg-blue-500 text-white flex items-center justify-center"><Check className="w-3 h-3" /></div>}
                </div>
                <h3 className="text-sm font-semibold text-white mb-1">Regular Calendar</h3>
                <p className="text-xs text-white/50 leading-relaxed">A standard calendar container. You manually create or import events into it.</p>
              </button>

              <button 
                onClick={() => setCalendarType('smart')}
                className={`relative p-4 rounded-2xl border text-left transition-all ${
                  calendarType === 'smart' 
                    ? 'bg-blue-500/10 border-blue-500/30 shadow-[0_0_30px_rgba(61,107,255,0.1)]' 
                    : 'bg-white/[0.02] border-white/5 hover:border-white/10 hover:bg-white/[0.03] opacity-60 hover:opacity-100'
                }`}
              >
                <div className="flex items-start justify-between mb-2">
                  <div className={`w-8 h-8 rounded-xl flex items-center justify-center ${calendarType === 'smart' ? 'bg-blue-500 text-white shadow-[0_0_15px_rgba(61,107,255,0.4)]' : 'bg-white/5 text-white/50'}`}>
                    <Sparkles className="w-4 h-4" />
                  </div>
                  {calendarType === 'smart' && <div className="w-4 h-4 rounded-full bg-blue-500 text-white flex items-center justify-center"><Check className="w-3 h-3" /></div>}
                </div>
                <h3 className="text-sm font-semibold text-white mb-1 flex items-center gap-1.5">
                  Smart Calendar <span className="text-[9px] uppercase tracking-wider font-bold bg-blue-500/20 text-blue-400 px-1.5 py-0.5 rounded">New</span>
                </h3>
                <p className="text-xs text-white/50 leading-relaxed">An auto-updating feed. Define rules, and matching events pull in automatically.</p>
              </button>
            </div>

            {/* Form Basics */}
            <div className="bg-white/[0.03] border border-white/10 rounded-2xl p-6 backdrop-blur-md">
              <h2 className="text-[11px] font-semibold uppercase tracking-widest text-white/30 mb-5">Calendar Basics</h2>
              
              <div className="space-y-4">
                <div>
                  <label className="block text-xs font-medium text-white/60 mb-1.5">Calendar Title</label>
                  <input 
                    type="text" 
                    defaultValue="MENA Tech & Startups"
                    className="w-full h-11 border border-white/10 bg-[#0a0a0f] rounded-xl px-4 text-sm text-white focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500/40 outline-none transition placeholder:text-white/20"
                    placeholder="e.g. AI Events"
                  />
                </div>
                
                <div>
                  <label className="block text-xs font-medium text-white/60 mb-1.5">Description <span className="text-white/30 font-normal">(optional)</span></label>
                  <textarea 
                    rows={2} 
                    defaultValue="A curated feed of startup, tech, and AI events happening across Dubai and the MENA region."
                    className="w-full border border-white/10 bg-[#0a0a0f] rounded-xl px-4 py-3 text-sm text-white focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500/40 outline-none transition placeholder:text-white/20 resize-none"
                    placeholder="What kind of events will appear here?"
                  />
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-medium text-white/60 mb-1.5">Custom URL</label>
                    <div className="flex bg-[#0a0a0f] border border-white/10 rounded-xl overflow-hidden focus-within:ring-2 focus-within:ring-blue-500/40 focus-within:border-blue-500/40 transition">
                      <span className="flex items-center px-3 text-xs text-white/30 bg-white/5 border-r border-white/5 select-none">1in.me/c/</span>
                      <input 
                        type="text" 
                        defaultValue="mena-startups"
                        className="flex-1 h-11 bg-transparent px-3 text-sm text-white outline-none placeholder:text-white/20"
                      />
                    </div>
                  </div>
                  <div>
                    <label className="block text-xs font-medium text-white/60 mb-1.5">Accent Color</label>
                    <div className="h-11 border border-white/10 bg-[#0a0a0f] rounded-xl px-2 py-1.5 flex items-center gap-2">
                      <input type="color" defaultValue="#3d6bff" className="w-8 h-8 rounded-lg cursor-pointer bg-transparent border-0 p-0" />
                      <span className="text-sm text-white/60 font-mono">#3d6bff</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {/* Smart Rules Builder */}
            {calendarType === 'smart' && (
              <div className="bg-white/[0.03] border border-blue-500/20 rounded-2xl overflow-hidden backdrop-blur-md">
                <div className="p-6 border-b border-white/5 bg-blue-500/[0.02]">
                  <div className="flex items-center justify-between mb-2">
                    <h2 className="text-[11px] font-semibold uppercase tracking-widest text-blue-400 flex items-center gap-1.5">
                      <Filter className="w-3.5 h-3.5" /> Smart Filters
                    </h2>
                    <span className="text-xs text-white/40 flex items-center gap-1">
                      <Info className="w-3.5 h-3.5 text-white/30" /> Auto-updates hourly
                    </span>
                  </div>
                  <p className="text-xs text-white/50 leading-relaxed">
                    Set conditions to pull in events automatically. <span className="text-white/70">Events are only sourced from calendars you currently own or follow.</span>
                  </p>
                </div>
                
                <div className="p-6 space-y-5">
                  {/* Rule: Source */}
                  <div className="flex items-start gap-4">
                    <div className="w-24 shrink-0 pt-2 text-right">
                      <span className="text-xs font-medium text-white/40">From</span>
                    </div>
                    <div className="flex-1 flex flex-wrap gap-2">
                      <button className={`px-3 py-1.5 rounded-lg text-xs font-medium border transition ${sources.includes('owned') ? 'bg-blue-500/15 border-blue-500/30 text-white' : 'bg-transparent border-white/10 text-white/50 hover:text-white hover:border-white/20'}`}>
                        My Calendars
                      </button>
                      <button className={`px-3 py-1.5 rounded-lg text-xs font-medium border transition ${sources.includes('following') ? 'bg-blue-500/15 border-blue-500/30 text-white flex items-center gap-1.5' : 'bg-transparent border-white/10 text-white/50 hover:text-white hover:border-white/20'}`}>
                        Following <span className="w-4 h-4 rounded-full bg-blue-500/20 text-blue-400 flex items-center justify-center text-[9px]">14</span>
                      </button>
                    </div>
                  </div>

                  {/* Rule: Tags */}
                  <div className="flex items-start gap-4">
                    <div className="w-24 shrink-0 pt-2 text-right">
                      <span className="text-xs font-medium text-white/40">Tagged with</span>
                    </div>
                    <div className="flex-1 flex flex-wrap items-center gap-2">
                      {tags.map(tag => (
                        <div key={tag} className="flex items-center gap-1 pl-2.5 pr-1 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-300 text-[11px] font-medium">
                          #{tag}
                          <button onClick={() => removeTag(tag)} className="w-4 h-4 rounded-full flex items-center justify-center hover:bg-blue-500/20 text-blue-300/70 hover:text-blue-300 transition">
                            <X className="w-2.5 h-2.5" />
                          </button>
                        </div>
                      ))}
                      
                      {isTagInputOpen ? (
                        <input 
                          type="text"
                          value={tagInput}
                          onChange={e => setTagInput(e.target.value)}
                          onKeyDown={handleAddTag}
                          onBlur={() => {
                            if (!tagInput) setIsTagInputOpen(false);
                            else handleAddTag({ key: 'Enter', preventDefault: () => {} } as any);
                          }}
                          autoFocus
                          className="h-[26px] bg-[#0a0a0f] border border-white/20 rounded-full px-2.5 text-[11px] text-white w-24 focus:outline-none focus:border-blue-500/50"
                          placeholder="type & enter"
                        />
                      ) : (
                        <button onClick={() => setIsTagInputOpen(true)} className="flex items-center gap-1 px-2.5 py-1 rounded-full border border-white/10 border-dashed text-white/40 hover:text-white/70 hover:border-white/30 text-[11px] transition">
                          <Plus className="w-3 h-3" /> Add tag
                        </button>
                      )}
                    </div>
                  </div>

                  {/* Rule: Location */}
                  <div className="flex items-start gap-4">
                    <div className="w-24 shrink-0 pt-2 text-right">
                      <span className="text-xs font-medium text-white/40">Location</span>
                    </div>
                    <div className="flex-1 flex flex-wrap items-center gap-2">
                      <div className="flex items-center gap-2 pl-3 pr-1 py-1 rounded-xl bg-white/[0.04] border border-white/10">
                        <MapPin className="w-3 h-3 text-white/40" />
                        <span className="text-xs text-white/80">{location}</span>
                        <button onClick={() => setLocation('')} className="w-5 h-5 rounded-md flex items-center justify-center hover:bg-white/10 text-white/40 hover:text-white transition">
                          <X className="w-3 h-3" />
                        </button>
                      </div>
                      <button className="flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-white/10 border-dashed text-white/40 hover:text-white/70 hover:border-white/30 text-[11px] transition">
                        <Plus className="w-3 h-3" /> Online only
                      </button>
                    </div>
                  </div>

                  {/* Rule: Date */}
                  <div className="flex items-start gap-4">
                    <div className="w-24 shrink-0 pt-2 text-right">
                      <span className="text-xs font-medium text-white/40">Date</span>
                    </div>
                    <div className="flex-1 relative">
                      <select 
                        value={dateRule}
                        onChange={(e) => setDateRule(e.target.value)}
                        className="appearance-none bg-white/[0.04] border border-white/10 hover:border-white/20 rounded-xl pl-3 pr-8 py-1.5 text-xs text-white/80 focus:outline-none focus:ring-2 focus:ring-blue-500/30 transition cursor-pointer"
                      >
                        <option value="all">Any future date</option>
                        <option value="next-30">Next 30 days</option>
                        <option value="next-90">Next 90 days</option>
                        <option value="weekends">Weekends only</option>
                      </select>
                      <ChevronDown className="w-3 h-3 text-white/40 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                    </div>
                  </div>

                  {/* Hint state */}
                  {!keyword && (
                    <div className="flex items-start gap-4 pt-2">
                      <div className="w-24 shrink-0"></div>
                      <div className="flex-1">
                         <button className="text-xs text-blue-400 hover:text-blue-300 flex items-center gap-1.5 transition">
                           <Plus className="w-3 h-3" /> Add keyword filter
                         </button>
                      </div>
                    </div>
                  )}

                </div>
              </div>
            )}
            
            <div className="flex items-center justify-end gap-3 pt-4">
               <button className="px-5 py-2.5 rounded-xl text-sm font-medium text-white/60 hover:text-white transition">
                 Cancel
               </button>
               <button className="px-6 py-2.5 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-500 text-white shadow-[0_0_15px_rgba(61,107,255,0.3)] transition transform active:scale-95 flex items-center gap-2">
                 Create Calendar <ArrowLeft className="w-4 h-4 rotate-180" />
               </button>
            </div>

          </div>

          {/* Right Column - Live Preview */}
          <div className="lg:col-span-5 relative">
            <div className="sticky top-8 flex flex-col h-[calc(100vh-64px)] max-h-[800px]">
              
              <div className="flex flex-col h-full bg-white/[0.02] border border-white/5 rounded-3xl overflow-hidden">
                {/* Preview Header */}
                <div className="p-5 border-b border-white/5 bg-white/[0.01]">
                  <div className="flex items-center justify-between mb-1">
                    <h2 className="text-sm font-semibold text-white flex items-center gap-2">
                      Live Preview
                      {calendarType === 'smart' && (
                        <span className="flex h-2 w-2 relative">
                          <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                          <span className="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span>
                        </span>
                      )}
                    </h2>
                    <span className="text-[11px] px-2 py-1 bg-white/5 rounded-lg text-white/60">
                      {calendarType === 'smart' ? '12 matches' : 'Empty'}
                    </span>
                  </div>
                  <p className="text-xs text-white/40">
                    {calendarType === 'smart' 
                      ? 'Events matching your current filters.' 
                      : 'Regular calendars start empty.'}
                  </p>
                </div>

                {/* Preview Content */}
                <div className="flex-1 overflow-y-auto p-4 space-y-3 custom-scrollbar relative">
                  
                  {calendarType === 'regular' ? (
                    <div className="absolute inset-0 flex flex-col items-center justify-center p-8 text-center opacity-60">
                      <CalendarIcon className="w-10 h-10 text-white/20 mb-3" />
                      <p className="text-sm font-medium text-white/50 mb-1">Ready for events</p>
                      <p className="text-xs text-white/30">You can add events manually or import them once created.</p>
                    </div>
                  ) : (
                    <>
                      {/* Event 1 */}
                      <div className="group bg-[#16151e]/80 border border-white/5 rounded-2xl p-3 flex items-start gap-3 hover:bg-[#1a1924] transition">
                        <div className="flex flex-col items-center justify-center w-11 shrink-0 rounded-xl py-2 bg-blue-500/10 border border-blue-500/20">
                           <span className="text-[8px] uppercase tracking-wider font-semibold text-blue-400">Oct</span>
                           <span className="text-[18px] font-bold leading-none text-blue-100 mt-0.5">24</span>
                        </div>
                        <div className="min-w-0 flex-1 py-0.5">
                           <div className="flex items-start justify-between gap-2">
                             <h3 className="font-medium text-sm text-white/90 truncate">AI Startup Pitch Night</h3>
                             <Sparkles className="w-3 h-3 text-blue-400 shrink-0 mt-0.5" />
                           </div>
                           <div className="flex flex-wrap items-center gap-x-2.5 gap-y-1 mt-1.5">
                             <span className="text-[10px] text-white/40 flex items-center gap-1">
                               <Clock className="w-2.5 h-2.5" /> 6:00 PM
                             </span>
                             <span className="text-[10px] text-white/40 flex items-center gap-1 truncate">
                               <MapPin className="w-2.5 h-2.5" /> Dubai Marina
                             </span>
                           </div>
                           <div className="flex flex-wrap items-center gap-1.5 mt-2">
                             <span className="text-[9px] flex items-center gap-1 text-white/30 bg-white/5 px-1.5 py-0.5 rounded flex-shrink-0">
                               <span className="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Startup Grind MENA
                             </span>
                             <span className="text-[9px] text-blue-300/80">#startups</span>
                             <span className="text-[9px] text-blue-300/80">#ai</span>
                           </div>
                        </div>
                      </div>

                      {/* Event 2 */}
                      <div className="group bg-[#16151e]/80 border border-white/5 rounded-2xl p-3 flex items-start gap-3 hover:bg-[#1a1924] transition">
                        <div className="flex flex-col items-center justify-center w-11 shrink-0 rounded-xl py-2 bg-blue-500/10 border border-blue-500/20">
                           <span className="text-[8px] uppercase tracking-wider font-semibold text-blue-400">Oct</span>
                           <span className="text-[18px] font-bold leading-none text-blue-100 mt-0.5">28</span>
                        </div>
                        <div className="min-w-0 flex-1 py-0.5">
                           <div className="flex items-start justify-between gap-2">
                             <h3 className="font-medium text-sm text-white/90 truncate">Founders Coffee & Connect</h3>
                             <Sparkles className="w-3 h-3 text-blue-400 shrink-0 mt-0.5" />
                           </div>
                           <div className="flex flex-wrap items-center gap-x-2.5 gap-y-1 mt-1.5">
                             <span className="text-[10px] text-white/40 flex items-center gap-1">
                               <Clock className="w-2.5 h-2.5" /> 9:00 AM
                             </span>
                             <span className="text-[10px] text-white/40 flex items-center gap-1 truncate">
                               <MapPin className="w-2.5 h-2.5" /> DIFC, Dubai
                             </span>
                           </div>
                           <div className="flex flex-wrap items-center gap-1.5 mt-2">
                             <span className="text-[9px] flex items-center gap-1 text-white/30 bg-white/5 px-1.5 py-0.5 rounded flex-shrink-0">
                               <span className="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Founders Coffee
                             </span>
                             <span className="text-[9px] text-blue-300/80">#networking</span>
                             <span className="text-[9px] text-blue-300/80">#startups</span>
                           </div>
                        </div>
                      </div>

                      {/* Event 3 */}
                      <div className="group bg-[#16151e]/80 border border-white/5 rounded-2xl p-3 flex items-start gap-3 hover:bg-[#1a1924] transition">
                        <div className="flex flex-col items-center justify-center w-11 shrink-0 rounded-xl py-2 bg-blue-500/10 border border-blue-500/20">
                           <span className="text-[8px] uppercase tracking-wider font-semibold text-blue-400">Nov</span>
                           <span className="text-[18px] font-bold leading-none text-blue-100 mt-0.5">05</span>
                        </div>
                        <div className="min-w-0 flex-1 py-0.5">
                           <div className="flex items-start justify-between gap-2">
                             <h3 className="font-medium text-sm text-white/90 truncate">Web3 x AI Mixer</h3>
                             <Sparkles className="w-3 h-3 text-blue-400 shrink-0 mt-0.5" />
                           </div>
                           <div className="flex flex-wrap items-center gap-x-2.5 gap-y-1 mt-1.5">
                             <span className="text-[10px] text-white/40 flex items-center gap-1">
                               <Clock className="w-2.5 h-2.5" /> 7:00 PM
                             </span>
                             <span className="text-[10px] text-white/40 flex items-center gap-1 truncate">
                               <MapPin className="w-2.5 h-2.5" /> Dubai Internet City
                             </span>
                           </div>
                           <div className="flex flex-wrap items-center gap-1.5 mt-2">
                             <span className="text-[9px] flex items-center gap-1 text-white/30 bg-white/5 px-1.5 py-0.5 rounded flex-shrink-0">
                               <span className="w-1.5 h-1.5 rounded-full bg-purple-500"></span> Dubai Tech Meetups
                             </span>
                             <span className="text-[9px] text-blue-300/80">#networking</span>
                             <span className="text-[9px] text-blue-300/80">#ai</span>
                           </div>
                        </div>
                      </div>

                      {/* Faded indicator for more events */}
                      <div className="pt-2 pb-4 flex justify-center">
                        <span className="text-[10px] text-white/20 uppercase tracking-widest font-medium">9 more matches</span>
                      </div>
                    </>
                  )}
                  
                </div>

                {/* Footer Gradient overlay to hint scroll */}
                <div className="h-10 bg-gradient-to-t from-[#14131d] to-transparent absolute bottom-0 left-0 right-0 pointer-events-none rounded-b-3xl"></div>
              </div>
            </div>
          </div>

        </div>
      </div>

      <style dangerouslySetInnerHTML={{__html: `
        .custom-scrollbar::-webkit-scrollbar {
          width: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
          background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
          background: rgba(255,255,255,0.1);
          border-radius: 4px;
        }
        .custom-scrollbar:hover::-webkit-scrollbar-thumb {
          background: rgba(255,255,255,0.2);
        }
      `}} />
    </div>
  );
}
