import { Image } from "expo-image";
import { LinearGradient } from "expo-linear-gradient";
import React, { useCallback, useEffect, useRef, useState } from "react";
import {
  AccessibilityInfo,
  Dimensions,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  View,
} from "react-native";
import Animated, {
  Easing,
  FadeOut,
  SharedValue,
  useAnimatedStyle,
  useSharedValue,
  withRepeat,
  withTiming,
} from "react-native-reanimated";
import Svg, { Circle } from "react-native-svg";

// ─── Ring definitions ──────────────────────────────────────────────────────
// Each ring has a radius, rotation duration (ms per full revolution), and
// direction (+1 = clockwise, -1 = counter-clockwise).
const RINGS = [
  { radius: 74,  duration: 22000, dir:  1 },
  { radius: 128, duration: 34000, dir: -1 },
  { radius: 180, duration: 46000, dir:  1 },
] as const;

// ─── Node definitions ──────────────────────────────────────────────────────
type NodeDef = {
  angle: number;
  ring: 0 | 1 | 2;
  img: number;
  accent: string;
};

const c1 = "#7d9bff";
const c2 = "#6e61ff";
const c3 = "#f59e0b";
const c4 = "#d76dff";
const c5 = "#22c55e";

const NODES: NodeDef[] = [
  // Ring 0 – Zio's AI core
  { angle: 0,   ring: 0, img: require("@/assets/images/zio-nodes/ai.png"),        accent: c2 },
  { angle: 90,  ring: 0, img: require("@/assets/images/zio-nodes/growth.png"),    accent: c1 },
  { angle: 180, ring: 0, img: require("@/assets/images/zio-nodes/calls.png"),     accent: c4 },
  { angle: 270, ring: 0, img: require("@/assets/images/zio-nodes/analytics.png"), accent: c2 },

  // Ring 1 – everyday tools
  { angle: 30,  ring: 1, img: require("@/assets/images/zio-nodes/link.png"),      accent: c2 },
  { angle: 90,  ring: 1, img: require("@/assets/images/zio-nodes/qr.png"),        accent: c3 },
  { angle: 150, ring: 1, img: require("@/assets/images/zio-nodes/store.png"),     accent: c5 },
  { angle: 210, ring: 1, img: require("@/assets/images/zio-nodes/forms.png"),     accent: c2 },
  { angle: 270, ring: 1, img: require("@/assets/images/zio-nodes/audience.png"),  accent: c1 },
  { angle: 330, ring: 1, img: require("@/assets/images/zio-nodes/social.png"),    accent: c3 },

  // Ring 2 – wider universe
  { angle: 0,   ring: 2, img: require("@/assets/images/zio-nodes/code.png"),      accent: c2 },
  { angle: 51,  ring: 2, img: require("@/assets/images/zio-nodes/reviews.png"),   accent: c3 },
  { angle: 103, ring: 2, img: require("@/assets/images/zio-nodes/menu.png"),      accent: c5 },
  { angle: 154, ring: 2, img: require("@/assets/images/zio-nodes/resume.png"),    accent: c2 },
  { angle: 206, ring: 2, img: require("@/assets/images/zio-nodes/calendar.png"),  accent: c1 },
  { angle: 257, ring: 2, img: require("@/assets/images/zio-nodes/vcard.png"),     accent: c2 },
  { angle: 309, ring: 2, img: require("@/assets/images/zio-nodes/domain.png"),    accent: c4 },
];

// ─── Tile sizes by ring ─────────────────────────────────────────────────────
const TILE_SIZES = [38, 34, 30] as const;
const ICON_SIZES = [22, 20, 18] as const;

// Mascot asset: animated WebP on Android (expo-image renders it looping),
// still PNG on iOS (VP9-alpha WebM ignores alpha on iOS; animated WebP
// support in older iOS WKWebView can be inconsistent).
const MASCOT_ANIMATED = require("@/assets/images/sayzio-mascot-animated.webp");
const MASCOT_STILL    = require("@/assets/images/sayzio-mascot-still.png");
const mascotSource    = Platform.OS === "android" ? MASCOT_ANIMATED : MASCOT_STILL;

