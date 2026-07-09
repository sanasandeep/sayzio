import { Alert, Platform, type AlertButton } from "react-native";

/**
 * Web-safe drop-in replacement for `Alert.alert`.
 *
 * react-native-web's `Alert.alert` is a NO-OP, so on the Expo web build any
 * confirmation or action sheet built with the native Alert silently swallows
 * the tap — destructive confirms never fire and informational alerts never
 * show. This shim keeps the exact `Alert.alert(title, message?, buttons?)`
 * signature and delegates to the native Alert on iOS/Android, but on web maps
 * the button set onto `window.alert` / `window.confirm` (same pattern as
 * lib/upgradePrompt.ts and the DrawerSidebar sign-out confirm):
 *
 * - 0 buttons, or only a single OK/cancel-style button:
 *   `window.alert`, then invoke that button's onPress (informational).
 * - Exactly one actionable (non-cancel) button (the classic confirm):
 *   `window.confirm` — OK runs the actionable button, Cancel/dismiss runs the
 *   cancel button's onPress (if any). Dismissing NEVER runs the action.
 * - Two or more actionable buttons (an action-sheet-style picker):
 *   offer each choice in order via `window.confirm("… <label>?")`; the first
 *   accepted choice runs and stops the chain. Declining all of them runs the
 *   cancel button's onPress (if any).
 *
 * SSR / non-browser safety: if `window.confirm` is unavailable we fail CLOSED
 * for actions (nothing runs) rather than silently invoking a destructive
 * onPress.
 */
export function showAlert(
  title: string,
  message?: string,
  buttons?: AlertButton[],
): void {
  if (Platform.OS !== "web") {
    Alert.alert(title, message, buttons);
    return;
  }

  const text = message ? `${title}\n\n${message}` : title;
  const hasWindow = typeof window !== "undefined";
  const canAlert = hasWindow && typeof window.alert === "function";
  const canConfirm = hasWindow && typeof window.confirm === "function";

  const list = buttons ?? [];
  const cancel = list.find((b) => b.style === "cancel");
  const actionable = list.filter((b) => b.style !== "cancel");

  // Informational: no actionable choice beyond acknowledging.
  if (
    list.length === 0 ||
    (list.length === 1 && !hasMeaningfulAction(list[0]))
  ) {
    if (canAlert) window.alert(text);
    list[0]?.onPress?.();
    return;
  }

  // Single button that DOES something: still just an acknowledge box, but the
  // user must be able to decline running it — use confirm.
  if (actionable.length === 1 && !cancel && list.length === 1) {
    if (canConfirm && window.confirm(text)) actionable[0].onPress?.();
    return;
  }

  if (!canConfirm) return; // fail closed — never auto-run an action

  // Classic confirm: one actionable button (+ optional cancel).
  if (actionable.length === 1) {
    if (window.confirm(text)) actionable[0].onPress?.();
    else cancel?.onPress?.();
    return;
  }

  // Action-sheet-style picker: offer each choice in turn.
  for (const b of actionable) {
    const label = b.text ?? "OK";
    if (window.confirm(`${text}\n\n${label}?`)) {
      b.onPress?.();
      return;
    }
  }
  cancel?.onPress?.();
}

function hasMeaningfulAction(b: AlertButton): boolean {
  return typeof b.onPress === "function" && b.style !== "cancel";
}
