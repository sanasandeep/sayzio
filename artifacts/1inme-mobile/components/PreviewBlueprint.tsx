import { Feather } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import { useEffect, useState } from "react";
import { Image, StyleSheet, Text, View } from "react-native";
import Animated, {
  Easing,
  useAnimatedStyle,
  useSharedValue,
  withRepeat,
  withTiming,
} from "react-native-reanimated";

import { useColors } from "@/hooks/useColors";
import type { PreviewLayoutCell } from "@/lib/api/cardTemplates";

/**
 * Renders the same shape-aware mini-blueprint the web gallery shows for
 * a template's thumbnail when no static `thumbnail_url` is set.
 * Each row is a flex row whose cells flex-grow proportional to their
 * grid_span, and each cell's `shape` hint drives a small mock (avatar
 * circle, pill button, stacked input lines, social dot rows, etc.) so
 * the tile communicates the page/card's actual contents at a glance.
 *
 * Shared by the card-template gallery, the page-template picker and the
 * guided wizard's starting-design step so every surface reads the same.
 */
export function PreviewBlueprint({
  rows,
  height,
}: {
  rows: PreviewLayoutCell[][];
  height: number;
}) {
  if (!rows.length) return null;
  return (
    <View
      style={{
        width: "100%",
        height,
        paddingHorizontal: 6,
        paddingVertical: 5,
        gap: 3,
        justifyContent: "center",
      }}
    >
      {rows.map((row, ri) => (
        <View
          key={ri}
          style={{
            flexDirection: "row",
            gap: 3,
            alignItems: "center",
            width: "100%",
          }}
        >
          {row.map((cell, ci) => (
            <View
              key={ci}
              style={{
                flex: cell.span,
                alignItems: "center",
                justifyContent: "center",
              }}
            >
              <BlueprintCell cell={cell} />
            </View>
          ))}
        </View>
      ))}
    </View>
  );
}

/**
 * The PHP `TemplatePreviewLayoutBuilder` mirrors web styling and emits
 * CSS `linear-gradient(...)` strings for media/map-style cells. React
 * Native's `backgroundColor` cannot accept gradient strings — assigning
 * one would either be silently ignored or throw — so we parse those
 * here. Returns either a solid colour string (safe to drop into any
 * `backgroundColor` prop) or a structured gradient we can hand to
 * `expo-linear-gradient`. Plain rgba/hex/named colours pass through
 * untouched.
 */
type ParsedBg =
  | { kind: "solid"; color: string }
  | { kind: "gradient"; colors: string[]; angle: number };

export function parsePreviewBg(bg: string): ParsedBg {
  const trimmed = (bg ?? "").trim();
  if (!trimmed.toLowerCase().startsWith("linear-gradient")) {
    return { kind: "solid", color: trimmed || "rgba(255,255,255,0.10)" };
  }
  const open = trimmed.indexOf("(");
  const close = trimmed.lastIndexOf(")");
  if (open < 0 || close <= open) {
    return { kind: "solid", color: "rgba(255,255,255,0.10)" };
  }
  const body = trimmed.slice(open + 1, close);
  // Split on commas at depth 0 so commas inside `rgba(...)` stops are kept.
  const parts: string[] = [];
  let depth = 0;
  let cur = "";
  for (let i = 0; i < body.length; i++) {
    const ch = body[i];
    if (ch === "(") depth++;
    else if (ch === ")") depth--;
    if (ch === "," && depth === 0) {
      parts.push(cur.trim());
      cur = "";
    } else {
      cur += ch;
    }
  }
  if (cur.trim()) parts.push(cur.trim());
  let angle = 180;
  let stops = parts;
  if (parts.length > 0 && /^-?\d+(?:\.\d+)?\s*deg$/i.test(parts[0])) {
    angle = parseFloat(parts[0]);
    stops = parts.slice(1);
  }
  // A stop may be `<color> <position?>` — keep just the colour token.
  const colors = stops
    .map((s) => s.split(/\s+/)[0])
    .filter((c) => c.length > 0);
  if (colors.length < 2) {
    return { kind: "solid", color: colors[0] || "rgba(255,255,255,0.10)" };
  }
  return { kind: "gradient", colors, angle };
}

