import React, { useEffect, useState, useCallback } from "react";
import { browser } from "../lib/browser";
import { ApiError, api } from "../lib/api";
import { ExtSettings, clearAuth, getSettings, setSettings } from "../lib/storage";

type Toast = { kind: "success" | "error" | "info"; text: string; link?: { href: string; label: string } } | null;

export function App() {
  const [settings, setLocalSettings] = useState<ExtSettings | null>(null);
  const [tabUrl, setTabUrl] = useState<string>("");
  const [tabTitle, setTabTitle] = useState<string>("");
  const [tabId, setTabId] = useState<number | null>(null);
  const [toast, setToast] = useState<Toast>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [view, setView] = useState<"main" | "login" | "settings">("main");

  const refresh = useCallback(async () => {
    const s = await getSettings();
    setLocalSettings(s);
    setView(s.token ? "main" : "login");
  }, []);

  useEffect(() => {
    refresh();
    browser.tabs.query({ active: true, currentWindow: true }).then((tabs) => {
      const t = tabs[0];
      if (t) {
        setTabUrl(t.url || "");
        setTabTitle(t.title || "");
        setTabId(t.id ?? null);
      }
    });
    const listener = (changes: any, area: string) => {
      if (area === "local") refresh();
    };
    browser.storage.onChanged.addListener(listener);
    return () => browser.storage.onChanged.removeListener(listener);
  }, [refresh]);

  const showToast = useCallback((t: Toast) => {
    setToast(t);
    if (t) setTimeout(() => setToast(null), 4000);
  }, []);

  const handleShorten = async () => {
    if (!tabUrl) return;
    setBusy("shorten");
    try {
      const resp: any = await browser.runtime.sendMessage({ type: "SHORTEN_URL", url: tabUrl, title: tabTitle });
      if (resp?.ok) {
        showToast({
          kind: "success",
          text: `Shortened: ${resp.shortUrl}`,
          link: { href: `${settings?.webBaseUrl}/dashboard/links/${resp.linkId}`, label: "View analytics" },
        });
      } else {
        showToast({ kind: "error", text: resp?.error || "Shorten failed" });
      }
    } catch (e: any) {
      showToast({ kind: "error", text: e?.message || "Shorten failed" });
    } finally {
      setBusy(null);
    }
  };

  const handleBiolink = async () => {
    if (!tabId) return;
    setBusy("biolink");
    try {
      const resp: any = await browser.runtime.sendMessage({ type: "PAGE_TO_BIOLINK", tabId });
      if (resp?.ok) {
        showToast({ kind: "success", text: "Bio-link draft created — opening editor…" });
      } else {
        showToast({ kind: "error", text: resp?.error || "Could not create bio-link" });
      }
    } catch (e: any) {
      showToast({ kind: "error", text: e?.message || "Could not create bio-link" });
    } finally {
      setBusy(null);
    }
  };

  const handleSignOut = async () => {
    setBusy("signout");
    try {
      await browser.runtime.sendMessage({ type: "SIGN_OUT" });
    } finally {
      await clearAuth();
      await refresh();
      setBusy(null);
    }
  };

  if (!settings) {
    return (
      <div className="body">
        <span className="muted">Loading…</span>
      </div>
    );
  }

  return (
    <>
      <Header settings={settings} onSettings={() => setView(view === "settings" ? "main" : "settings")} />
      {view === "login" && <LoginView settings={settings} onAuthed={refresh} showToast={showToast} />}
      {view === "settings" && <SettingsView settings={settings} onSaved={refresh} />}
      {view === "main" && (
        <div className="body">
          <div className="field">
            <label>Current page</label>
            <div className="url-card" title={tabUrl}>{tabUrl || "(no active tab)"}</div>
          </div>
          {settings.workspaces.length > 1 ? (
            <div className="field">
              <label>Workspace</label>
              <select
                className="workspace-select"
                value={settings.workspaceId ?? ""}
                onChange={(e) => setSettings({ workspaceId: Number(e.target.value) }).then(refresh)}
              >
                {settings.workspaces.map((w) => (
                  <option key={w.id} value={w.id}>{w.name}</option>
                ))}
              </select>
            </div>
          ) : settings.workspaces.length === 1 ? (
            <div className="field">
              <label>Workspace</label>
              <div className="muted">{settings.workspaces[0].name}</div>
            </div>
          ) : null}
          <button className="btn-primary" disabled={!tabUrl || busy !== null} onClick={handleShorten}>
            {busy === "shorten" && <span className="spinner" />}Shorten &amp; copy
          </button>
          <button className="btn-secondary" disabled={!tabId || busy !== null} onClick={handleBiolink}>
            {busy === "biolink" && <span className="spinner" />}Turn into bio-link page
          </button>
        </div>
      )}
      <Footer settings={settings} view={view} onSignOut={handleSignOut} onSettings={() => setView(view === "settings" ? "main" : "settings")} busy={busy} />
      {toast && (
        <div className={`toast ${toast.kind}`}>
          <div className="row">
            <span>{toast.text}</span>
            <button className="btn-link" style={{ color: "white" }} onClick={() => setToast(null)}>×</button>
          </div>
          {toast.link && <a href={toast.link.href} target="_blank" rel="noreferrer">{toast.link.label}</a>}
        </div>
      )}
    </>
  );
}

