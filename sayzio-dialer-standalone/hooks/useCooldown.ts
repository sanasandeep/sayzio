import { useCallback, useEffect, useRef, useState } from "react";

/**
 * Drives a short countdown for actions that should be throttled client-side
 * (e.g. "Resend code" buttons). Call `start()` after a successful send and the
 * hook counts `remaining` down to 0. It polls a few times a second (rather than
 * once per second) and derives the remaining seconds from a wall-clock end time,
 * so the displayed count stays accurate even if a tick is delayed or the screen
 * re-renders. While `remaining > 0` the action should be disabled.
 */
export function useCooldown(seconds = 30) {
  const [remaining, setRemaining] = useState(0);
  const endsAtRef = useRef<number | null>(null);

  useEffect(() => {
    if (remaining <= 0) return;
    const id = setInterval(() => {
      const endsAt = endsAtRef.current;
      if (endsAt == null) {
        setRemaining(0);
        return;
      }
      const left = Math.max(0, Math.ceil((endsAt - Date.now()) / 1000));
      setRemaining(left);
      if (left <= 0) endsAtRef.current = null;
    }, 250);
    return () => clearInterval(id);
  }, [remaining]);

  const start = useCallback(
    (durationSeconds = seconds) => {
      endsAtRef.current = Date.now() + durationSeconds * 1000;
      setRemaining(durationSeconds);
    },
    [seconds],
  );

  const clear = useCallback(() => {
    endsAtRef.current = null;
    setRemaining(0);
  }, []);

  return { remaining, active: remaining > 0, start, clear };
}