/**
 * Convert a CSS gradient angle (clockwise from "north", 0deg = bottom→
 * top, 90deg = left→right) into the start/end unit-square points
 * `expo-linear-gradient` expects.
 */
export function gradientPoints(angle: number) {
  const rad = (angle * Math.PI) / 180;
  const x = Math.sin(rad);
  const y = -Math.cos(rad);
  return {
    start: { x: 0.5 - x / 2, y: 0.5 - y / 2 },
    end: { x: 0.5 + x / 2, y: 0.5 + y / 2 },
  };
}

export function ShimmerOverlay({ radius = 0 }: { radius?: number }) {
  const { scheme } = useColors();
  const dark = scheme === "dark";
  const base = dark ? "rgba(255,255,255,0.06)" : "rgba(15,12,30,0.07)";
  const highlight = dark
    ? "rgba(255,255,255,0.16)"
    : "rgba(255,255,255,0.55)";
  const [width, setWidth] = useState(0);
  const progress = useSharedValue(-1);
  useEffect(() => {
    progress.value = withRepeat(
      withTiming(1, { duration: 1200, easing: Easing.inOut(Easing.ease) }),
      -1,
      false,
    );
  }, [progress]);
  const bandStyle = useAnimatedStyle(() => ({
    transform: [{ translateX: progress.value * (width || 1) }],
  }));
  return (
    <View
      pointerEvents="none"
      onLayout={(e) => setWidth(e.nativeEvent.layout.width)}
      style={[
        StyleSheet.absoluteFillObject,
        { backgroundColor: base, borderRadius: radius, overflow: "hidden" },
      ]}
    >
      {width > 0 ? (
        <Animated.View style={[StyleSheet.absoluteFillObject, bandStyle]}>
          <LinearGradient
            colors={["transparent", highlight, "transparent"]}
            start={{ x: 0, y: 0.5 }}
            end={{ x: 1, y: 0.5 }}
            style={{ width: "100%", height: "100%" }}
          />
        </Animated.View>
      ) : null}
    </View>
  );
}

