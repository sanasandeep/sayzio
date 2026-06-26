import { Image, StyleSheet, Text, View } from "react-native";

import { AppIcon } from "@/components/AppIcon";
import { useColors } from "@/hooks/useColors";

// Shared row renderers for list / list_numbered / list_pricing blocks.
// Used by BOTH the public biolink page (`app/biolink/[handle].tsx`) and
// the live preview in the block editor (`app/links/[id]/blocks/[blockId].tsx`).
// Centralizing the visual logic here is what guarantees the editor's
// "Preview" panel matches what visitors actually see — every style
// variant, picked icon, and pricing-row treatment is rendered through
// the same code path on both surfaces.

export type ListBlockItem = { text: string; icon?: string };
export type PricingBlockItem = {
  name: string;
  description: string;
  price: string;
  period: string;
  included: boolean;
  featured: boolean;
  thumbnail: string;
  icon: string;
};

type Palette = ReturnType<typeof useColors>;

// Trim items the way the editor save path and public renderer agree on:
// keep anything with text or a picked icon. Empty rows would otherwise
// render as a stray bullet/number with no label.
export function visibleListItems(items: ListBlockItem[]): ListBlockItem[] {
  return items.filter((it) => (it.text ?? "").trim() !== "" || (it.icon ?? "") !== "");
}

// Same predicate the editor uses when saving — keep any pricing row
// with any meaningful field set, including just a flag toggle.
export function visiblePricingItems(items: PricingBlockItem[]): PricingBlockItem[] {
  return items.filter(
    (it) =>
      it.name.trim() !== "" ||
      it.price.trim() !== "" ||
      it.period.trim() !== "" ||
      it.description.trim() !== "" ||
      it.thumbnail.trim() !== "" ||
      it.icon.trim() !== "" ||
      it.featured ||
      !it.included,
  );
}

// Renders the inner rows for a list / list_numbered block. Callers wrap
// this in whatever container they want (the public page uses a card, the
// editor preview uses a dashed panel).
export function ListBlockView({
  kind,
  styleKey,
  defaultIcon,
  items,
  colors,
}: {
  kind: "list" | "numbered";
  styleKey: string;
  defaultIcon: string;
  items: ListBlockItem[];
  colors: Palette;
}) {
  const muted = colors.mutedForeground;
  const fg = colors.foreground;
  const primary = colors.primary;
  const border = colors.border;

  const rows = visibleListItems(items);
  if (rows.length === 0) return null;

  const isChecklist = kind === "list" && styleKey === "checklist";
  const isTimeline = kind === "list" && styleKey === "timeline";
  const isBoxed = styleKey === "boxed";
  const isDivided = styleKey === "divided";
  const isPill = kind === "numbered" && styleKey === "pill";
  const isBadgeSquare = kind === "numbered" && styleKey === "badge_square";
  const isOutlined = kind === "numbered" && styleKey === "outlined";

  return (
    <View style={{ gap: isBoxed ? 8 : 0 }}>
      {rows.map((it, i) => {
        // Per-item icon wins, falling back to the block default; checklist
        // style forces a green check regardless of what was picked.
        const iconName = isChecklist
          ? "fas fa-check"
          : kind === "list"
            ? (it.icon || defaultIcon || "fas fa-check")
            : "";
        const showDivider = isDivided && i < rows.length - 1;
        const numberLabel = `${i + 1}`;
        return (
          <View key={i}>
            <View
              style={{
                flexDirection: "row",
                alignItems: "flex-start",
                gap: 8,
                paddingVertical: isBoxed ? 8 : 6,
                paddingHorizontal: isBoxed ? 10 : 0,
                borderWidth: isBoxed || isOutlined ? 1 : 0,
                borderColor: border,
                borderRadius: isBoxed || isOutlined ? 10 : 0,
                backgroundColor: isBoxed ? colors.card : "transparent",
              }}
            >
              {kind === "list" ? (
                isTimeline ? (
                  <View style={{ alignItems: "center", width: 18 }}>
                    <View
                      style={{
                        width: 8,
                        height: 8,
                        borderRadius: 4,
                        backgroundColor: primary,
                        marginTop: 4,
                      }}
                    />
                    {i < rows.length - 1 ? (
                      <View
                        style={{
                          width: 1,
                          flex: 1,
                          backgroundColor: border,
                          marginTop: 2,
                          minHeight: 12,
                        }}
                      />
                    ) : null}
                  </View>
                ) : (
                  <View
                    style={{
                      width: 22,
                      height: 22,
                      alignItems: "center",
                      justifyContent: "center",
                    }}
                  >
                    <AppIcon
                      name={iconName}
                      size={14}
                      color={isChecklist ? colors.success : primary}
                    />
                  </View>
                )
              ) : (
                <View
                  style={{
                    minWidth: isOutlined ? 28 : 22,
                    height: isOutlined ? 28 : 22,
                    paddingHorizontal: isPill ? 8 : 0,
                    borderRadius: isPill ? 999 : isBadgeSquare ? 4 : isOutlined ? 6 : 0,
                    backgroundColor:
                      isPill || isBadgeSquare ? primary : "transparent",
                    borderWidth: isOutlined ? 1.5 : 0,
                    borderColor: primary,
                    alignItems: "center",
                    justifyContent: "center",
                  }}
                >
                  <Text
                    style={{
                      color:
                        isPill || isBadgeSquare
                          ? "#fff"
                          : isOutlined
                            ? primary
                            : muted,
                      fontWeight: "700",
                      fontSize: isOutlined ? 13 : 12,
                    }}
                  >
                    {isPill || isBadgeSquare || isOutlined
                      ? numberLabel
                      : `${numberLabel}.`}
                  </Text>
                </View>
              )}
              <Text
                style={{
                  color: fg,
                  fontSize: 13,
                  flex: 1,
                  lineHeight: 18,
                }}
              >
                {it.text}
              </Text>
            </View>
            {showDivider ? (
              <View
                style={{
                  height: 1,
                  backgroundColor: border,
                  marginVertical: 2,
                }}
              />
            ) : null}
          </View>
        );
      })}
    </View>
  );
}

