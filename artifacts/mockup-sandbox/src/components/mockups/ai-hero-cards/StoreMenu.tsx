import React from "react";
import { ShoppingBag, Plus, Star } from "lucide-react";
import {
  PhoneStage,
  StatusBar,
  Chip,
  BuilderChip,
  tokens,
  img,
} from "./_shell";

const cats = ["Coffee", "Beans", "Gear"];

const items = [
  { name: "Oat Flat White", desc: "Double shot · 12oz", price: "4.50", photo: "latte.webp" },
  { name: "House Blend", desc: "Whole bean · 1lb", price: "18.00", photo: "beans.webp" },
  { name: "Cold Brew Kit", desc: "Brews 6 cups", price: "24.00", photo: "live.webp" },
];

export function StoreMenu() {
  return (
    <PhoneStage accent={tokens.blue} chips={<StoreChips />}>
      <div className="no-sb h-full overflow-y-auto text-white">
        <StatusBar />

        {/* Header */}
        <div className="flex items-center justify-between px-5 pt-3">
          <div>
            <div className="text-[10px] font-semibold uppercase tracking-[0.2em] text-white/45">
              Order online
            </div>
            <div className="font-grotesk text-[16px] font-bold">Daybreak Shop</div>
          </div>
          <span className="relative flex h-9 w-9 items-center justify-center rounded-xl" style={{ background: "rgba(255,255,255,.06)", border: "1px solid rgba(255,255,255,.1)" }}>
            <ShoppingBag className="h-4 w-4" />
            <span className="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full text-[9px] font-bold text-white" style={{ background: tokens.blue }}>
              3
            </span>
          </span>
        </div>

        {/* Category tabs */}
        <div className="mt-3 flex gap-2 px-4">
          {cats.map((c, i) => (
            <span
              key={c}
              className="rounded-full px-3 py-1 text-[11px] font-semibold"
              style={
                i === 0
                  ? { background: tokens.blue, color: "#fff" }
                  : { background: "rgba(255,255,255,.06)", color: "rgba(255,255,255,.6)", border: "1px solid rgba(255,255,255,.08)" }
              }
            >
              {c}
            </span>
          ))}
        </div>

        {/* Items */}
        <div className="mt-3 space-y-2.5 px-4">
          {items.map((it) => (
            <div
              key={it.name}
              className="flex items-center gap-3 rounded-2xl p-2.5"
              style={{ background: "rgba(255,255,255,.05)", border: "1px solid rgba(255,255,255,.08)" }}
            >
              <img src={img(it.photo)} alt="" className="h-14 w-14 shrink-0 rounded-xl object-cover" />
              <div className="min-w-0 flex-1">
                <div className="truncate text-[13px] font-semibold">{it.name}</div>
                <div className="truncate text-[10px] text-white/45">{it.desc}</div>
                <div className="mt-1 font-grotesk text-[13px] font-bold" style={{ color: tokens.cyan }}>
                  ${it.price}
                </div>
              </div>
              <button
                className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-white"
                style={{ background: tokens.blue }}
              >
                <Plus className="h-4 w-4" />
              </button>
            </div>
          ))}
        </div>

        {/* Rating strip */}
        <div className="mx-4 mt-3 flex items-center justify-center gap-1.5 rounded-xl py-2 text-[11px] font-semibold text-white/70" style={{ background: "rgba(255,255,255,.04)" }}>
          <Star className="h-3.5 w-3.5" style={{ color: "#ffcf4d" }} fill="#ffcf4d" />
          4.9 · 1,200+ orders this month
        </div>

        {/* Checkout */}
        <div className="px-4 pb-6 pt-3">
          <button
            className="flex w-full items-center justify-between rounded-2xl px-4 py-3 text-[13px] font-bold text-white"
            style={{ background: `linear-gradient(90deg, ${tokens.blue}, ${tokens.cyan})` }}
          >
            <span>View cart · 3 items</span>
            <span className="font-grotesk">$27.00</span>
          </button>
        </div>
      </div>
    </PhoneStage>
  );
}

function StoreChips() {
  return (
    <>
      <Chip tone="green" style={{ top: 34, right: -58 }}>
        <span className="h-2 w-2 rounded-full" style={{ background: tokens.green }} />
        New order
      </Chip>
      <Chip tone="glass" style={{ top: 232, left: -50 }}>
        <ShoppingBag className="h-3.5 w-3.5" style={{ color: tokens.cyan }} />
        $27.00
      </Chip>
      <BuilderChip style={{ bottom: 8, right: -40 }} />
    </>
  );
}