export function BlueprintCell({ cell }: { cell: PreviewLayoutCell }) {
  const h = cell.h;
  const [imgFailed, setImgFailed] = useState(false);
  const [imgLoaded, setImgLoaded] = useState(false);
  const parsed = parsePreviewBg(cell.bg);
  // Always have a safe solid fallback to drop into RN `backgroundColor`
  // — even gradient cells use this for tiny inner elements (dots, lines)
  // where rendering a full `LinearGradient` wouldn't add value.
  const bg = parsed.kind === "solid" ? parsed.color : parsed.colors[0];
  switch (cell.shape) {
    case "heading":
      return (
        <View style={{ width: "100%", alignItems: "center", gap: 1 }}>
          {cell.text ? (
            <Text
              numberOfLines={1}
              style={{
                width: "100%",
                color: "rgba(255,255,255,0.92)",
                fontSize: 7,
                fontWeight: "700",
                textAlign: "center",
              }}
            >
              {cell.text}
            </Text>
          ) : (
            <View
              style={{
                backgroundColor: bg,
                height: h,
                width: "100%",
                borderRadius: 2,
              }}
            />
          )}
          {cell.sub && cell.sub_text ? (
            <Text
              numberOfLines={1}
              style={{
                width: "100%",
                color: "rgba(255,255,255,0.55)",
                fontSize: 5.5,
                textAlign: "center",
              }}
            >
              {cell.sub_text}
            </Text>
          ) : cell.sub ? (
            <View
              style={{
                backgroundColor: bg,
                height: Math.max(h - 6, 4),
                width: "55%",
                borderRadius: 2,
              }}
            />
          ) : null}
        </View>
      );
    case "text_lines": {
      const lines = Math.max(cell.lines ?? 2, 1);
      if (cell.text) {
        return (
          <View
            style={{ width: "100%", minHeight: h, justifyContent: "center" }}
          >
            <Text
              numberOfLines={lines}
              style={{
                color: "rgba(255,255,255,0.62)",
                fontSize: 5.5,
                lineHeight: 7,
              }}
            >
              {cell.text}
            </Text>
          </View>
        );
      }
      return (
        <View
          style={{
            width: "100%",
            minHeight: h,
            justifyContent: "center",
            gap: 2,
          }}
        >
          {Array.from({ length: lines }).map((_, i) => (
            <View
              key={i}
              style={{
                backgroundColor: bg,
                height: 2.5,
                width: i === lines - 1 ? "60%" : "100%",
                borderRadius: 2,
              }}
            />
          ))}
        </View>
      );
    }
    case "pill":
      return (
        <View
          style={{
            width: "100%",
            minHeight: h,
            backgroundColor: bg,
            borderRadius: 999,
            flexDirection: "row",
            alignItems: "center",
            justifyContent: "center",
            gap: 2,
            paddingHorizontal: 4,
          }}
        >
          {cell.text ? (
            <Text
              numberOfLines={1}
              style={{
                color: "rgba(255,255,255,0.95)",
                fontSize: 5.5,
                fontWeight: "600",
                flexShrink: 1,
              }}
            >
              {cell.text}
            </Text>
          ) : null}
          {cell.icon ? (
            <View
              style={{
                width: 4,
                height: 4,
                borderRadius: 2,
                backgroundColor: "rgba(255,255,255,0.85)",
              }}
            />
          ) : null}
        </View>
      );
    case "avatar": {
      const size = Math.max(h - 8, 14);
      return (
        <View
          style={{
            width: "100%",
            minHeight: h,
            flexDirection: "row",
            alignItems: "center",
            gap: 5,
          }}
        >
          {cell.img && !imgFailed ? (
            <View
              style={{
                width: size,
                height: size,
                borderRadius: size / 2,
                overflow: "hidden",
              }}
            >
              <Image
                source={{ uri: cell.img }}
                style={{ width: "100%", height: "100%" }}
                resizeMode="cover"
                onError={() => setImgFailed(true)}
                onLoad={() => setImgLoaded(true)}
              />
              {!imgLoaded ? <ShimmerOverlay radius={size / 2} /> : null}
            </View>
          ) : (
            <View
              style={{
                width: size,
                height: size,
                borderRadius: size / 2,
                backgroundColor: bg,
              }}
            />
          )}
          <View style={{ flex: 1, gap: 2 }}>
            {cell.text ? (
              <Text
                numberOfLines={1}
                style={{
                  color: "rgba(255,255,255,0.85)",
                  fontSize: 5.5,
                  fontWeight: "600",
                }}
              >
                {cell.text}
              </Text>
            ) : (
              <View
                style={{
                  backgroundColor: "rgba(255,255,255,0.55)",
                  height: 4,
                  width: "70%",
                  borderRadius: 2,
                }}
              />
            )}
            {cell.sub_text ? (
              <Text
                numberOfLines={1}
                style={{ color: "rgba(255,255,255,0.45)", fontSize: 5 }}
              >
                {cell.sub_text}
              </Text>
            ) : (
              <View
                style={{
                  backgroundColor: "rgba(255,255,255,0.30)",
                  height: 3,
                  width: "50%",
                  borderRadius: 2,
                }}
              />
            )}
          </View>
        </View>
      );
    }
    case "media": {
      // The builder emits a real placeholder image URL (`img`) plus a CSS
      // `linear-gradient(...)` background for image/video/audio/pdf cells
      // (matches web). Prefer the real image; fall back to the gradient
      // (via `expo-linear-gradient`) and finally a flat patch so the
      // mobile thumbnail mirrors the web preview.
      const playOverlay = cell.play ? (
        <View
          style={{
            position: "absolute",
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
            alignItems: "center",
            justifyContent: "center",
          }}
        >
          <Feather name="play" size={11} color="rgba(255,255,255,0.95)" />
        </View>
      ) : null;
      if (cell.img && !imgFailed) {
        return (
          <View
            style={{
              width: "100%",
              height: h,
              borderRadius: 3,
              overflow: "hidden",
            }}
          >
            <Image
              source={{ uri: cell.img }}
              style={{ width: "100%", height: "100%" }}
              resizeMode="cover"
              onError={() => setImgFailed(true)}
              onLoad={() => setImgLoaded(true)}
            />
            {!imgLoaded ? <ShimmerOverlay radius={3} /> : null}
            {playOverlay}
          </View>
        );
      }
      if (parsed.kind === "gradient") {
        const { start, end } = gradientPoints(parsed.angle);
        return (
          <LinearGradient
            colors={parsed.colors as [string, string, ...string[]]}
            start={start}
            end={end}
            style={{
              width: "100%",
              minHeight: h,
              height: h,
              borderRadius: 3,
            }}
          >
            {playOverlay}
          </LinearGradient>
        );
      }
      return (
        <View
          style={{
            width: "100%",
            minHeight: h,
            height: h,
            backgroundColor: bg,
            borderRadius: 3,
          }}
        >
          {playOverlay}
        </View>
      );
    }
    case "dot_row": {
      const dots = Math.max(cell.dots ?? 5, 1);
      return (
        <View
          style={{
            width: "100%",
            minHeight: h,
            flexDirection: "row",
            alignItems: "center",
            justifyContent: "center",
            gap: 3,
          }}
        >
          {Array.from({ length: dots }).map((_, i) => (
            <View
              key={i}
              style={{
                width: 4,
                height: 4,
                borderRadius: 2,
                backgroundColor: bg,
              }}
            />
          ))}
        </View>
      );
    }
    case "form": {
      const lines = Math.max(cell.lines ?? 1, 1);
      return (
        <View
          style={{
            width: "100%",
            minHeight: h,
            justifyContent: "center",
            gap: 3,
          }}
        >
          {Array.from({ length: lines }).map((_, i) => (
            <View
              key={i}
              style={{
                backgroundColor: bg,
                height: 4,
                width: "100%",
                borderRadius: 2,
              }}
            />
          ))}
          <View
            style={{
              alignSelf: "center",
              backgroundColor: cell.btn_bg ?? "rgba(139,92,246,0.85)",
              minHeight: 7,
              width: "70%",
              borderRadius: 999,
              alignItems: "center",
              justifyContent: "center",
              paddingHorizontal: 4,
              paddingVertical: 1,
            }}
          >
            {cell.text ? (
              <Text
                numberOfLines={1}
                style={{
                  color: "rgba(255,255,255,0.95)",
                  fontSize: 5,
                  fontWeight: "600",
                }}
              >
                {cell.text}
              </Text>
            ) : null}
          </View>
        </View>
      );
    }
    case "list_rows": {
      const lines = Math.max(cell.lines ?? 3, 1);
      const items =
        Array.isArray(cell.items) && cell.items.length
          ? cell.items.slice(0, lines)
          : Array.from({ length: lines }, () => null);
      return (
        <View
          style={{
            width: "100%",
            minHeight: h,
            justifyContent: "center",
            gap: 3,
          }}
        >
          {items.map((item, i) => (
            <View
              key={i}
              style={{
                flexDirection: "row",
                alignItems: "center",
                gap: 3,
              }}
            >
              <View
                style={{
                  width: 3,
                  height: 3,
                  borderRadius: 2,
                  backgroundColor: bg,
                }}
              />
              {item ? (
                <Text
                  numberOfLines={1}
                  style={{
                    flex: 1,
                    color: "rgba(255,255,255,0.62)",
                    fontSize: 5.5,
                  }}
                >
                  {item}
                </Text>
              ) : (
                <View
                  style={{
                    flex: 1,
                    backgroundColor: bg,
                    height: 2.5,
                    borderRadius: 2,
                  }}
                />
              )}
            </View>
          ))}
        </View>
      );
    }
    case "hairline":
      return (
        <View
          style={{
            width: "100%",
            backgroundColor: bg,
            height: h,
            borderRadius: 2,
          }}
        />
      );
    case "spacer":
      return <View style={{ width: "100%", minHeight: h }} />;
    case "badge":
      return (
        <View
          style={{
            alignSelf: "center",
            backgroundColor: bg,
            height: h,
            width: "50%",
            borderRadius: 999,
          }}
        />
      );
    case "tile":
    default:
      // The builder uses gradients for map/map_location cells too, so
      // honour them here as well for visual parity with web.
      if (parsed.kind === "gradient") {
        const { start, end } = gradientPoints(parsed.angle);
        return (
          <LinearGradient
            colors={parsed.colors as [string, string, ...string[]]}
            start={start}
            end={end}
            style={{ width: "100%", minHeight: h, borderRadius: 3 }}
          />
        );
      }
      return (
        <View
          style={{
            width: "100%",
            minHeight: h,
            backgroundColor: bg,
            borderRadius: 3,
          }}
        />
      );
  }
}
