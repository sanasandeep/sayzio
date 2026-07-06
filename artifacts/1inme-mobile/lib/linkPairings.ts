import type { Feather } from "@expo/vector-icons";

/**
 * "Perfect pairings" cross-promo catalog item, as returned by the public
 * link-type API endpoints under a `pairings` key. The shape mirrors the web
 * source of truth (Laravel `SitePagesContent::linkTypePairingsCatalog`):
 * each item is a complementary link type worth suggesting on this page.
 *
 * `icon` is a FontAwesome class name (e.g. "fa-star") used by the web
 * renderer; mobile ignores it and derives a Feather glyph from `type`
 * instead (FontAwesome brands/solids aren't available in Expo).
 */
export type LinkTypePairing = {
  name: string;
  type: string;
  icon: string;
  benefit: string;
};

type IconName = keyof typeof Feather.glyphMap;

// Catalog `type` → Feather glyph. Kept in step with the web icon intent and,
// where a matching create kind exists, with lib/linkKinds.ts.
const PAIRING_ICONS: Record<string, IconName> = {
  calendar: "calendar",
  ics: "calendar",
  biolink: "grid",
  reviews: "star",
  vcf: "user",
  resume: "file-text",
  brand_kit: "feather",
  qr: "maximize",
  restaurant_menu: "coffee",
  store_menu: "shopping-bag",
};

export function pairingIcon(type: string): IconName {
  return PAIRING_ICONS[type] ?? "arrow-up-right";
}

/**
 * Catalog `type` → the mobile create-flow path it should deep-link to.
 * Mirrors the web `SitePagesContent::linkTypePairingCreateRoute` map,
 * translated to the Expo Router screens that own each create flow:
 *   - most types share the `/links/create/[kind]` screen (kind = mobile
 *     LinkKind, so `vcf` → `vcard`, `ics` (Event) → `calendar`)
 *   - the followable `calendar` type and `qr` have their own screens
 * Unknown types fall back to the generic Create tab.
 */
export function pairingCreatePath(type: string): string {
  switch (type) {
    case "biolink":
      return "/links/create/biolink";
    case "reviews":
      return "/links/create/reviews";
    case "vcf":
      return "/links/create/vcard";
    case "brand_kit":
      return "/links/create/brand_kit";
    case "resume":
      return "/links/create/resume";
    case "restaurant_menu":
      return "/links/create/restaurant_menu";
    case "store_menu":
      return "/links/create/store_menu";
    case "ics":
      return "/links/create/calendar";
    case "calendar":
      return "/calendars/edit";
    case "qr":
      return "/qr-studio";
    default:
      return "/(tabs)/create";
  }
}
