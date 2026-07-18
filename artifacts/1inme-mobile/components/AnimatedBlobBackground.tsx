import { useEffect } from "react";
import Animated, {
  Easing,
  useAnimatedStyle,
  useSharedValue,
  withDelay,
  withRepeat,
  withSequence,
  withTiming,
} from "react-native-reanimated";

/**
 * A single soft, circular "blob" that gently drifts around its initial
 * position to create an ambient depth effect. Set `reduced={true}` to
 * render it static (no animation) for users who prefer reduced motion.
 *
 * Always rendered with `pointerEvents="none"` so it never intercepts taps.
 */
export function AnimatedBlob({
  color,
  size,
  initialX,
  initialY,
  driftX,
  driftY,
  duration,
  opacity,
  delayMs,
  reduced,
}: {
  color: string;
  size: number;
  initialX: number;
  initialY: number;
  driftX: number;
  driftY: number;
  duration: number;
  opacity: number;
  delayMs: number;
  reduced: boolean;
}) {
  const tx = useSharedValue(0);
  const ty = useSharedValue(0);

  useEffect(() => {
    if (reduced) return;
    tx.value = withDelay(
      delayMs,
      withRepeat(
        withSequence(
          withTiming(driftX, { duration, easing: Easing.inOut(Easing.quad) }),
          withTiming(-driftX * 0.6, {
            duration: duration * 0.9,
            easing: Easing.inOut(Easing.quad),
          }),
        ),
        -1,
        true,
      ),
    );
    ty.value = withDelay(
      delayMs,
      withRepeat(
        withSequence(
          withTiming(driftY, {
            duration: duration * 1.1,
            easing: Easing.inOut(Easing.quad),
          }),
          withTiming(-driftY * 0.7, {
            duration: duration * 0.8,
            easing: Easing.inOut(Easing.quad),
          }),
        ),
        -1,
        true,
      ),
    );
  }, [reduced]);

  const blobStyle = useAnimatedStyle(() => ({
    transform: [{ translateX: tx.value }, { translateY: ty.value }],
  }));

  return (
    <Animated.View
      pointerEvents="none"
      style={[
        {
          position: "absolute",
          left: initialX - size / 2,
          top: initialY - size / 2,
          width: size,
          height: size,
          borderRadius: size / 2,
          backgroundColor: color,
          opacity,
        },
        blobStyle,
      ]}
    />
  );
}
