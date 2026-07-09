import {
  apiFetch,
  getBaseUrl,
  MOBILE_USER_AGENT,
  type ApiError,
} from "@/lib/api";
import { getToken } from "@/lib/secure";

export type AiChatTheme = "auto" | "light" | "dark";

export type AiChatConfig = {
  greeting: string | null;
  placeholder: string;
  accent: string;
  theme: AiChatTheme;
  show_branding: boolean;
  ground_in_profile: boolean;
  avatar_url: string | null;
  custom_branding_text: string | null;
  custom_branding_url: string | null;
};

export type AiChatBranding = {
  can_hide_branding: boolean;
  can_custom_branding: boolean;
  can_avatar: boolean;
};

export type AiChatPersona = { id: number; name: string };

export type AiChatPage = {
  link_id: number;
  alias: string;
  public_url: string;
  name: string;
  persona_id: number | null;
  config: AiChatConfig;
  branding: AiChatBranding;
  starters: string[];
  usage: {
    turns: number;
    free_turns_per_month: number;
    hard_cap_per_month: number;
  };
  ai_enabled: boolean;
};

export type AiChatEditor = {
  ai_chat: AiChatPage;
  personas: AiChatPersona[];
  /** Worst-case coins one visitor turn may debit from the owner's wallet. */
  coin_cost?: number;
  /** Owner's wallet balance (shared coin_cost + coin_balance pattern). */
  coin_balance?: number;
};

export async function getAiChat(id: number): Promise<AiChatEditor> {
  const res = await apiFetch<{ data: AiChatEditor }>(`/links/${id}/ai-chat`);
  return res.data;
}

// A picture the creator picked from their device for the agent avatar.
// `uri` is the local file:// path expo-image-picker returns; the rest is
// best-effort metadata filled in by the picker.
export type AiAvatarUpload = {
  uri: string;
  mimeType?: string | null;
  fileName?: string | null;
  // Byte size from the picker when known; used for the client-side cap check.
  size?: number | null;
};

// The server caps the agent avatar at 2MB and accepts only the extensions
// below (see UploadPolicy `link.ai_avatar`). We mirror both here so oversized
// / unsupported files are caught before uploading instead of a server 422.
export const AI_AVATAR_MAX_BYTES = 2 * 1024 * 1024;
export const AI_AVATAR_ALLOWED_EXTENSIONS = [
  "jpg",
  "jpeg",
  "png",
  "webp",
  "gif",
];

function avatarExtForMime(mime: string): string {
  const m = mime.toLowerCase();
  if (m.includes("jpeg") || m.includes("jpg")) return "jpg";
  if (m.includes("png")) return "png";
  if (m.includes("webp")) return "webp";
  if (m.includes("gif")) return "gif";
  return "jpg";
}

// Resolve the extension we'd validate against. Unlike `avatarExtForMime`
// (used for the upload filename, where a jpg fallback is harmless), this is
// strict: a recognised-but-unsupported type (e.g. iOS HEIC) returns
// "unsupported" so it's caught client-side, while a pick with no usable
// metadata at all returns null and gets the benefit of the doubt (the server
// is the backstop) rather than being wrongly rejected.
function avatarExtForValidation(a: AiAvatarUpload): string | null {
  if (a.fileName && a.fileName.includes(".")) {
    const e = a.fileName.split(".").pop()?.toLowerCase();
    if (e) return e;
  }
  const m = (a.mimeType || "").toLowerCase();
  if (!m) return null;
  if (m.includes("jpeg") || m.includes("jpg")) return "jpg";
  if (m.includes("png")) return "png";
  if (m.includes("webp")) return "webp";
  if (m.includes("gif")) return "gif";
  return "unsupported";
}

/**
 * Validate a picked avatar against the server's limits. Returns a clear,
 * user-facing message when the file would be rejected, or null when it's fine.
 */
export function validateAiAvatar(a: AiAvatarUpload): string | null {
  const ext = avatarExtForValidation(a);
  if (ext !== null && !AI_AVATAR_ALLOWED_EXTENSIONS.includes(ext)) {
    return "Unsupported image type. Use a JPG, PNG, WebP or GIF.";
  }
  if (typeof a.size === "number" && a.size > AI_AVATAR_MAX_BYTES) {
    const mb = (a.size / (1024 * 1024)).toFixed(1);
    return `That image is ${mb}MB, over the 2MB limit. Please pick a smaller one.`;
  }
  return null;
}

type SaveAiChatPayload = {
  name: string;
  persona_id: number;
  config: AiChatConfig;
  starters: string[];
};

