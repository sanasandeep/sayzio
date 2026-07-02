import { apiFetch } from "@/lib/api";

/**
 * Register an Expo push token with the backend so the server can deliver
 * push notifications (e.g. API-usage warnings) to this device. Upserts on
 * the token, so calling it repeatedly with the same token is harmless.
 */
export async function registerPushToken(input: {
  token: string;
  platform?: string;
  device_name?: string;
}): Promise<void> {
  await apiFetch(`/me/push-tokens`, {
    method: "POST",
    body: JSON.stringify(input),
  });
}

/** Detach a token from the signed-in user (called on sign-out). */
export async function unregisterPushToken(token: string): Promise<void> {
  await apiFetch(`/me/push-tokens`, {
    method: "DELETE",
    body: JSON.stringify({ token }),
  });
}
