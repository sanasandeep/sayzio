import React from "react";
import {
  BadgeCheck,
  Store,
  Coffee,
  Star,
  ChevronRight,
  Phone,
  Mail,
  MapPin,
  Instagram,
  Youtube,
  Music2,
} from "lucide-react";
import {
  PhoneStage,
  StatusBar,
  Chip,
  BuilderChip,
  tokens,
  img,
} from "./_shell";

const links = [
  { icon: Store, label: "Shop the beans", sub: "Free shipping over $30" },
  { icon: Coffee, label: "See the menu", sub: "Seasonal drinks" },
  { icon: Star, label: "Read reviews", sub: "320 happy regulars", rating: "4.9" },
];

const socials = [Instagram, Music2, Youtube];

export function BiolinkPage() {
  return (
    <PhoneStage accent={tokens.blue} chips={<BiolinkChips />}>
      <div className="no-sb h-full overflow-y-auto text-white">
        <StatusBar />

        {/* Profile */}
        <div className="flex flex-col items-center px-5 pt-3 text-center">
          <div className="relative">
            <img
              src={img("avatar-coffee.webp")}
              alt=""
              className="h-[68px] w-[68px] rounded-full object-cover ring-2 ring-white/15"
            />
            <span
              className="absolute -bottom-0.5 -right-0.5 flex h-5 w-5 items-center justify-center rounded-full"
              style={{ background: tokens.screen }}
            >
              <BadgeCheck className="h-5 w-5" style={{ color: tokens.blue }} fill={tokens.blue} stroke={tokens.screen} />
            </span>
          </div>
          <div className="mt-2.5 flex items-center gap-1.5">
            <span className="font-grotesk text-[17px] font-bold">Daybreak Coffee</span>
          </div>
          <p className="mt-1 text-[11px] text-white/55">Roasted fresh · shipped daily</p>
        </div>

        {/* Link rows */}
        <div className="mt-4 space-y-2.5 px-4">
          {links.map(({ icon: Icon, label, sub, rating }) => (
            <div
              key={label}
              className="flex items-center gap-3 rounded-2xl px-3 py-3"
              style={{
                background: "rgba(255,255,255,.05)",
                border: "1px solid rgba(255,255,255,.08)",
              }}
            >
              <span
                className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                style={{
                  background: "rgba(61,107,255,.16)",
                  color: tokens.blue,
                }}
              >
                <Icon className="h-4 w-4" />
              </span>
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-1.5 text-[13px] font-semibold">
                  {label}
                  {rating && (
                    <span className="flex items-center gap-0.5 text-[10px] font-bold" style={{ color: "#ffcf4d" }}>
                      <Star className="h-3 w-3" fill="#ffcf4d" stroke="#ffcf4d" />
                      {rating}
                    </span>
                  )}
                </div>
                <div className="truncate text-[10px] text-white/45">{sub}</div>
              </div>
              <ChevronRight className="h-4 w-4 text-white/30" />
            </div>
          ))}
        </div>

        {/* Gallery */}
        <div className="mt-3 grid grid-cols-3 gap-1.5 px-4">
          {["latte.webp", "beans.webp", "live.webp"].map((g) => (
            <div key={g} className="aspect-square overflow-hidden rounded-xl">
              <img src={img(g)} alt="" className="h-full w-full object-cover" />
            </div>
          ))}
        </div>

        {/* Contacts */}
        <div className="mt-3 grid grid-cols-3 gap-2 px-4">
          {[
            { icon: Phone, label: "Call" },
            { icon: Mail, label: "Email" },
            { icon: MapPin, label: "Visit" },
          ].map(({ icon: Icon, label }) => (
            <button
              key={label}
              className="flex flex-col items-center gap-1 rounded-xl py-2.5 text-[10px] font-semibold text-white/80"
              style={{ background: "rgba(255,255,255,.05)", border: "1px solid rgba(255,255,255,.08)" }}
            >
              <Icon className="h-4 w-4" style={{ color: tokens.cyan }} />
              {label}
            </button>
          ))}
        </div>

        {/* Socials */}
        <div className="mt-4 flex items-center justify-center gap-3 pb-6">
          {socials.map((Icon, i) => (
            <span
              key={i}
              className="flex h-8 w-8 items-center justify-center rounded-full text-white/70"
              style={{ background: "rgba(255,255,255,.06)", border: "1px solid rgba(255,255,255,.08)" }}
            >
              <Icon className="h-4 w-4" />
            </span>
          ))}
        </div>
      </div>
    </PhoneStage>
  );
}

function BiolinkChips() {
  return (
    <>
      <Chip tone="green" style={{ top: 30, right: -54 }}>
        <span className="h-2 w-2 rounded-full" style={{ background: tokens.green }} />
        Page built
      </Chip>
      <Chip tone="glass" style={{ top: 168, left: -58 }}>
        <Star className="h-3.5 w-3.5" style={{ color: "#ffcf4d" }} fill="#ffcf4d" />
        4.9 · 320 reviews
      </Chip>
      <BuilderChip style={{ bottom: 8, right: -40 }} />
    </>
  );
}