// ─── Single orbiting node tile ─────────────────────────────────────────────
function NodeTile({
  node,
  ringIndex,
  rotorDeg,
  reduced,
}: {
  node: NodeDef;
  ringIndex: number;
  rotorDeg: SharedValue<number>;
  reduced: boolean;
}) {
  const tile = TILE_SIZES[ringIndex];
  const icon = ICON_SIZES[ringIndex];
  const radius = RINGS[ringIndex].radius;
  const rad = (node.angle * Math.PI) / 180;
  const tx = radius * Math.cos(rad);
  const ty = radius * Math.sin(rad);

  const counterStyle = useAnimatedStyle(() => ({
    transform: [{ rotate: `${-rotorDeg.value}deg` }],
  }));

  return (
    <View
      style={[
        styles.nodeAnchor,
        {
          transform: [{ translateX: tx }, { translateY: ty }],
          width: tile,
          height: tile,
          marginLeft: -tile / 2,
          marginTop: -tile / 2,
        },
      ]}
    >
      <Animated.View style={reduced ? undefined : counterStyle}>
        <View
          style={[
            styles.nodeTile,
            {
              width: tile,
              height: tile,
              borderRadius: tile * 0.28,
              borderColor: node.accent + "55",
            },
          ]}
        >
          <Image
            source={node.img}
            style={{ width: icon, height: icon }}
            contentFit="contain"
          />
        </View>
      </Animated.View>
    </View>
  );
}

// ─── Single ring rotor ──────────────────────────────────────────────────────
function RingRotor({
  ringIndex,
  reduced,
}: {
  ringIndex: number;
  reduced: boolean;
}) {
  const { duration, dir, radius } = RINGS[ringIndex];
  const deg = useSharedValue(0);

  useEffect(() => {
    if (reduced) return;
    deg.value = withRepeat(
      withTiming(360 * dir, { duration, easing: Easing.linear }),
      -1,
      false,
    );
  }, [reduced, deg, duration, dir]);

  const rotorStyle = useAnimatedStyle(() => ({
    transform: [{ rotate: `${deg.value}deg` }],
  }));

  const ringNodes = NODES.filter((n) => n.ring === ringIndex);

  return (
    <>
      {/* Dashed SVG ring */}
      <Svg
        width={radius * 2 + 2}
        height={radius * 2 + 2}
        style={[styles.svgRing, { marginLeft: -(radius + 1), marginTop: -(radius + 1) }]}
      >
        <Circle
          cx={radius + 1}
          cy={radius + 1}
          r={radius}
          stroke="rgba(255,255,255,0.10)"
          strokeWidth={1}
          strokeDasharray={["4 8", "5 9", "6 10"][ringIndex]}
          fill="none"
        />
      </Svg>

      {/* Rotor holding the tiles */}
      <Animated.View style={[styles.rotor, reduced ? undefined : rotorStyle]}>
        {ringNodes.map((node, i) => (
          <NodeTile
            key={i}
            node={node}
            ringIndex={ringIndex}
            rotorDeg={deg}
            reduced={reduced}
          />
        ))}
      </Animated.View>
    </>
  );
}

// ─── Pulsing halo behind the mascot ────────────────────────────────────────
function MascotHalo({ reduced }: { reduced: boolean }) {
  const scale = useSharedValue(1);

  useEffect(() => {
    if (reduced) return;
    scale.value = withRepeat(
      withTiming(1.18, { duration: 2200, easing: Easing.inOut(Easing.ease) }),
      -1,
      true,
    );
  }, [reduced, scale]);

  const haloStyle = useAnimatedStyle(() => ({
    transform: [{ scale: scale.value }],
    opacity: 1.5 - scale.value,
  }));

  return <Animated.View style={[styles.halo, haloStyle]} />;
}

// ─── Session flag ──────────────────────────────────────────────────────────
// Module-level so it survives GateScreen remounts (e.g. navigating back to
// the gate) but resets on a fresh JS session (cold launch). Ensures the
// branded splash shows only on the first launch of each session.
let splashShownThisSession = false;

/** True once the splash has been shown during the current app session. */
export function hasSplashShownThisSession(): boolean {
  return splashShownThisSession;
}

/** Mark the splash as shown for the current app session. */
export function markSplashShownThisSession(): void {
  splashShownThisSession = true;
}

// ─── Main ZioSplash component ──────────────────────────────────────────────
export interface ZioSplashProps {
  /**
   * Called once when the splash should exit: whichever comes last of
   * `minDuration` elapsed and `appReady` becoming true — capped by
   * `maxDuration` so a slow server can never trap the user.
   */
  onDone: () => void;
  /**
   * Set to true when the app has resolved where the user is going.
   * The splash will not dismiss before this is true (within maxDuration).
   */
  appReady?: boolean;
  /** Minimum display time in ms. Default 2400. */
  minDuration?: number;
  /** Hard cap in ms — dismisses unconditionally. Default 3200. */
  maxDuration?: number;
}

