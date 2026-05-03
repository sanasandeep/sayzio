// SHA-256 helpers shared by the radar's content + background scripts.
// We hash candidate path segments (slugs) and check membership in the
// short prefix list shipped by the API. Hashing means the slug list
// itself never lives in extension memory and we can't reverse-engineer
// a creator's full link inventory from what we ship to the popup.

export async function sha256HexPrefix(input: string, prefixLen = 12): Promise<string> {
  const enc = new TextEncoder().encode(input.toLowerCase());
  const buf = await crypto.subtle.digest("SHA-256", enc);
  const bytes = new Uint8Array(buf);
  let hex = "";
  for (let i = 0; i < bytes.length && hex.length < prefixLen; i++) {
    hex += bytes[i].toString(16).padStart(2, "0");
  }
  return hex.slice(0, prefixLen);
}
