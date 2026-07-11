/**
 * ScrollReveal — shared scroll-triggered entrance-animation primitives.
 *
 * A section fades + slides in as it enters the viewport. Consumers wrap a
 * ScrollView in <ScrollRevealCtx.Provider> (via useScrollRevealRegistry) and
 * forward its onScroll offset to `notify`; each <ScrollReveal> child then
 * reveals itself on scroll. Respects the OS "Reduce Motion" setting — when on,
 * everything renders immediately with no animation.
 *
 * Used by AboutPage.tsx and InfoPage.tsx so every /info/* screen shares the
 * same motion.
 */

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "react";
import { View } from "react-native";
import Animated, {
  useAnimatedStyle,
  useSharedValue,
  withDelay,
  withSpring,
  withTiming,
} from "react-native-reanimated";
import { useWindowDimensions } from "react-native";

// ---------------------------------------------------------------------------
// Scroll-reveal context — shares a subscriber registry so child components
// can check their screen position without re-rendering the whole tree.
// ---------------------------------------------------------------------------

type ScrollListener = (scrollY: number) => void;
export type ScrollRevealRegistry = {
  subscribe: (l: ScrollListener) => () => void;
  getY: () => number;
};

export const ScrollRevealCtx = createContext<ScrollRevealRegistry | null>(null);

export function useScrollRevealRegistry(): [
  ScrollRevealRegistry,
  (y: number) => void,
] {
  const scrollY = useRef(0);
  const listeners = useRef<Set<ScrollListener>>(new Set());

  const registry = useMemo<ScrollRevealRegistry>(
    () => ({
      subscribe: (l) => {
        listeners.current.add(l);
        return () => {
          listeners.current.delete(l);
        };
      },
      getY: () => scrollY.current,
    }),
    [],
  );

  const notify = useCallback((y: number) => {
    scrollY.current = y;
    listeners.current.forEach((l) => l(y));
  }, []);

  return [registry, notify];
}

// ---------------------------------------------------------------------------
// ScrollReveal — render-prop component; reveals children when they enter view.
// children(revealed: boolean) so consumers can trigger their own animations.
// ---------------------------------------------------------------------------

export function ScrollReveal({
  children,
  delay = 0,
  direction = "up",
  reduceMotion,
}: {
  children: (revealed: boolean) => React.ReactNode;
  delay?: number;
  direction?: "up" | "left" | "right" | "none";
  reduceMotion: boolean;
}) {
  const ctx = useContext(ScrollRevealCtx);
  const { height: windowHeight } = useWindowDimensions();
  const triggered = useRef(false);
  const [revealed, setRevealed] = useState(reduceMotion);
  const ref = useRef<View>(null);

  const opacity = useSharedValue(reduceMotion ? 1 : 0);
  const translateY = useSharedValue(
    reduceMotion ? 0 : direction === "up" ? 28 : 0,
  );
  const translateX = useSharedValue(
    reduceMotion
      ? 0
      : direction === "left"
        ? -24
        : direction === "right"
          ? 24
          : 0,
  );

  // Unconditionally reveal the section — animating in when motion is allowed,
  // or snapping to visible otherwise. Idempotent via the `triggered` guard.
  const applyReveal = useCallback(() => {
    if (triggered.current) return;
    triggered.current = true;
    setRevealed(true);
    if (!reduceMotion) {
      opacity.value = withDelay(delay, withTiming(1, { duration: 480 }));
      translateY.value = withDelay(
        delay,
        withSpring(0, { damping: 22, stiffness: 130 }),
      );
      translateX.value = withDelay(
        delay,
        withSpring(0, { damping: 22, stiffness: 130 }),
      );
    }
  }, [delay, reduceMotion, opacity, translateY, translateX]);

  const reveal = useCallback(() => {
    if (triggered.current) return;
    const node = ref.current;
    // If the view can't be measured (null ref, or a platform where measure is
    // unavailable / silently drops its callback), reveal instead of risking a
    // section stuck permanently at opacity 0.
    if (!node || typeof node.measure !== "function") {
      applyReveal();
      return;
    }
    node.measure((_x, _y, _w, _h, _pageX, pageY) => {
      if (pageY < windowHeight * 1.08) applyReveal();
    });
  }, [windowHeight, applyReveal]);

  useEffect(() => {
    if (reduceMotion) {
      setRevealed(true);
      return;
    }
    const timer = setTimeout(reveal, 80);
    // Failsafe: guarantee the section becomes visible even if the scroll/measure
    // trigger never fires (measurement timing, web vs native quirks, off-screen
    // content that never scrolls into view). Without this a section could stay
    // stuck at opacity 0 — a silent content-loss bug.
    const failsafe = setTimeout(applyReveal, 2200);
    const unsub = ctx?.subscribe(reveal);
    return () => {
      clearTimeout(timer);
      clearTimeout(failsafe);
      unsub?.();
    };
  }, [reveal, applyReveal, ctx, reduceMotion]);

  const animStyle = useAnimatedStyle(() => ({
    opacity: opacity.value,
    transform: [
      { translateY: translateY.value },
      { translateX: translateX.value },
    ],
  }));

  return (
    <Animated.View ref={ref} style={animStyle}>
      {children(revealed)}
    </Animated.View>
  );
}
