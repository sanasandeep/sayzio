import { useEffect, useRef } from "react";
import { AppState } from "react-native";

/**
 * Runs `onForeground` whenever the app returns to the foreground
 * (AppState becomes "active"). JS timers pause while the app is
 * backgrounded, so polling loops and relative-time labels go stale;
 * screens showing live or time-relative data should use this to
 * re-fetch and recompute immediately on resume.
 *
 * The latest callback is always used (no need to memoize it), and
 * nothing runs while the screen is actively in use.
 */
export function useForegroundRefresh(onForeground: () => void) {
  const cbRef = useRef(onForeground);
  cbRef.current = onForeground;

  useEffect(() => {
    const sub = AppState.addEventListener("change", (state) => {
      if (state === "active") cbRef.current();
    });
    return () => sub.remove();
  }, []);
}
