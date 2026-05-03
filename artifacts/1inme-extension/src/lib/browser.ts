import browserPolyfill from "webextension-polyfill";

export const browser = browserPolyfill;
export type Browser = typeof browserPolyfill;
