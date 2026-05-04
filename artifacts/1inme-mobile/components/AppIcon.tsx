import { Feather } from "@expo/vector-icons";
import * as React from "react";

export type FeatherName = keyof typeof Feather.glyphMap;

export type AppIconProps = {
  name: FeatherName | string;
  size?: number;
  color?: string;
  style?: React.ComponentProps<typeof Feather>["style"];
};

const FA_TO_FEATHER: Record<string, FeatherName> = {
  "link": "link",
  "share-nodes": "share-2",
  "square-share-nodes": "share-2",
  "share": "share",
  "paper-plane": "send",
  "globe": "globe",
  "house": "home",
  "user": "user",
  "users": "users",
  "user-group": "users",
  "user-plus": "user-plus",
  "user-tie": "user",
  "user-graduate": "user",
  "id-card": "credit-card",
  "id-badge": "user-check",
  "address-card": "credit-card",
  "envelope": "mail",
  "bell": "bell",
  "comment": "message-circle",
  "comments": "message-square",
  "comment-dots": "message-circle",
  "message": "message-square",
  "phone": "phone",
  "heart": "heart",
  "star": "star",
  "bookmark": "bookmark",
  "tag": "tag",
  "tags": "tag",
  "flag": "flag",
  "thumbtack": "paperclip",
  "fire": "zap",
  "bolt": "zap",
  "rocket": "send",
  "magic-wand-sparkles": "star",
  "wand-magic-sparkles": "star",
  "sparkles": "star",
  "palette": "droplet",
  "paintbrush": "edit-2",
  "brush": "edit-2",
  "pen": "edit-2",
  "pen-nib": "edit-2",
  "pen-to-square": "edit",
  "pencil": "edit-3",
  "image": "image",
  "images": "image",
  "camera": "camera",
  "video": "video",
  "film": "film",
  "music": "music",
  "headphones": "headphones",
  "microphone": "mic",
  "podcast": "mic",
  "play": "play",
  "circle-play": "play-circle",
  "chart-line": "trending-up",
  "chart-bar": "bar-chart-2",
  "chart-pie": "pie-chart",
  "chart-column": "bar-chart",
  "chart-simple": "bar-chart",
  "arrow-trend-up": "trending-up",
  "square-poll-vertical": "bar-chart-2",
  "magnifying-glass": "search",
  "magnifying-glass-chart": "search",
  "eye": "eye",
  "bullseye": "target",
  "crosshairs": "crosshair",
  "gauge": "activity",
  "gauge-high": "activity",
  "shield": "shield",
  "shield-halved": "shield",
  "lock": "lock",
  "key": "key",
  "fingerprint": "lock",
  "user-shield": "shield",
  "cog": "settings",
  "gear": "settings",
  "gears": "settings",
  "sliders": "sliders",
  "toggle-on": "toggle-right",
  "wrench": "tool",
  "screwdriver-wrench": "tool",
  "toolbox": "tool",
  "hammer": "tool",
  "puzzle-piece": "package",
  "plug": "zap",
  "code": "code",
  "terminal": "terminal",
  "laptop-code": "monitor",
  "database": "database",
  "server": "server",
  "cloud": "cloud",
  "cloud-arrow-up": "upload-cloud",
  "cloud-arrow-down": "download-cloud",
  "upload": "upload",
  "download": "download",
  "file": "file",
  "file-lines": "file-text",
  "folder": "folder",
  "folder-open": "folder",
  "clipboard": "clipboard",
  "clipboard-list": "clipboard",
  "list": "list",
  "list-check": "check-square",
  "square-check": "check-square",
  "circle-check": "check-circle",
  "check": "check",
  "circle-info": "info",
  "circle-question": "help-circle",
  "life-ring": "life-buoy",
  "headset": "headphones",
  "handshake": "users",
  "people-group": "users",
  "thumbs-up": "thumbs-up",
  "trophy": "award",
  "medal": "award",
  "award": "award",
  "crown": "award",
  "gem": "award",
  "circle-dollar-to-slot": "dollar-sign",
  "dollar-sign": "dollar-sign",
  "coins": "dollar-sign",
  "money-bill": "dollar-sign",
  "money-bill-wave": "dollar-sign",
  "piggy-bank": "dollar-sign",
  "wallet": "credit-card",
  "credit-card": "credit-card",
  "cash-register": "shopping-cart",
  "percent": "percent",
  "cart-shopping": "shopping-cart",
  "bag-shopping": "shopping-bag",
  "store": "shopping-bag",
  "shop": "shopping-bag",
  "receipt": "file-text",
  "truck": "truck",
  "box": "box",
  "gift": "gift",
  "calendar": "calendar",
  "calendar-day": "calendar",
  "calendar-days": "calendar",
  "calendar-check": "calendar",
  "clock": "clock",
  "hourglass": "clock",
  "hourglass-half": "clock",
  "times": "x",
  "xmark": "x",
  "plus": "plus",
  "minus": "minus",
  "search": "search",
  "info": "info",
  "warning": "alert-triangle",
  "exclamation-triangle": "alert-triangle",
  "exclamation-circle": "alert-circle",
  "ban": "slash",
  "trash": "trash-2",
  "trash-can": "trash-2",
  "save": "save",
  "edit": "edit",
  "copy": "copy",
  "cut": "scissors",
  "paste": "clipboard",
  "share-square": "share",
  "external-link": "external-link",
  "external-link-alt": "external-link",
  "arrow-right": "arrow-right",
  "arrow-left": "arrow-left",
  "arrow-up": "arrow-up",
  "arrow-down": "arrow-down",
  "chevron-right": "chevron-right",
  "chevron-left": "chevron-left",
  "chevron-up": "chevron-up",
  "chevron-down": "chevron-down",
  "ellipsis": "more-horizontal",
  "ellipsis-vertical": "more-vertical",
  "bars": "menu",
  "grip": "grid",
  "th": "grid",
  "th-large": "grid",
  "qrcode": "grid",
  "barcode": "bar-chart-2",
  "wifi": "wifi",
  "signal": "wifi",
  "bluetooth": "bluetooth",
  "battery-full": "battery",
  "power-off": "power",
  "play-circle": "play-circle",
  "pause": "pause",
  "stop": "square",
  "forward": "fast-forward",
  "backward": "rewind",
  "volume-up": "volume-2",
  "volume-down": "volume-1",
  "volume-mute": "volume-x",
  "map": "map",
  "map-marker": "map-pin",
  "map-pin": "map-pin",
  "location-dot": "map-pin",
  "compass": "compass",
  "route": "map",
  "car": "navigation",
  "plane": "send",
  "envelope-open": "mail",
  "inbox": "inbox",
  "archive": "archive",
  "filter": "filter",
  "sort": "list",
  "refresh": "refresh-cw",
  "rotate": "rotate-cw",
  "sync": "refresh-cw",
  "redo": "rotate-cw",
  "undo": "rotate-ccw",
  "history": "clock",
  "circle-user": "user",
  "circle": "circle",
  "square": "square",
  "diamond": "octagon",
  "briefcase": "briefcase",
  "building": "home",
  "industry": "package",
  "graduation-cap": "award",
  "book": "book",
  "book-open": "book-open",
  "newspaper": "file-text",
  "lightbulb": "zap",
  "leaf": "feather",
  "tree": "feather",
  "seedling": "feather",
  "wind": "wind",
  "sun": "sun",
  "moon": "moon",
  "cloud-rain": "cloud-rain",
  "umbrella": "umbrella",
  "snowflake": "cloud-snow",
  "smile": "smile",
  "frown": "frown",
  "meh": "meh",
  "ticket": "tag",
  "shopping-cart": "shopping-cart",
  "shopping-bag": "shopping-bag",
  "qr-code": "grid",
  "qr": "grid",
};

