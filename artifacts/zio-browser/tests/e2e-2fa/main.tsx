/**
 * e2e harness entry for the AuthModal 2FA flow.
 *
 * Renders the REAL AuthModal component in a plain browser page so a Playwright
 * spec (artifacts/1inme/tests/Browser/zio-browser-2fa-auth.spec.ts) can drive
 * the actual sign-in UI against the live Laravel API. The only shim is
 * `window.zio.auth` (normally provided by the Electron preload bridge): here it
 * records the stored token/user on `window.__zio` so the spec can assert the
 * flow completed and then prove the issued token against the real API.
 */
import { createRoot } from 'react-dom/client';
import { AuthModal } from '../../src/renderer/components/AuthModal';

interface HarnessAuthState {
  token: string | null;
  user: Record<string, unknown> | null;
}

interface HarnessWindow {
  __zio: HarnessAuthState;
  __modalClosed: boolean;
  zio: unknown;
}

const w = window as unknown as HarnessWindow;

w.__zio = { token: null, user: null };
w.__modalClosed = false;

// Minimal stand-in for the preload bridge — only the surface AuthModal's
// auth-store touches.
w.zio = {
  auth: {
    getToken: async () => w.__zio.token,
    getUser: async () => w.__zio.user,
    storeToken: async (token: string) => { w.__zio.token = token; },
    storeUser: async (user: Record<string, unknown>) => { w.__zio.user = user; },
    clear: async () => { w.__zio = { token: null, user: null }; },
  },
};

const rootEl = document.getElementById('root');
if (!rootEl) throw new Error('harness root element missing');

createRoot(rootEl).render(
  <AuthModal onClose={() => { w.__modalClosed = true; }} />,
);
