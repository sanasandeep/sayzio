import { browser } from "./browser";

export interface ExtSettings {
  apiBaseUrl: string;
  webBaseUrl: string;
  token: string | null;
  user: { id: number; name: string; email: string; handle?: string | null } | null;
  workspaceId: number | null;
  workspaces: Array<{ id: number; name: string }>;
  // Contacts: extension preferences. Persisted in browser.storage.local.
  // contactDefaultTags — applied client-side before sending to /contacts.
  // contactAllowOneClick — gates the "One-click save" button.
  // contactWorkspaceId  — overrides the active workspace for contact saves.
  contactDefaultTags: string[];
  contactAllowOneClick: boolean;
  contactWorkspaceId: number | null;
}

const DEFAULT_API = "https://1inme.com/api/v1";
const DEFAULT_WEB = "https://1inme.com";

export const defaultSettings: ExtSettings = {
  apiBaseUrl: DEFAULT_API,
  webBaseUrl: DEFAULT_WEB,
  token: null,
  user: null,
  workspaceId: null,
  workspaces: [],
  contactDefaultTags: ["from-extension"],
  contactAllowOneClick: true,
  contactWorkspaceId: null,
};

export async function getSettings(): Promise<ExtSettings> {
  const stored = (await browser.storage.local.get(Object.keys(defaultSettings))) as Partial<ExtSettings>;
  return { ...defaultSettings, ...stored };
}

export async function setSettings(patch: Partial<ExtSettings>): Promise<ExtSettings> {
  await browser.storage.local.set(patch as Record<string, unknown>);
  return getSettings();
}

export async function clearAuth(): Promise<void> {
  await browser.storage.local.remove(["token", "user", "workspaceId", "workspaces"]);
}
