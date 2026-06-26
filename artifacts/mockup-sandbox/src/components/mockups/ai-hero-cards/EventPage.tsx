import React from "react";
import {
  Calendar,
  Clock,
  MapPin,
  Ticket,
  Share2,
  CalendarPlus,
} from "lucide-react";
import { PhoneStage, StatusBar, Chip, BuilderChip, tokens } from "./_shell";

const going = ["#3d6bff", "#1bd4d9", "#ff8a3d", "#1ed760", "#2aa7ff"];

export function EventPage() {
  return (
    <PhoneStage accent={tokens.blue} chips={<EventChips />}>
      <div className="no-sb h-full overflow-y-auto text-white">
        <StatusBar />

        {/* Cover */}
        <div
          className="relative mx-4 mt-2 h-36 overflow-hidden rounded-2xl"
          style={{ background: `linear-gradient(150deg, ${tokens.blue}, ${tokens.cyan} 120%)` }}
        >
          <div className="absolute inset-0" style={{ background: "radial-gradient(120% 80% at 80% 0%, rgba(255,255,255,.28), transparent)" }} />
          <div
            className="absolute left-3 top-3 flex flex-col items-center rounded-xl px-3 py-1.5 text-center"
            style={{ background: "rgba(8,8,15,.55)", backdropFilter: "blur(6px)" }}
          >
            <span className="text-[9px] font-bold uppercase tracking-widest text-white/70">Sat</span>
            <span className="font-grotesk text-[20px] font-bold leading-none">14</span>
            <span className="text-[9px] font-semibold text-white/70">Sep</span>
          </div>
          <span className="absolute bottom-3 right-3 rounded-full px-2.5 py-1 text-[10px] font-bold" style={{ background: "rgba(8,8,15,.5)" }}>
            Free entry
          </span>
        </div>

        {/* Title */}
        <div className="px-5 pt-3">
          <div className="text-[10px] font-semibold uppercase tracking-[0.18em]" style={{ color: tokens.cyan }}>
            Live event
          </div>
          <h1 className="mt-1 font-grotesk text-[19px] font-bold leading-tight">
            Latte Art Throwdown
          </h1>
          <p className="mt-1 text-[11px] text-white/55">Hosted by Daybreak Coffee</p>
        </div>

        {/* Meta rows */}
        <div className="mt-3 space-y-2 px-4">
          {[
            { icon: Calendar, main: "Saturday, Sep 14", sub: "2026" },
            { icon: Clock, main: "7:00 PM – 10:00 PM", sub: "Doors at 6:30" },
            { icon: MapPin, main: "Daybreak Roastery", sub: "123 Market St" },
          ].map(({ icon: Icon, main, sub }) => (
            <div
              key={main}
              className="flex items-center gap-3 rounded-xl px-3 py-2.5"
              style={{ background: "rgba(255,255,255,.05)", border: "1px solid rgba(255,255,255,.08)" }}
            >
              <span className="flex h-8 w-8 items-center justify-center rounded-lg" style={{ background: "rgba(61,107,255,.16)", color: tokens.blue }}>
                <Icon className="h-4 w-4" />
              </span>
              <div>
                <div className="text-[12px] font-semibold">{main}</div>
                <div className="text-[10px] text-white/45">{sub}</div>
              </div>
            </div>
          ))}
        </div>

        {/* Attendees */}
        <div className="mt-3 flex items-center gap-3 px-5">
          <div className="flex -space-x-2.5">
            {going.map((c, i) => (
              <span
                key={i}
                className="h-7 w-7 rounded-full ring-2"
                style={{ background: c, boxShadow: `0 0 0 2px ${tokens.screen}` }}
              />
            ))}
          </div>
          <span className="text-[11px] font-semibold text-white/70">+128 going</span>
        </div>

        {/* RSVP */}
        <div className="px-4 pb-6 pt-4">
          <button
            className="flex w-full items-center justify-center gap-2 rounded-2xl py-3 text-[13px] font-bold text-white"
            style={{ background: `linear-gradient(90deg, ${tokens.blue}, ${tokens.cyan})` }}
          >
            <Ticket className="h-4 w-4" /> RSVP — Save my spot
          </button>
          <div className="mt-2 grid grid-cols-2 gap-2">
            {[
              { icon: CalendarPlus, label: "Add to calendar" },
              { icon: Share2, label: "Share" },
            ].map(({ icon: Icon, label }) => (
              <button
                key={label}
                className="flex items-center justify-center gap-1.5 rounded-xl py-2.5 text-[11px] font-semibold text-white/80"
                style={{ background: "rgba(255,255,255,.05)", border: "1px solid rgba(255,255,255,.08)" }}
              >
                <Icon className="h-3.5 w-3.5" style={{ color: tokens.cyan }} />
                {label}
              </button>
            ))}
          </div>
        </div>
      </div>
    </PhoneStage>
  );
}

function EventChips() {
  return (
    <>
      <Chip tone="green" style={{ top: 34, right: -56 }}>
        <span className="h-2 w-2 rounded-full" style={{ background: tokens.green }} />
        Page built
      </Chip>
      <Chip tone="blue" style={{ top: 220, left: -48 }}>
        <Ticket className="h-3.5 w-3.5" />
        128 going
      </Chip>
      <BuilderChip style={{ bottom: 8, right: -40 }} />
    </>
  );
}
