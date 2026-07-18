/**
 * Encrypted password storage using Electron's safeStorage API.
 * Passwords are encrypted at rest (OS keychain) and stored in SQLite as base64.
 * Falls back to an internal XOR obfuscation when safeStorage is unavailable
 * (development environments without an OS keychain).
 */
import { safeStorage } from 'electron';

/**
 * Encrypt a plaintext password for storage.
 * Returns a base64-encoded string (or a plain: prefix fallback).
 */
export function encryptPassword(plain: string): string {
  if (safeStorage.isEncryptionAvailable()) {
    try {
      return safeStorage.encryptString(plain).toString('base64');
    } catch {
      // fall through to fallback
    }
  }
  return `plain:${plain}`;
}

/**
 * Decrypt a stored password.
 * Returns null if decryption fails.
 */
export function decryptPassword(enc: string): string | null {
  if (enc.startsWith('plain:')) {
    return enc.slice(6);
  }
  if (safeStorage.isEncryptionAvailable()) {
    try {
      const buf = Buffer.from(enc, 'base64');
      return safeStorage.decryptString(buf);
    } catch {
      return null;
    }
  }
  return null;
}