export function ZioSplash({
  onDone,
  appReady = false,
  minDuration = 2400,
  maxDuration = 3200,
}: ZioSplashProps) {
  const [reduced, setReduced] = useState(false);

  // Idempotent "call onDone once" with shared state across two timers.
  const done = useRef(false);
  const minFired = useRef(false);
  const readyRef = useRef(appReady);
  readyRef.current = appReady;

  const callOnce = useCallback(() => {
    if (done.current) return;
    done.current = true;
    onDone();
  }, [onDone]);

  const tryDone = useCallback(() => {
    if (minFired.current && readyRef.current) callOnce();
  }, [callOnce]);

  useEffect(() => {
    AccessibilityInfo.isReduceMotionEnabled()
      .then(setReduced)
      .catch(() => {});

    const minTimer = setTimeout(() => {
      minFired.current = true;
      tryDone();
    }, minDuration);

    // Hard cap: always exit by maxDuration.
    const maxTimer = setTimeout(callOnce, maxDuration);

    return () => {
      clearTimeout(minTimer);
      clearTimeout(maxTimer);
    };
  }, [minDuration, maxDuration, tryDone, callOnce]);

  // Whenever appReady flips to true, try to dismiss.
  useEffect(() => {
    if (appReady) tryDone();
  }, [appReady, tryDone]);

  return (
    <Animated.View style={styles.root} exiting={FadeOut.duration(380)}>
      {/* Deep dark background */}
      <View style={StyleSheet.absoluteFill}>
        <LinearGradient
          colors={["#0f0a1e", "#0a0a0f", "#0a0a0f"]}
          start={{ x: 0.3, y: 0 }}
          end={{ x: 0.7, y: 1 }}
          style={StyleSheet.absoluteFill}
        />
        <View style={[styles.blob, styles.blobA]} />
        <View style={[styles.blob, styles.blobB]} />
      </View>

      {/* Orbit stage */}
      <View style={styles.stage}>
        <RingRotor ringIndex={0} reduced={reduced} />
        <RingRotor ringIndex={1} reduced={reduced} />
        <RingRotor ringIndex={2} reduced={reduced} />

        <MascotHalo reduced={reduced} />
        <View style={styles.mascotWrap}>
          <Image
            source={mascotSource}
            style={styles.mascot}
            contentFit="contain"
            // Only autoplay on Android (animated WebP); iOS uses still PNG.
            // In reduced-motion mode the mascot is static regardless of platform.
            autoplay={!reduced}
          />
        </View>
      </View>

      {/* "Zio runs it all" pill */}
      <View style={styles.pillWrap}>
        <View style={styles.pill}>
          <Text style={styles.pillDot}>✦</Text>
          <Text style={styles.pillText}>Zio runs it all</Text>
        </View>
      </View>

      {/* Topmost layer: tap anywhere to skip the splash immediately. */}
      <Pressable
        accessibilityRole="button"
        accessibilityLabel="Skip splash screen"
        onPress={callOnce}
        style={StyleSheet.absoluteFill}
      />
    </Animated.View>
  );
}

// ─── Styles ────────────────────────────────────────────────────────────────
const { width: SW } = Dimensions.get("window");
const STAGE_SIZE = Math.min(SW, 420);

const styles = StyleSheet.create({
  root: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: "#0a0a0f",
    alignItems: "center",
    justifyContent: "center",
    zIndex: 9999,
  },

  blob: { position: "absolute", borderRadius: 9999 },
  blobA: { width: 260, height: 260, top: -60,  left: -60, backgroundColor: "#3d6bff", opacity: 0.12 },
  blobB: { width: 220, height: 220, bottom: -50, right: -40, backgroundColor: "#6e61ff", opacity: 0.10 },

  stage: {
    width: STAGE_SIZE,
    height: STAGE_SIZE,
    alignItems: "center",
    justifyContent: "center",
  },

  svgRing: { position: "absolute" },

  rotor: {
    position: "absolute",
    width: 0,
    height: 0,
    alignItems: "center",
    justifyContent: "center",
  },

  nodeAnchor: { position: "absolute" },

  nodeTile: {
    backgroundColor: "rgba(255,255,255,0.07)",
    borderWidth: 1,
    alignItems: "center",
    justifyContent: "center",
    shadowColor: "#7d9bff",
    shadowOffset: { width: 0, height: 0 },
    shadowOpacity: 0.25,
    shadowRadius: 6,
    elevation: 4,
  },

  halo: {
    position: "absolute",
    width: 110,
    height: 110,
    borderRadius: 55,
    backgroundColor: "#3d6bff",
    opacity: 0.18,
  },

  mascotWrap: {
    position: "absolute",
    width: 100,
    height: 100,
    alignItems: "center",
    justifyContent: "center",
  },
  mascot: { width: 96, height: 96 },

  pillWrap: {
    position: "absolute",
    bottom: "14%",
    alignItems: "center",
  },
  pill: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 9999,
    backgroundColor: "rgba(61,107,255,0.18)",
    borderWidth: 1,
    borderColor: "rgba(61,107,255,0.40)",
  },
  pillDot: { fontSize: 11, color: "#7d9bff" },
  pillText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 13,
    color: "#c8d8ff",
    letterSpacing: 0.5,
  },
});
