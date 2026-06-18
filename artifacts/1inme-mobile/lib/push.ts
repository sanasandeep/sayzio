import Constants from "expo-constants";
import * as Device from "expo-device";
import * as Notifications from "expo-notifications";
import { router } from "expo-router";
import * as WebBrowser from "expo-web-browser";
import { Linking, Platform } from "react-native";

import { getBaseUrl } from "@/lib/api";
import { markRead } from "@/lib/api/notifications";
import { registerPushToken, unregisterPushToken } from "@/lib/api/push";

let handlerConfigured = false;
let lastRegisteredToken: string | null = null;
let registering = false;

/**
 * Foreground presentation rules. By default Expo suppresses notifications
 * while the app is open; API-usage warnings are worth surfacing as a
 * banner + sound even then.
 */
export function configurePushHandler(): void {
  if (handlerConfigured) return;
  handlerConfigured = true;
  Notifications.setNotificationHandler({
    handleNotification: async () => ({
      shouldShowBanner: true,
      shouldShowList: true,
      shouldPlaySound: true,
      shouldSetBadge: false,
    }),
  });
}

/**
 * Resolve this device's Expo push token, requesting permission if needed.
 * Returns null when running on a simulator/web, when permission is denied,
 * or when no EAS projectId is configured (so the call can't succeed) —
 * push is a progressive enhancement, never a hard requirement.
 */
async function resolveExpoPushToken(): Promise<string | null> {
  if (Platform.OS === "web" || !Device.isDevice) return null;

  const current = await Notifications.getPermissionsAsync();
  let status = current.status;
  if (status !== "granted") {
    const requested = await Notifications.requestPermissionsAsync();
    status = requested.status;
  }
  if (status !== "granted") return null;

  if (Platform.OS === "android") {
    await Notifications.setNotificationChannelAsync("default", {
      name: "Default",
      importance: Notifications.AndroidImportance.DEFAULT,
    });
  }

  const projectId =
    (Constants?.expoConfig?.extra as { eas?: { projectId?: string } } | undefined)
      ?.eas?.projectId ?? (Constants as any)?.easConfig?.projectId;

  try {
    const res = await Notifications.getExpoPushTokenAsync(
      projectId ? { projectId } : undefined,
    );
    return res.data ?? null;
  } catch (e) {
    if (__DEV__) {
      console.warn(
        "[1inme] Could not get an Expo push token (no EAS project / dev build?):",
        e,
      );
    }
    return null;
  }
}

/**
 * Ensure this device's push token is registered with the backend. Safe to
 * call on every sign-in / unlock — it de-dupes against the last token it
 * successfully registered this session.
 */
export async function syncPushRegistration(): Promise<void> {
  if (registering) return;
  registering = true;
  try {
    const token = await resolveExpoPushToken();
    if (!token || token === lastRegisteredToken) return;
    await registerPushToken({
      token,
      platform: Platform.OS,
      device_name: Device.deviceName ?? undefined,
    });
    lastRegisteredToken = token;
  } catch (e) {
    if (__DEV__) console.warn("[1inme] push registration failed:", e);
  } finally {
    registering = false;
  }
}

/** Detach the registered token from the user (best-effort, on sign-out). */
export async function clearPushRegistration(): Promise<void> {
  const token = lastRegisteredToken;
  lastRegisteredToken = null;
  if (!token) return;
  try {
    await unregisterPushToken(token);
  } catch {
    /* best-effort — sign-out should never block on this */
  }
}

/**
 * Open the target a tapped push points at, mirroring the in-app row
 * gesture. Targets are app web paths/URLs, so relative paths resolve
 * against the API host and hand off to the in-app browser (falling back
 * to the OS handler).
 */
function openPushTarget(target: string): void {
  const absolute = /^https?:\/\//i.test(target)
    ? target
    : `${getBaseUrl()}${target.startsWith("/") ? "" : "/"}${target}`;
  WebBrowser.openBrowserAsync(absolute).catch(() => {
    Linking.openURL(absolute).catch(() => {});
  });
}

/**
 * Route to the right destination when the user taps a push notification.
 * We deep-link to the exact same target the in-app row uses (carried as
 * `url` in the push data, resolved server-side by the single source of
 * truth) and mark the originating row read in the same gesture. When no
 * target is present we fall back gracefully: API-usage warnings to the
 * usage screen, everything else to the notifications list.
 */
export function addPushResponseListener(): Notifications.EventSubscription {
  return Notifications.addNotificationResponseReceivedListener((response) => {
    const data = response.notification.request.content.data as
      | Record<string, unknown>
      | undefined;

    // Mark just the originating row read (best-effort), so the badge/list
    // stay in sync with the tap.
    const rawId = data?.notification_id;
    const id =
      typeof rawId === "number"
        ? rawId
        : typeof rawId === "string" && rawId.trim() !== ""
          ? Number(rawId)
          : NaN;
    if (Number.isFinite(id)) {
      markRead(id).catch(() => {});
    }

    // Deep-link to the same place the in-app row opens, when present.
    const target = typeof data?.url === "string" ? data.url : null;
    if (target) {
      openPushTarget(target);
      return;
    }

    // Graceful fallbacks when there's no target URL.
    const type = typeof data?.type === "string" ? data.type : null;
    if (type === "api.usage_warning") {
      router.push("/api-usage");
    } else {
      router.push("/notifications");
    }
  });
}
