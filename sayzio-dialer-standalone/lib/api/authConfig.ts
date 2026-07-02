import { apiFetch } from "@/lib/api";

export type AuthConfig = {
  mobileLoginEnabled: boolean;
  allowedCountryCodes: string[];
};

/**
 * Fetch the public login-method policy. Email is always accepted; WhatsApp
 * (mobile) login is behind an admin toggle with an allowed-country-code list.
 * Falls back to email-only when the request fails so the UI degrades safely.
 */
export async function getAuthConfig(): Promise<AuthConfig> {
  try {
    const res = await apiFetch<{
      data?: { mobile_login_enabled?: boolean; allowed_country_codes?: string[] };
    }>("/auth/config");
    const data = res?.data ?? {};
    return {
      mobileLoginEnabled: Boolean(data.mobile_login_enabled),
      allowedCountryCodes: Array.isArray(data.allowed_country_codes)
        ? data.allowed_country_codes.map(String)
        : [],
    };
  } catch {
    return { mobileLoginEnabled: false, allowedCountryCodes: [] };
  }
}

/** Does this number start with one of the allowed dialling codes (digits-only compare)? */
export function isAllowedCountryCode(number: string, codes: string[]): boolean {
  const digits = number.replace(/\D+/g, "");
  if (!digits) return false;
  return codes.some((code) => {
    const codeDigits = code.replace(/\D+/g, "");
    return codeDigits !== "" && digits.startsWith(codeDigits);
  });
}