const SOCIAL_FA_TO_FEATHER: Record<string, FeatherName> = {
  "facebook": "facebook",
  "facebook-f": "facebook",
  "twitter": "twitter",
  "x-twitter": "twitter",
  "instagram": "instagram",
  "linkedin": "linkedin",
  "linkedin-in": "linkedin",
  "github": "github",
  "youtube": "youtube",
  "google": "chrome",
  "apple": "command",
  "tiktok": "music",
  "pinterest": "image",
  "snapchat": "camera",
  "whatsapp": "message-circle",
  "telegram": "send",
  "discord": "message-square",
  "slack": "hash",
  "twitch": "tv",
  "reddit": "message-circle",
  "spotify": "music",
};

const FALLBACK: FeatherName = "circle";

const warned = new Set<string>();

/**
 * Resolve any icon string — whether a raw Feather glyph name, a
 * FontAwesome class string from the web (e.g. "fa-user", "fas fa-save",
 * "fab fa-google"), or even a stray space-separated set of FA classes —
 * to a Feather icon name we can actually render on the device.
 *
 * Unknown names are logged once and rendered as a sensible fallback
 * instead of vanishing as an empty glyph (the bug this resolver fixes).
 */
export function resolveIconName(input: string | null | undefined): FeatherName {
  if (!input) return FALLBACK;
  const raw = String(input).trim();
  if (!raw) return FALLBACK;

  // Direct Feather hit (already a valid native glyph).
  if ((Feather.glyphMap as Record<string, unknown>)[raw]) {
    return raw as FeatherName;
  }

  // Pull the meaningful class out of FontAwesome strings like
  //   "fas fa-user", "fa-solid fa-user", "fab fa-google", "fa-user".
  const tokens = raw.split(/\s+/);
  const faToken = tokens.find((t) => /^fa[srbld]?$/.test(t)) ?? null;
  const isFaContext =
    faToken !== null || tokens.some((t) => t.startsWith("fa-"));
  const nameToken =
    tokens.find((t) => t.startsWith("fa-")) ??
    (isFaContext ? tokens[tokens.length - 1] : raw);
  const stripped = nameToken.replace(/^fa-/, "");

  // Brand icons live in their own table.
  const isBrand = faToken === "fab" || raw.includes("fa-brands");
  if (isBrand && SOCIAL_FA_TO_FEATHER[stripped]) {
    return SOCIAL_FA_TO_FEATHER[stripped];
  }

  if (FA_TO_FEATHER[stripped]) return FA_TO_FEATHER[stripped];
  if (SOCIAL_FA_TO_FEATHER[stripped]) return SOCIAL_FA_TO_FEATHER[stripped];

  // Tolerate trivial casing/snake variants users may send.
  const norm = stripped.replace(/_/g, "-").toLowerCase();
  if (FA_TO_FEATHER[norm]) return FA_TO_FEATHER[norm];
  if ((Feather.glyphMap as Record<string, unknown>)[norm]) {
    return norm as FeatherName;
  }

  if (!warned.has(raw)) {
    warned.add(raw);
    if (typeof console !== "undefined") {
      console.warn(`[AppIcon] Unknown icon "${raw}" — falling back to "${FALLBACK}"`);
    }
  }
  return FALLBACK;
}

export function AppIcon({ name, size, color, style }: AppIconProps) {
  const resolved = resolveIconName(name);
  return <Feather name={resolved} size={size} color={color} style={style} />;
}

export default AppIcon;
