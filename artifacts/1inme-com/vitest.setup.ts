import { afterEach, vi } from "vitest";
import { cleanup } from "@testing-library/react";

// jsdom ships no matchMedia. The Zio Bot widget calls it (theme detection,
// reduced-motion nudge), so provide an inert "no, and never changes" stub.
if (!window.matchMedia) {
  window.matchMedia = ((query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addEventListener: () => {},
    removeEventListener: () => {},
    addListener: () => {},
    removeListener: () => {},
    dispatchEvent: () => false,
  })) as unknown as typeof window.matchMedia;
}

// scrollBottom() uses requestAnimationFrame; older jsdom builds lack it.
if (typeof window.requestAnimationFrame !== "function") {
  window.requestAnimationFrame = ((cb: FrameRequestCallback) =>
    setTimeout(() => cb(Date.now()), 0) as unknown as number) as typeof window.requestAnimationFrame;
  window.cancelAnimationFrame = ((id: number) =>
    clearTimeout(id as unknown as ReturnType<typeof setTimeout>)) as typeof window.cancelAnimationFrame;
}

afterEach(() => {
  cleanup();
  localStorage.clear();
  vi.restoreAllMocks();
});
