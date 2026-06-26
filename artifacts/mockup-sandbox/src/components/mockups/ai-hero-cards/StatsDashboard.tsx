import React from "react";
import {
  MousePointerClick,
  TrendingUp,
  Users,
  Globe,
  ArrowUpRight,
} from "lucide-react";
import { PhoneStage, StatusBar, Chip, BuilderChip, tokens } from "./_shell";

const bars = [38, 52, 44, 70, 60, 88, 76, 96];

const topLinks = [
  { label: "Shop the beans", clicks: "4,210", pct: 92 },
  { label: "See the menu", clicks: "2,884", pct: 63 },
  { label: "Read reviews", clicks: "1,506", pct: 34 },
];

export function StatsDashboard() {
  return (
    <PhoneStage accent={tokens.cyan} chips={<StatsChips />}>
      <div className="no-sb h-full overflow-y-auto text-white">
        <StatusBar />

        {/* Header */}
        <div className="flex items-center justify-between px-5 pt-3">
          <div>
            <div className="text-[10px] font-semibold uppercase tracking-[0.2em] text-white/45">
              Analytics
            </div>
            <div className="font-grotesk text-[16px] font-bold">Last 7 days</div>
          </div>
          <span
            className="flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-bold"
            style={{ background: "rgba(30,215,96,.16)", color: tokens.green }}
          >
            <span className="h-1.5 w-1.5 rounded-full" style={{ background: tokens.green, animation: "pulse-dot 1.4s infinite" }} />
            Live
          </span>
        </div>

        {/* Hero metric */}
        <div className="mx-4 mt-3 rounded-2xl p-4" style={{ background: "linear-gradient(150deg, rgba(61,107,255,.22), rgba(27,212,217,.1))", border: "1px solid rgba(255,255,255,.1)" }}>
          <div className="flex items-center gap-1.5 text-[11px] text-white/60">
            <MousePointerClick className="h-3.5 w-3.5" /> Total clicks
          </div>
          <div className="mt-1 flex items-end gap-2">
            <span className="font-grotesk text-[30px] font-bold leading-none">12,847</span>
            <span className="mb-0.5 flex items-center text-[11px] font-bold" style={{ color: tokens.green }}>
              <ArrowUpRight className="h-3.5 w-3.5" />18%
            </span>
          </div>
          {/* Bar chart */}
          <div className="mt-3 flex h-16 items-end gap-1.5">
            {bars.map((h, i) => (
              <div
                key={i}
                className="flex-1 rounded-t-sm"
                style={{
                  height: `${h}%`,
                  background:
                    i === bars.length - 1
                      ? `linear-gradient(${tokens.cyan}, ${tokens.blue})`
                      : "rgba(255,255,255,.16)",
                }}
              />
            ))}
          </div>
        </div>

        {/* Stat grid */}
        <div className="mt-3 grid grid-cols-3 gap-2 px-4">
          {[
            { icon: Users, label: "Visitors", value: "8.2k" },
            { icon: TrendingUp, label: "CTR", value: "6.4%" },
            { icon: Globe, label: "Top geo", value: "US" },
          ].map(({ icon: Icon, label, value }) => (
            <div
              key={label}
              className="rounded-xl px-2.5 py-2.5"
              style={{ background: "rgba(255,255,255,.05)", border: "1px solid rgba(255,255,255,.08)" }}
            >
              <Icon className="h-3.5 w-3.5" style={{ color: tokens.cyan }} />
              <div className="mt-1.5 font-grotesk text-[15px] font-bold">{value}</div>
              <div className="text-[9px] text-white/45">{label}</div>
            </div>
          ))}
        </div>

        {/* Top links */}
        <div className="mt-4 px-4 pb-6">
          <div className="mb-2 text-[10px] font-semibold uppercase tracking-[0.16em] text-white/45">
            Top links
          </div>
          <div className="space-y-2.5">
            {topLinks.map((l) => (
              <div key={l.label}>
                <div className="flex items-center justify-between text-[12px]">
                  <span className="font-medium">{l.label}</span>
                  <span className="font-grotesk font-bold text-white/80">{l.clicks}</span>
                </div>
                <div className="mt-1 h-1.5 w-full overflow-hidden rounded-full" style={{ background: "rgba(255,255,255,.08)" }}>
                  <div
                    className="h-full rounded-full"
                    style={{ width: `${l.pct}%`, background: `linear-gradient(90deg, ${tokens.blue}, ${tokens.cyan})` }}
                  />
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </PhoneStage>
  );
}

function StatsChips() {
  return (
    <>
      <Chip tone="green" style={{ top: 36, right: -56 }}>
        <ArrowUpRight className="h-3.5 w-3.5" />
        +247 today
      </Chip>
      <Chip tone="blue" style={{ top: 250, left: -52 }}>
        <Users className="h-3.5 w-3.5" />
        8.2k visitors
      </Chip>
      <BuilderChip style={{ bottom: 8, right: -40 }} />
    </>
  );
}