function Header({ settings, onSettings }: { settings: ExtSettings; onSettings: () => void }) {
  return (
    <div className="header">
      <div>
        <h1>1INME</h1>
        {settings.user && <div className="who">{settings.user.name || settings.user.email}</div>}
      </div>
      <button className="btn-link" style={{ color: "white" }} onClick={onSettings} title="Settings">⚙</button>
    </div>
  );
}

function Footer({
  settings, view, onSignOut, onSettings, busy,
}: { settings: ExtSettings; view: string; onSignOut: () => void; onSettings: () => void; busy: string | null }) {
  return (
    <div className="footer">
      <span>{settings.token ? "Signed in" : "Not signed in"}</span>
      <span>
        {settings.token && view !== "settings" && (
          <button className="btn-link" disabled={busy !== null} onClick={onSignOut}>Sign out</button>
        )}
      </span>
    </div>
  );
}

function LoginView({ settings, onAuthed, showToast }: { settings: ExtSettings; onAuthed: () => void; showToast: (t: Toast) => void }) {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErr(null);
    setBusy(true);
    try {
      const result = await api.login(email, password);
      await setSettings({ token: result.token, user: result.user });
      try {
        const ws = await api.workspaces();
        const items = Array.isArray(ws) ? ws : (ws?.items ?? []);
        await setSettings({
          workspaces: items.map((w: any) => ({ id: w.id, name: w.name })),
          workspaceId: items[0]?.id ?? null,
        });
      } catch { /* workspaces optional */ }
      showToast({ kind: "success", text: "Signed in" });
      onAuthed();
    } catch (e: any) {
      setErr(e instanceof ApiError ? e.message : (e?.message || "Sign in failed"));
    } finally {
      setBusy(false);
    }
  };

  const startSso = async () => {
    const url = `${settings.webBaseUrl}/extension/handshake`;
    await browser.tabs.create({ url });
  };

  return (
    <div className="body">
      <p className="muted">Sign in to your 1INME account to start shortening links and creating bio-link pages from any tab.</p>
      <button className="btn-primary" onClick={startSso}>Sign in with 1INME</button>
      <div className="muted" style={{ textAlign: "center" }}>or use email + password</div>
      <form onSubmit={submit} style={{ display: "flex", flexDirection: "column", gap: 8 }}>
        <div className="field">
          <label>Email</label>
          <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
        </div>
        <div className="field">
          <label>Password</label>
          <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />
        </div>
        {err && <div className="error-text">{err}</div>}
        <button type="submit" className="btn-primary" disabled={busy}>{busy && <span className="spinner" />}Sign in</button>
      </form>
    </div>
  );
}

function SettingsView({ settings, onSaved }: { settings: ExtSettings; onSaved: () => void }) {
  const [api, setApi] = useState(settings.apiBaseUrl);
  const [web, setWeb] = useState(settings.webBaseUrl);
  const [saved, setSaved] = useState(false);

  const save = async () => {
    await setSettings({ apiBaseUrl: api.replace(/\/$/, ""), webBaseUrl: web.replace(/\/$/, "") });
    setSaved(true);
    setTimeout(() => setSaved(false), 1500);
    onSaved();
  };
  const reset = async () => {
    await setSettings({ apiBaseUrl: "https://1inme.com/api/v1", webBaseUrl: "https://1inme.com" });
    onSaved();
  };

  return (
    <div className="body">
      <div className="settings-row">
        <label>API base URL</label>
        <input value={api} onChange={(e) => setApi(e.target.value)} />
        <span className="muted">Default: https://1inme.com/api/v1</span>
      </div>
      <div className="settings-row">
        <label>Web base URL</label>
        <input value={web} onChange={(e) => setWeb(e.target.value)} />
        <span className="muted">Used to open the editor and dashboard.</span>
      </div>
      <button className="btn-primary" onClick={save}>{saved ? "Saved ✓" : "Save"}</button>
      <button className="btn-secondary" onClick={reset}>Reset to defaults</button>
    </div>
  );
}
