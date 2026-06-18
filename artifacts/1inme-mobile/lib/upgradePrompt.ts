import { Alert, Platform } from "react-native";
import { router } from "expo-router";

import type { ApiError } from "@/lib/api";

/**
 * Error `code`s the REST API stamps on plan-gated rejections (the
 * `{error: {message, code}}` envelope). When any of these — or an HTTP 402
 * (Payment Required) — comes back from a mutation, the action was blocked by
 * the user's plan rather than by bad input, so we surface a friendly
 * "Upgrade your plan" prompt instead of a raw error toast.
 *
 * Covers QR Studio caps, Creator Payouts, and page-type quotas (restaurant
 * menu / reviews / resume / paid page) enforced server-side in Task #1779.
 */
const PLAN_LOCK_CODES = new Set<string>([
  "plan_upgrade_required",
  "plan_limit_reached",
  "plan_limit",
  "upgrade_required",
  "feature_locked",
  "rule_limit_exceeded",
  "api_quota_exceeded",
  "api_access_disabled",
  "insufficient_credits",
]);

function asApiError(error: unknown): Partial<ApiError> | null {
  if (!error || typeof error !== "object") return null;
  return error as Partial<ApiError>;
}

/**
 * True when an error represents a plan-gating rejection (the action would
 * succeed on a higher plan), as opposed to a validation error, a network
 * failure, or a not-found.
 */
export function isPlanLockedError(error: unknown): error is ApiError {
  const err = asApiError(error);
  if (!err) return false;
  if (err.status === 402) return true;
  if (typeof err.code === "string" && PLAN_LOCK_CODES.has(err.code)) return true;
  return false;
}

function messageOf(error: unknown, fallback: string): string {
  const err = asApiError(error);
  if (err && typeof err.message === "string" && err.message.trim()) {
    return err.message;
  }
  return fallback;
}

/**
 * Show the "Upgrade your plan" prompt with a CTA that opens the in-app
 * upgrade screen. Safe to call from anywhere (uses the imperative
 * `expo-router` `router`); on web falls back to `window.confirm`.
 */
export function showUpgradePrompt(opts?: {
  title?: string;
  message?: string;
}): void {
  const title = opts?.title ?? "Upgrade your plan";
  const message =
    opts?.message ?? "This feature isn't available on your current plan.";

  const goToUpgrade = () => router.push("/upgrade" as never);

  if (Platform.OS === "web") {
    const proceed =
      typeof window !== "undefined" && typeof window.confirm === "function"
        ? window.confirm(`${title}\n\n${message}\n\nSee upgrade options?`)
        : false;
    if (proceed) goToUpgrade();
    return;
  }

  Alert.alert(title, message, [
    { text: "Not now", style: "cancel" },
    { text: "See plans", style: "default", onPress: goToUpgrade },
  ]);
}

/**
 * If `error` is a plan-gating rejection, show the upgrade prompt and return
 * `true` (the caller should NOT also show its own error). Otherwise return
 * `false` so the caller can fall back to its normal error handling.
 *
 * Usage in a mutation `onError`:
 *
 *   onError: (e) => {
 *     if (handlePlanLockedError(e)) return;
 *     Alert.alert("Couldn't save", (e as any)?.message ?? "Try again.");
 *   }
 */
export function handlePlanLockedError(
  error: unknown,
  fallbackMessage = "This feature requires a plan upgrade.",
): boolean {
  if (!isPlanLockedError(error)) return false;
  showUpgradePrompt({ message: messageOf(error, fallbackMessage) });
  return true;
}
