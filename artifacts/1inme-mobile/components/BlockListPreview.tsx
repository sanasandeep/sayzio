import { Text, View } from "react-native";

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
                      color={isChecklist ? "#16a34a" : primary}
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

  return (
    <View style={{ gap: isCards ? 8 : 4 }}>
      {rows.map((it, i) => {
        const featuredHighlight = isFeatured && it.featured;
        const containerStyle = {
          padding: isCards || featuredHighlight ? 10 : 6,
          borderRadius: 10,
          borderWidth: isCards || featuredHighlight ? 1 : 0,
          borderColor: featuredHighlight ? primary : border,
          backgroundColor: featuredHighlight
            ? primary + "11"
            : isCards
              ? colors.card
              : "transparent",
          gap: 4,
        };
        const iconNode = it.icon ? (
          <AppIcon name={it.icon} size={14} color={primary} />
        ) : null;
        const includedNode = isComparison ? (
          <AppIcon
            name={it.included ? "fas fa-check" : "fas fa-times"}
            size={14}
            color={it.included ? "#16a34a" : "#dc2626"}
          />
        ) : null;
        const name = it.name || "Item";

        if (isMenu) {
          return (
            <View key={i} style={containerStyle}>
              <View style={{ flexDirection: "row", alignItems: "flex-start", gap: 8 }}>
                {iconNode ? <View style={{ marginTop: 2 }}>{iconNode}</View> : null}
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
                {iconNode}
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

        // Classic (and Featured for non-highlighted rows): name ······ price
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
            {iconNode}
            <Text
              style={{ color: fg, fontWeight: "600", fontSize: 13 }}
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
            {featuredHighlight ? (
              <Text style={{ color: primary, fontSize: 11 }}>★</Text>
            ) : null}
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