// Renders the inner rows for a list_pricing block across all five
// style variants. Callers wrap this in whatever container they want.
export function PricingBlockView({
  styleKey,
  items,
  colors,
}: {
  styleKey: string;
  items: PricingBlockItem[];
  colors: Palette;
}) {
  const muted = colors.mutedForeground;
  const fg = colors.foreground;
  const primary = colors.primary;
  const border = colors.border;

  const rows = visiblePricingItems(items);
  if (rows.length === 0) return null;

  const isMenu = styleKey === "menu";
  const isCards = styleKey === "cards";
  const isComparison = styleKey === "comparison";
  const isFeatured = styleKey === "featured";

  // Web's "featured" variant pulls one featured row out as a hero card and
  // stacks the rest in a compact list below. Mirror that here so the
  // public mobile page (and the editor preview that shares this view)
  // matches the web treatment instead of just colour-tinting an inline row.
  if (isFeatured) {
    const heroIndex = rows.findIndex((r) => r.featured);
    const hero = heroIndex >= 0 ? rows[heroIndex] : rows[0];
    const others = rows.filter((_, i) => i !== (heroIndex >= 0 ? heroIndex : 0));
    return (
      <View style={{ gap: 8 }}>
        {hero ? (
          <View
            style={{
              padding: 14,
              borderRadius: 14,
              borderWidth: 1,
              borderColor: primary,
              backgroundColor: primary + "22",
              alignItems: "center",
              gap: 4,
            }}
          >
            <Text style={{ color: primary, fontSize: 10, fontWeight: "800", letterSpacing: 0.5 }}>
              ★ FEATURED
            </Text>
            <Text style={{ color: fg, fontWeight: "800", fontSize: 16 }} numberOfLines={1}>
              {hero.name || "Item"}
            </Text>
            {hero.description ? (
              <Text style={{ color: muted, fontSize: 12, textAlign: "center" }}>
                {hero.description}
              </Text>
            ) : null}
            <Text style={{ color: primary, fontWeight: "800", fontSize: 22, marginTop: 4 }}>
              {hero.price}
              {hero.period ? (
                <Text style={{ color: muted, fontSize: 12, fontWeight: "400" }}>
                  {" "}{hero.period}
                </Text>
              ) : null}
            </Text>
          </View>
        ) : null}
        {others.length > 0 ? (
          <View style={{ borderRadius: 10, borderWidth: 1, borderColor: border, overflow: "hidden" }}>
            {others.map((it, i) => (
              <View
                key={i}
                style={{
                  flexDirection: "row",
                  alignItems: "baseline",
                  gap: 6,
                  paddingHorizontal: 10,
                  paddingVertical: 8,
                  borderTopWidth: i === 0 ? 0 : StyleSheet.hairlineWidth,
                  borderTopColor: border,
                }}
              >
                <Text style={{ color: fg, fontWeight: "600", fontSize: 13 }} numberOfLines={1}>
                  {it.name || "Item"}
                </Text>
                {it.description ? (
                  <Text style={{ color: muted, fontSize: 11, flexShrink: 1 }} numberOfLines={1}>
                    — {it.description}
                  </Text>
                ) : null}
                <Text
                  style={{ color: muted, fontSize: 11, flex: 1, overflow: "hidden" }}
                  numberOfLines={1}
                >
                  {" "}··················································
                </Text>
                <Text style={{ color: primary, fontWeight: "700", fontSize: 13 }}>
                  {it.price}
                  {it.period ? (
                    <Text style={{ color: muted, fontSize: 11 }}>{it.period}</Text>
                  ) : null}
                </Text>
              </View>
            ))}
          </View>
        ) : null}
      </View>
    );
  }

  return (
    <View style={{ gap: isCards ? 8 : 4 }}>
      {rows.map((it, i) => {
        const containerStyle = {
          padding: isCards ? 10 : 6,
          borderRadius: 10,
          borderWidth: isCards ? 1 : 0,
          borderColor: border,
          backgroundColor: isCards ? colors.card : "transparent",
          gap: 4,
        };
        const iconNode = it.icon ? (
          <AppIcon name={it.icon} size={14} color={primary} />
        ) : null;
        // For menu/cards, the web shows a thumbnail badge (and falls back
        // to an icon-in-tinted-square when the row only has an icon).
        // We mirror that so creators get the same chrome on mobile.
        const showThumbBadge = (isMenu || isCards) && (!!it.thumbnail || !!it.icon);
        const thumbSize = 36;
        const thumbBadge = showThumbBadge ? (
          it.thumbnail ? (
            <Image
              source={{ uri: it.thumbnail }}
              style={{
                width: thumbSize,
                height: thumbSize,
                borderRadius: 8,
                backgroundColor: colors.muted,
              }}
            />
          ) : (
            <View
              style={{
                width: thumbSize,
                height: thumbSize,
                borderRadius: 8,
                backgroundColor: primary + "22",
                alignItems: "center",
                justifyContent: "center",
              }}
            >
              <AppIcon name={it.icon} size={16} color={primary} />
            </View>
          )
        ) : null;
        const includedNode = isComparison ? (
          <AppIcon
            name={it.included ? "fas fa-check" : "fas fa-times"}
            size={14}
            color={it.included ? colors.success : colors.destructive}
          />
        ) : null;
        const name = it.name || "Item";

        if (isMenu) {
          return (
            <View key={i} style={containerStyle}>
              <View style={{ flexDirection: "row", alignItems: "flex-start", gap: 8 }}>
                {thumbBadge}
                <View style={{ flex: 1 }}>
                  <View
                    style={{
                      flexDirection: "row",
                      justifyContent: "space-between",
                      alignItems: "baseline",
                    }}
                  >
                    <Text
                      style={{ color: fg, fontWeight: "700", fontSize: 13, flex: 1 }}
                      numberOfLines={1}
                    >
                      {name}
                    </Text>
                    <Text style={{ color: primary, fontWeight: "700", fontSize: 13 }}>
                      {it.price}
                      {it.period ? (
                        <Text style={{ color: muted, fontSize: 11 }}>{it.period}</Text>
                      ) : null}
                    </Text>
                  </View>
                  {it.description ? (
                    <Text style={{ color: muted, fontSize: 11, marginTop: 2 }}>
                      {it.description}
                    </Text>
                  ) : null}
                </View>
              </View>
            </View>
          );
        }

        if (isCards) {
          return (
            <View key={i} style={containerStyle}>
              <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                {thumbBadge}
                <Text
                  style={{ color: fg, fontWeight: "700", fontSize: 13, flex: 1 }}
                  numberOfLines={1}
                >
                  {name}
                </Text>
                {it.featured ? (
                  <Text style={{ color: primary, fontSize: 11 }}>★</Text>
                ) : null}
              </View>
              <Text style={{ color: primary, fontWeight: "700", fontSize: 18 }}>
                {it.price}
                {it.period ? (
                  <Text style={{ color: muted, fontSize: 11, fontWeight: "400" }}>
                    {" "}{it.period}
                  </Text>
                ) : null}
              </Text>
              {it.description ? (
                <Text style={{ color: muted, fontSize: 11 }}>{it.description}</Text>
              ) : null}
            </View>
          );
        }

        if (isComparison) {
          return (
            <View
              key={i}
              style={{
                ...containerStyle,
                flexDirection: "row",
                alignItems: "center",
                gap: 8,
              }}
            >
              {includedNode}
              {iconNode}
              <Text
                style={{
                  color: it.included ? fg : muted,
                  fontWeight: "600",
                  fontSize: 13,
                  flex: 1,
                  textDecorationLine: it.included ? "none" : "line-through",
                }}
                numberOfLines={1}
              >
                {name}
              </Text>
              {it.price ? (
                <Text style={{ color: primary, fontWeight: "700", fontSize: 13 }}>
                  {it.price}
                  {it.period ? (
                    <Text style={{ color: muted, fontSize: 11 }}>{it.period}</Text>
                  ) : null}
                </Text>
              ) : null}
            </View>
          );
        }

        // Classic: name ······ price (with optional icon dot on the left).
        return (
          <View
            key={i}
            style={{
              ...containerStyle,
              flexDirection: "row",
              alignItems: "center",
              gap: 8,
              opacity: it.included ? 1 : 0.5,
            }}
          >
            {iconNode}
            <Text
              style={{
                color: fg,
                fontWeight: "600",
                fontSize: 13,
                textDecorationLine: it.included ? "none" : "line-through",
              }}
              numberOfLines={1}
            >
              {name}
            </Text>
            <Text
              style={{
                color: muted,
                fontSize: 11,
                flex: 1,
                overflow: "hidden",
              }}
              numberOfLines={1}
            >
              {" "}··················································
            </Text>
            <Text style={{ color: primary, fontWeight: "700", fontSize: 13 }}>
              {it.price}
              {it.period ? (
                <Text style={{ color: muted, fontSize: 11 }}>{it.period}</Text>
              ) : null}
            </Text>
          </View>
        );
      })}
    </View>
  );
}

