import { StrictMode, Component, type ReactNode } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import './styles/global.css';

/**
 * Last-resort fallback: if the chrome UI crashes before or during boot, show
 * a visible recovery screen instead of a permanently white window.
 */
function showFatalFallback(detail: string): void {
  const existing = document.getElementById('zio-fatal-fallback');
  if (existing) return;
  const el = document.createElement('div');
  el.id = 'zio-fatal-fallback';
  el.setAttribute(
    'style',
    'position:fixed;inset:0;z-index:99999;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;background:#101418;color:#e8eaed;font-family:-apple-system,system-ui,sans-serif;text-align:center;padding:32px;',
  );
  const title = document.createElement('div');
  title.textContent = 'Zio Browser hit a problem while starting';
  title.setAttribute('style', 'font-size:18px;font-weight:600;');
  const msg = document.createElement('div');
  msg.textContent = detail.slice(0, 300);
  msg.setAttribute('style', 'font-size:12px;opacity:.7;max-width:520px;word-break:break-word;');
  const row = document.createElement('div');
  row.setAttribute('style', 'display:flex;gap:10px;margin-top:6px;');
  const mkBtn = (label: string, onClick: () => void): HTMLButtonElement => {
    const b = document.createElement('button');
    b.textContent = label;
    b.setAttribute(
      'style',
      'padding:8px 18px;border-radius:8px;border:1px solid #3c4043;background:#202124;color:#e8eaed;font-size:13px;cursor:pointer;',
    );
    b.addEventListener('click', onClick);
    return b;
  };
  row.appendChild(mkBtn('Reload', () => { window.location.reload(); }));
  row.appendChild(
    mkBtn('Reset & reload', () => {
      try { window.localStorage.clear(); } catch { /* keep going */ }
      try { window.sessionStorage.clear(); } catch { /* keep going */ }
      window.location.reload();
    }),
  );
  el.appendChild(title);
  el.appendChild(msg);
  el.appendChild(row);
  document.body.appendChild(el);
}

// Catch boot-time errors that would otherwise leave the window blank. Only
// escalate to the fallback while the app shell hasn't rendered yet — once the
// UI is up, stray async errors shouldn't nuke the whole chrome.
let appRendered = false;
window.addEventListener('error', (e) => {
  if (!appRendered) showFatalFallback(String(e.error ?? e.message ?? 'Unknown error'));
});
window.addEventListener('unhandledrejection', (e) => {
  if (!appRendered) showFatalFallback(String(e.reason ?? 'Unknown error'));
});

/** Root error boundary — a crash anywhere in the tree shows the recovery UI. */
class RootErrorBoundary extends Component<{ children: ReactNode }, { failed: boolean }> {
  override state = { failed: false };
  static getDerivedStateFromError(): { failed: boolean } {
    return { failed: true };
  }
  override componentDidCatch(error: unknown): void {
    showFatalFallback(error instanceof Error ? error.message : String(error));
  }
  override render(): ReactNode {
    return this.state.failed ? null : this.props.children;
  }
}

try {
  const root = document.getElementById('root');
  if (!root) throw new Error('Root element not found');
  createRoot(root).render(
    <StrictMode>
      <RootErrorBoundary>
        <App />
      </RootErrorBoundary>
    </StrictMode>,
  );
  appRendered = true;
} catch (err) {
  showFatalFallback(err instanceof Error ? (err.stack ?? err.message) : String(err));
}
