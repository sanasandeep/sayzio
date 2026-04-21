import { Platform } from "react-native";

import { getToken } from "@/lib/secure";

const FALLBACK_HOST = "1inme.com";

export function getBaseUrl(): string {
  const fromEnv = process.env.EXPO_PUBLIC_API_BASE_URL;
  if (fromEnv) return fromEnv.replace(/\/$/, "");
  const domain = process.env.EXPO_PUBLIC_DOMAIN;
  if (domain) return `https://${domain}`;
  if (Platform.OS === "web" && typeof window !== "undefined") {
    return window.location.origin;
  }
  return `https://${FALLBACK_HOST}`;
}

export type ApiError = {
  status: number;
  message: string;
  errors?: Record<string, string[]>;
};

export async function apiFetch<T = unknown>(
  path: string,
  init: RequestInit = {},
): Promise<T> {
  const url = `${getBaseUrl()}/api/v1${path.startsWith("/") ? path : `/${path}`}`;
  const token = await getToken();
  const headers: Record<string, string> = {
    Accept: "application/json",
    "Content-Type": "application/json",
    ...((init.headers as Record<string, string>) ?? {}),
  };
  if (token) headers.Authorization = `Bearer ${token}`;

  const res = await fetch(url, { ...init, headers });
  const text = await res.text();
  const body = text ? safeJson(text) : null;

  if (!res.ok) {
    const err: ApiError = {
      status: res.status,
      message:
        (body && (body.message || body.error)) ||
        `Request failed (${res.status})`,
      errors: body?.errors,
    };
    throw err;
  }
  return body as T;
}

function safeJson(text: string): any {
  try {
    return JSON.parse(text);
  } catch {
    return null;
  }
}