// Coerce raw block.settings.items into the typed shape used by the views.
// Public renderer reads from untrusted settings JSON, so we normalize
// strings/objects/missing fields the same way the editor does.
export function normalizeListItems(raw: unknown): ListBlockItem[] {
  if (!Array.isArray(raw)) return [];
  return raw.map((i) => {
    if (typeof i === "string") return { text: i, icon: "" };
    if (i && typeof i === "object") {
      const o = i as Record<string, unknown>;
      return {
        text: typeof o.text === "string"
          ? o.text
          : typeof o.label === "string"
            ? o.label
            : "",
        icon: typeof o.icon === "string" ? o.icon : "",
      };
    }
    return { text: "", icon: "" };
  });
}

export function normalizePricingItems(raw: unknown): PricingBlockItem[] {
  if (!Array.isArray(raw)) return [];
  return raw.map((i) => {
    const o = (i && typeof i === "object" ? i : {}) as Record<string, unknown>;
    return {
      name: typeof o.name === "string" ? o.name : "",
      description: typeof o.description === "string" ? o.description : "",
      price: typeof o.price === "string" ? o.price : "",
      period: typeof o.period === "string" ? o.period : "",
      included: o.included === undefined ? true : !!o.included,
      featured: !!o.featured,
      thumbnail: typeof o.thumbnail === "string" ? o.thumbnail : "",
      icon: typeof o.icon === "string" ? o.icon : "",
    };
  });
}
