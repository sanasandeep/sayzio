import { Alert, Linking, Platform } from "react-native";

import type { ApiError } from "@/lib/api";
import { getBaseUrl } from "@/lib/api";

/**
 * Error `code`s the REST API stamps on plan-gated rejections (the
 * `{error: {message, code}}` envelope). When any of these — or an HTTP 402
 * (Payment Required) — comes back from a mutation, the action was blocked by
 * the user's plan rather than by bad input, so we surface a friendly
 * "Upgrade your plan" prompt instead of a raw error toast.
 *
 * Covers QR Studio caps, Creator Payouts, and page-type quotas (restaurant
 * menu / reviews / resume / paid page) enforced server-side in Task #1779,
 * plus the resume/card/page template `plan_required` gates and workspace
 * `seat_limit` gate that now carry a recommended-plan hint.
 */
const PLAN_LOCK_CODES = new Set<string>([
  "plan_upgrade_required",
  "plan_limit_reached",
  "plan_limit",
  "plan_required",
  "upgrade_required",
  "feature_locked",
  "rule_limit_exceeded",
  "seat_limit",
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
 * A pre-fill hint for the upgrade prompt: the plan that unlocks the feature
 * the user just hit. The Laravel side computes the cheapest qualifying plan
 * (`User::planThatUnlocks`) and stamps it into the plan-gated error envelope's
 * `details` (`recommended_plan` slug + `recommended_plan_name` + `feature`).
 */
export type UpgradeHint = {
  /** Recommended plan slug. */
  planSlug?: string;
  /** Human-readable recommended plan name, for the prompt copy. */
  planName?: string;
  /** Raw feature key the gate blocked on. */
  feature?: string;
};

function str(v: unknown): string | undefined {
  return typeof v === "string" && v.trim() ? v : undefined;
}

/**
 * Pull the recommended-plan hint out of a plan-gated API error's `details`.
 * Returns `undefined` when the backend didn't supply one.
 */
export function upgradeHintFromError(error: unknown): UpgradeHint | undefined {
  const err = asApiError(error);
  const details = err?.details;
  if (!details || typeof details !== "object") return undefined;
  const planSlug = str((details as Record<string, unknown>).recommended_plan);
  const planName = str(
    (details as Record<string, unknown>).recommended_plan_name,
  );
  const feature = str((details as Record<string, unknown>).feature);
  if (!planSlug && !feature) return undefined;
  return { planSlug, planName, feature };
}

/**
 * Open the website's pricing page in the OS external browser (Safari / Chrome).
 * The external browser does not carry the app's session, so we always land on
 * the public /pricing page rather than the authenticated /user/upgrade page.
 */
export function openUpgradeInBrowser(): void {
  const url = `${getBaseUrl()}/pricing`;
  if (Platform.OS === "web") {
    if (typeof window !== "undefined") {
      window.open(url, "_blank");
    }
    return;
  }
  Linking.openURL(url).catch(() => {});
}

/**
 * Show the "Upgrade your plan" prompt with a CTA that opens the website's
 * pricing page in the phone's external browser. Safe to call from anywhere.
 */
export function showUpgradePrompt(opts?: {
  title?: string;
  message?: string;
  hint?: UpgradeHint;
}): void {
  const title = opts?.title ?? "Upgrade your plan";
  const message =
    opts?.message ?? "This feature isn't available on your current plan.";

  if (Platform.OS === "web") {
    const proceed =
      typeof window !== "undefined" && typeof window.confirm === "function"
        ? window.confirm(`${title}\n\n${message}\n\nOpen pricing page?`)
        : false;
    if (proceed) openUpgradeInBrowser();
    return;
  }

  Alert.alert(title, message, [
    { text: "Not now", style: "cancel" },
    { text: "See plans", style: "default", onPress: openUpgradeInBrowser },
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
  const hint = upgradeHintFromError(error);
  let message = messageOf(error, fallbackMessage);
  if (hint?.planName && !message.includes(hint.planName)) {
    const sep = /[.!?]\s*$/.test(message) ? "" : ".";
    message = `${message.trimEnd()}${sep} Upgrade to ${hint.planName} to unlock it.`;
  }
  showUpgradePrompt({ message, hint });
  return true;
}
