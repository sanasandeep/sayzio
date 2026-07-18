/**
 * Type declaration for the window.zio API exposed by the preload script.
 */
import type { ZioApi } from '../preload/index';

declare global {
  interface Window {
    zio: ZioApi;
  }
}

export {};
