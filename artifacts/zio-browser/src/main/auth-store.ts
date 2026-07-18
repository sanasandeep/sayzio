/**
 * Secure auth token storage using Electron's safeStorage API.
 * safeStorage encrypts values with the OS keychain/credential store.
 */
import { safeStorage } from 'electron';
import { getPreference, setPreference } from './db';
import { PREFERENCE_KEYS } from '../shared/db-schema';

const TOKEN_PREF_KEY = 'auth_token_encrypted';
const USER_PREF_KEY = 'auth_user_json';

/**
 * Store the Sanctum token securely using safeStorage (OS keychain).
 * Falls back to a plain preference if safeStorage is not available.
 */
export function storeToken(plainToken: string): void {
  if (safeStorage.isEncryptionAvailable()) {
    const encrypted = safeStorage.encryptString(plainToken);
    setPreference(TOKEN_PREF_KEY as typeof PREFERENCE_KEYS[keyof typeof PREFERENCE_KEYS], encrypted.toString('base64'));
  } else {
    // Fallback: store plain (development environments without OS keychain)
    setPreference(TOKEN_PREF_KEY as typeof PREFERENCE_KEYS[keyof typeof PREFERENCE_KEYS], `plain:${plainToken}`);
  }
}

/**
 * Retrieve the stored Sanctum token.
 */
export function retrieveToken(): string | null {
  const stored = getPreference(TOKEN_PREF_KEY as typeof PREFERENCE_KEYS[keyof typeof PREFERENCE_KEYS]);
  if (!stored) return null;

  if (stored.startsWith('plain:')) {
    return stored.slice(6);
  }

  if (safeStorage.isEncryptionAvailable()) {
    try {
      const buf = Buffer.from(stored, 'base64');
      return safeStorage.decryptString(buf);
    } catch {
      return null;
    }
  }

  return null;
}

/**
 * Clear the stored token (on logout).
 */
export function clearToken(): void {
  setPreference(TOKEN_PREF_KEY as typeof PREFERENCE_KEYS[keyof typeof PREFERENCE_KEYS], '');
}

/**
 * Store the user JSON (non-sensitive — just cached profile data).
 */
export function storeUser(user: Record<string, unknown>): void {
  setPreference(USER_PREF_KEY as typeof PREFERENCE_KEYS[keyof typeof PREFERENCE_KEYS], JSON.stringify(user));
}

/**
 * Retrieve the cached user data.
 */
export function retrieveUser(): Record<string, unknown> | null {
  const stored = getPreference(USER_PREF_KEY as typeof PREFERENCE_KEYS[keyof typeof PREFERENCE_KEYS]);
  if (!stored) return null;
  try {
    return JSON.parse(stored) as Record<string, unknown>;
  } catch {
    return null;
  }
}

/**
 * Clear the cached user data (on logout).
 */
export function clearUser(): void {
  setPreference(USER_PREF_KEY as typeof PREFERENCE_KEYS[keyof typeof PREFERENCE_KEYS], '');
}