export async function saveAiChat(
  id: number,
  payload: SaveAiChatPayload,
): Promise<AiChatEditor> {
  const res = await apiFetch<{ data: AiChatEditor }>(`/links/${id}/ai-chat`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });
  return res.data;
}

/**
 * Save the AI chat page together with a device-picked avatar image, bringing
 * the mobile editor to web parity (web supports file upload / URL / vault).
 *
 * The save route is `PUT /links/{id}/ai-chat`, but PHP only parses multipart
 * bodies on POST — so we POST with Laravel's `_method=PUT` spoof and let the
 * controller's existing `avatar_upload` handling store the file in the vault.
 *
 * NB: do NOT set Content-Type — React Native fills in the multipart boundary
 * for us when the body is a FormData. We use XMLHttpRequest because RN's
 * fetch() can't report upload progress.
 */
export async function saveAiChatWithAvatar(
  id: number,
  payload: SaveAiChatPayload,
  avatar: AiAvatarUpload,
  onProgress?: (fraction: number) => void,
): Promise<AiChatEditor> {
  const problem = validateAiAvatar(avatar);
  if (problem) {
    const err: ApiError = { status: 0, message: problem };
    throw err;
  }

  const url = `${getBaseUrl()}/api/v1/links/${id}/ai-chat`;
  const token = await getToken();

  const fd = new FormData();
  // Laravel method spoofing — POST carries the multipart body, PUT routes it.
  fd.append("_method", "PUT");
  fd.append("name", payload.name);
  fd.append("persona_id", String(payload.persona_id));

  const c = payload.config;
  // Omit greeting when blank (mergeConfig treats a missing key as null, which
  // matches the JSON path that sends `greeting: null`).
  if (c.greeting) fd.append("config[greeting]", c.greeting);
  fd.append("config[placeholder]", c.placeholder);
  fd.append("config[accent]", c.accent);
  fd.append("config[theme]", c.theme);
  // Booleans must be "1"/"0" — PHP casts the string "false" to true.
  fd.append("config[show_branding]", c.show_branding ? "1" : "0");
  fd.append("config[ground_in_profile]", c.ground_in_profile ? "1" : "0");
  if (c.custom_branding_text)
    fd.append("config[custom_branding_text]", c.custom_branding_text);
  if (c.custom_branding_url)
    fd.append("config[custom_branding_url]", c.custom_branding_url);

  payload.starters.forEach((s) => fd.append("starters[]", s));

  // The uploaded file wins over config.avatar_url in the controller, so we
  // don't send avatar_url here — it's resolved from the stored file.
  const mime = avatar.mimeType || "image/jpeg";
  const name = avatar.fileName || `ai-avatar.${avatarExtForMime(mime)}`;
  fd.append("avatar_upload", {
    // eslint-disable-next-line @typescript-eslint/ban-ts-comment
    // @ts-ignore – RN-specific FormData entry.
    uri: avatar.uri,
    name,
    type: mime,
  } as any);

  return new Promise<AiChatEditor>((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open("POST", url);
    xhr.responseType = "text";
    xhr.setRequestHeader("Accept", "application/json");
    xhr.setRequestHeader("X-1INME-Client", MOBILE_USER_AGENT);
    if (token) xhr.setRequestHeader("Authorization", `Bearer ${token}`);
    // NB: do NOT set Content-Type — RN fills the multipart boundary in.

    if (xhr.upload && onProgress) {
      xhr.upload.onprogress = (e) => {
        if (e.lengthComputable && e.total > 0) {
          onProgress(Math.min(1, e.loaded / e.total));
        }
      };
    }

    const parse = (): any => {
      try {
        return xhr.responseText ? JSON.parse(xhr.responseText) : null;
      } catch {
        return null;
      }
    };

    xhr.onload = () => {
      const parsed = parse();
      if (xhr.status >= 200 && xhr.status < 300) {
        onProgress?.(1);
        resolve(parsed?.data as AiChatEditor);
        return;
      }
      const nested =
        parsed && typeof parsed.error === "object" ? parsed.error : null;
      const err: ApiError = {
        status: xhr.status,
        message:
          (nested?.message && String(nested.message)) ||
          (parsed?.message && String(parsed.message)) ||
          `Could not save the avatar (${xhr.status})`,
        code:
          (nested && typeof nested.code === "string" ? nested.code : null) ||
          undefined,
        errors: parsed?.errors ?? nested?.details,
        details:
          nested?.details &&
          typeof nested.details === "object" &&
          !Array.isArray(nested.details)
            ? (nested.details as Record<string, unknown>)
            : undefined,
      };
      reject(err);
    };

    xhr.onerror = () => {
      reject({
        status: 0,
        message: "Upload failed. Check your connection and try again.",
      } as ApiError);
    };

    xhr.send(fd as any);
  });
}
