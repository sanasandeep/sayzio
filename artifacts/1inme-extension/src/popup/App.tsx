import React, { useEffect, useState, useCallback } from "react";
import { browser } from "../lib/browser";
import { ApiError, api, AbVariantsPayload, WorkspacePixels } from "../lib/api";
import { ExtSettings, clearAuth, getSettings, setSettings } from "../lib/storage";

type Toast = { kind: "success" | "error" | "info"; text: string; link?: { href: string; label: string } } | null;

type AbTestItem = { link: { id: number; alias: string; short_url?: string; title?: string }; variants: AbVariantsPayload };

type ExtractionSource = "vcard" | "hcard" | "jsonld" | "scraped" | "manual";
interface ContactCandidate {
  display_name: string | null;
  given_name: string | null;
  family_name: string | null;
  organization: string | null;
  job_title: string | null;
  website: string | null;
  notes: string | null;
  emails: Array<{ value: string; label?: string; source: ExtractionSource }>;
  phones: Array<{ value: string; label?: string; country?: string; source: ExtractionSource }>;
  socials: Record<string, string>;
  source_url: string;
  source_title: string;
  provenance: Record<string, ExtractionSource>;
  structured: boolean;
  tags?: string[];
}

type View = "main" | "login" | "settings" | "ab" | "contact-preview";

export function App() {
  const [settings, setLocalSettings] = useState<ExtSettings | null>(null);
  const [tabUrl, setTabUrl] = useState<string>("");
  const [tabTitle, setTabTitle] = useState<string>("");
  const [tabId, setTabId] = useState<number | null>(null);
  const [toast, setToast] = useState<Toast>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [view, setView] = useState<View>("main");
  const [abTests, setAbTests] = useState<AbTestItem[]>([]);
  const [abLoading, setAbLoading] = useState(false);
  const [pixels, setPixels] = useState<WorkspacePixels | null>(null);
  // Per-link auto-pixel toggle for the *next* link the popup will create.
  // Initialised from the workspace's pixel state so creators with pixels
  // configured opt in by default; creators without pixels stay off.
  const [autoPixel, setAutoPixel] = useState<boolean>(false);
  const [recent, setRecent] = useState<Array<{ id: number; alias: string; title: string | null; long_url: string | null; short_url?: string; auto_pixel?: boolean; pixel_fires?: { count: number; providers: string[] } }>>([]);
  const [candidate, setCandidate] = useState<ContactCandidate | null>(null);
  const [extractError, setExtractError] = useState<string | null>(null);

  const loadAbTests = useCallback(async () => {
    setAbLoading(true);
    try {
      const resp = await api.listAbTests();
      setAbTests(resp.items || []);
    } catch {
      // silently swallow — user might be on a plan without AB tests
      setAbTests([]);
    } finally {
      setAbLoading(false);
    }
  }, []);

  const refresh = useCallback(async () => {
    const s = await getSettings();
    setLocalSettings(s);
    setView((v) => (v === "contact-preview" ? v : (s.token ? (v === "login" ? "main" : v) : "login")));
  }, []);

  useEffect(() => {
    if (settings?.token && view === "main") {
      loadAbTests();
    }
  }, [settings?.token, view, loadAbTests]);

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
    // Pending candidate handed off from the context-menu flow.
    browser.storage.local.get("pendingContactCandidate").then((res: any) => {
      const pending = res?.pendingContactCandidate;
      if (pending && pending.candidate) {
        setCandidate(pending.candidate);
        setView("contact-preview");
        browser.storage.local.remove("pendingContactCandidate");
      }
    });
    return () => browser.storage.onChanged.removeListener(listener);
  }, [refresh]);

  // Load workspace pixel config + recent links whenever auth/workspace changes
  // so the popup can show the "Pixels: Meta, TikTok" badge and the per-link
  // toggle defaults match the workspace's pixel state.
  useEffect(() => {
    if (!settings?.token) { setPixels(null); setRecent([]); return; }
    let cancelled = false;
    (async () => {
      try {
        const r = await api.getWorkspacePixels(settings.workspaceId);
        if (!cancelled) {
          setPixels(r.pixels);
          setAutoPixel(!!r.pixels.has_any);
        }
      } catch { if (!cancelled) setPixels(null); }
      try {
        const r = await api.recentLinks(8);
        if (!cancelled) setRecent(r.items || []);
      } catch { /* ignore */ }
    })();
    return () => { cancelled = true; };
  }, [settings?.token, settings?.workspaceId]);

  const showToast = useCallback((t: Toast) => {
    setToast(t);
    if (t) setTimeout(() => setToast(null), 4500);
  }, []);

  const handleShorten = async () => {
    if (!tabUrl) return;
    setBusy("shorten");
    try {
      const resp: any = await browser.runtime.sendMessage({
        type: "SHORTEN_URL", url: tabUrl, title: tabTitle, autoPixel,
      });
      if (resp?.ok) {
        showToast({
          kind: "success",
          text: `Shortened: ${resp.shortUrl}`,
          link: { href: `${settings?.webBaseUrl}/dashboard/links/${resp.linkId}`, label: "View analytics" },
        });
        // Refresh recent-links list so the new row appears with its toggle.
        try { const r = await api.recentLinks(8); setRecent(r.items || []); } catch {}
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
    } finally {
      setBusy(null);
    }
  };

  const extractCandidate = async (): Promise<ContactCandidate | null> => {
    if (!tabId) return null;
    const results = await browser.scripting.executeScript({
      target: { tabId },
      files: ["content-extract-contact.js"],
    });
    const resp = results?.[0]?.result as any;
    if (!resp || resp.ok !== true) {
      setExtractError(resp?.error || "Could not read this page.");
      return null;
    }
    setExtractError(null);
    // Apply default tags from settings.
    const tags = Array.from(new Set([...(settings?.contactDefaultTags || [])]));
    return { ...(resp.candidate as ContactCandidate), tags };
  };

  const handleSaveContact = async () => {
    setBusy("contact-extract");
    try {
      const c = await extractCandidate();
      if (c) { setCandidate(c); setView("contact-preview"); }
      else if (extractError) showToast({ kind: "error", text: extractError });
    } catch (e: any) {
      showToast({ kind: "error", text: e?.message || "Extraction failed" });
    } finally {
      setBusy(null);
    }
  };

  const handleOneClickContact = async () => {
    setBusy("contact-oneclick");
    try {
      const c = await extractCandidate();
      if (!c) {
        if (extractError) showToast({ kind: "error", text: extractError });
        return;
      }
      // Server validates strictly; on duplicate / errors we fall back to
      // the editable preview so the creator can fix or merge.
      try {
        const result = await api.createContact(buildPayload(c, settings), true);
        const dashboard = `${settings?.webBaseUrl}/dashboard/contacts/${result.contact.id}`;
        showToast({
          kind: "success",
          text: `Saved: ${result.contact.display_name}`,
          link: { href: dashboard, label: "Open contact" },
        });
      } catch (e: any) {
        if (e instanceof ApiError && (e.code === "contact_invalid" || e.code === "contact_duplicate")) {
          // Surface the editable card with server-side errors highlighted.
          setCandidate(c);
          setView("contact-preview");
          if (e.code === "contact_invalid") {
            showToast({ kind: "info", text: "Couldn't save in one click — please review fields." });
          }
        } else {
          showToast({ kind: "error", text: e?.message || "Save failed" });
        }
      }
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

  const toggleRecentAutoPixel = async (linkId: number, next: boolean) => {
    setRecent((prev) => prev.map((r) => r.id === linkId ? { ...r, auto_pixel: next } : r));
    try {
      await api.updateLink(linkId, { auto_pixel: next });
    } catch (e: any) {
      showToast({ kind: "error", text: e?.message || "Could not update link" });
      // Revert on failure.
      setRecent((prev) => prev.map((r) => r.id === linkId ? { ...r, auto_pixel: !next } : r));
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
      {view === "settings" && (
        <SettingsView
          settings={settings}
          pixels={pixels}
          onSaved={refresh}
          onPixelsSaved={(p) => { setPixels(p); setAutoPixel(p.has_any); }}
          showToast={showToast}
        />
      )}
      {view === "ab" && (
        <AbBuilder
          tabUrl={tabUrl}
          tabTitle={tabTitle}
          workspaceId={settings.workspaceId ?? null}
          onCancel={() => setView("main")}
          onCreated={(shortUrl) => {
            setView("main");
            loadAbTests();
            showToast({ kind: "success", text: `A/B test live: ${shortUrl}` });
          }}
          onError={(msg) => showToast({ kind: "error", text: msg })}
        />
      )}
      {view === "contact-preview" && candidate && (
        <ContactPreview
          candidate={candidate}
          settings={settings}
          onCancel={() => { setCandidate(null); setView("main"); }}
          onSaved={(name, id) => {
            setCandidate(null);
            setView("main");
            const dashboard = `${settings.webBaseUrl}/dashboard/contacts/${id}`;
            showToast({ kind: "success", text: `Saved: ${name}`, link: { href: dashboard, label: "Open contact" } });
          }}
          showToast={showToast}
        />
      )}
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
          {pixels && pixels.has_any && (
            <div className="field">
              <div className="pixel-badge" title="Workspace tracking pixels that will fire on click">
                Pixels:{" "}
                {pixels.configured.map((p) => p === "meta" ? "Meta" : p === "tiktok" ? "TikTok" : "Google")
                  .join(", ")}
              </div>
              <label className="toggle-row">
                <input type="checkbox" checked={autoPixel} onChange={(e) => setAutoPixel(e.target.checked)} />
                <span>Auto-fire on this link</span>
              </label>
            </div>
          )}
          <button className="btn-primary" disabled={!tabUrl || busy !== null} onClick={handleShorten}>
            {busy === "shorten" && <span className="spinner" />}Shorten &amp; copy
          </button>
          <button className="btn-secondary" disabled={!tabId || busy !== null} onClick={handleBiolink}>
            {busy === "biolink" && <span className="spinner" />}Turn into bio-link page
          </button>
          <button className="btn-secondary" disabled={!tabUrl || busy !== null} onClick={() => setView("ab")}>
            Shorten as A/B test…
          </button>
          <hr className="divider" />
          <button className="btn-secondary" disabled={!tabId || busy !== null} onClick={handleSaveContact}>
            {busy === "contact-extract" && <span className="spinner" />}Save to Contacts
          </button>
          {settings.contactAllowOneClick && (
            <button className="btn-link contact-oneclick" disabled={!tabId || busy !== null} onClick={handleOneClickContact}>
              {busy === "contact-oneclick" && <span className="spinner" />}One-click save (skip preview)
            </button>
          )}
          <RecentAbTests items={abTests} loading={abLoading} onChanged={loadAbTests} showToast={showToast} />

          {recent.length > 0 && (
            <div className="recent-links">
              <div className="muted" style={{ marginTop: 8, marginBottom: 4 }}>Recent links</div>
              {recent.slice(0, 6).map((r) => (
                <div key={r.id} className="recent-row" title={r.long_url || ""}>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>
                      <a href={r.short_url || `${settings.webBaseUrl}/${r.alias}`} target="_blank" rel="noreferrer">
                        /{r.alias}
                      </a>
                    </div>
                    {r.pixel_fires && r.pixel_fires.count > 0 && (
                      <div className="muted" style={{ fontSize: 11 }}>
                        {r.pixel_fires.count} pixel fire{r.pixel_fires.count === 1 ? "" : "s"}
                        {r.pixel_fires.providers.length > 0 && ` · ${r.pixel_fires.providers.join(", ")}`}
                      </div>
                    )}
                  </div>
                  {pixels?.has_any && (
                    <label className="toggle-row" style={{ margin: 0 }}>
                      <input
                        type="checkbox"
                        checked={!!r.auto_pixel}
                        onChange={(e) => toggleRecentAutoPixel(r.id, e.target.checked)}
                        title="Auto-pixel for this link"
                      />
                    </label>
                  )}
                </div>
              ))}
            </div>
          )}
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

function buildPayload(c: ContactCandidate, settings: ExtSettings | null): Record<string, unknown> {
  const tags = Array.from(new Set([...(c.tags ?? []), ...(settings?.contactDefaultTags ?? [])]));
  return {
    display_name: c.display_name,
    given_name:   c.given_name,
    family_name:  c.family_name,
    organization: c.organization,
    job_title:    c.job_title,
    website:      c.website,
    notes:        c.notes,
    emails:       c.emails.map((e) => ({ value: e.value, label: e.label, source: e.source })),
    phones:       c.phones.map((p) => ({ value: p.value, label: p.label, country: p.country, source: p.source })),
    socials:      c.socials,
    tags,
    source_url:   c.source_url,
    workspace_id: settings?.contactWorkspaceId ?? settings?.workspaceId ?? undefined,
  };
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
  settings, view, onSignOut, busy,
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

function AbBuilder({
  tabUrl, tabTitle, workspaceId, onCancel, onCreated, onError,
}: {
  tabUrl: string;
  tabTitle: string;
  workspaceId: number | null;
  onCancel: () => void;
  onCreated: (shortUrl: string) => void;
  onError: (msg: string) => void;
}) {
  const [title, setTitle] = useState(tabTitle || "");
  const [variants, setVariants] = useState<Array<{ label: string; url: string; weight: number }>>([
    { label: "A", url: tabUrl, weight: 50 },
    { label: "B", url: "", weight: 50 },
  ]);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  const sum = variants.reduce((acc, v) => acc + (Number.isFinite(v.weight) ? v.weight : 0), 0);

  const setRow = (i: number, patch: Partial<{ label: string; url: string; weight: number }>) => {
    setVariants((prev) => prev.map((v, idx) => (idx === i ? { ...v, ...patch } : v)));
  };

  const addRow = () => {
    if (variants.length >= 4) return;
    setVariants((prev) => {
      const labels = ["A", "B", "C", "D"];
      const next = [...prev, { label: labels[prev.length] ?? "", url: "", weight: 0 }];
      const even = Math.floor(100 / next.length);
      return next.map((v, i) => ({ ...v, weight: i === next.length - 1 ? 100 - even * (next.length - 1) : even }));
    });
  };

  const removeRow = (i: number) => {
    if (variants.length <= 2) return;
    setVariants((prev) => {
      const next = prev.filter((_, idx) => idx !== i);
      const even = Math.floor(100 / next.length);
      return next.map((v, j) => ({ ...v, weight: j === next.length - 1 ? 100 - even * (next.length - 1) : even }));
    });
  };

  const splitEvenly = () => {
    setVariants((prev) => {
      const even = Math.floor(100 / prev.length);
      return prev.map((v, i) => ({ ...v, weight: i === prev.length - 1 ? 100 - even * (prev.length - 1) : even }));
    });
  };

  const submit = async () => {
    setErr(null);
    if (variants.some((v) => !v.url.trim())) { setErr("Every variant needs a destination URL."); return; }
    if (sum !== 100) { setErr(`Weights must sum to 100 (currently ${sum}).`); return; }
    setBusy(true);
    try {
      const resp = await api.createAbTest(
        title || undefined,
        variants.map((v) => ({ label: v.label || undefined, url: v.url.trim(), weight: v.weight })),
        workspaceId,
      );
      const shortUrl = resp.link.short_url || `/${resp.link.alias}`;
      try { await navigator.clipboard.writeText(shortUrl); } catch { /* clipboard optional */ }
      onCreated(shortUrl);
    } catch (e: any) {
      const msg = e instanceof ApiError ? e.message : (e?.message || "Could not create A/B test");
      setErr(msg);
      onError(msg);
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="body">
      <div className="field">
        <label>A/B test title (optional)</label>
        <input value={title} onChange={(e) => setTitle(e.target.value)} placeholder="e.g. Spring sale CTA" />
      </div>
      <div className="ab-builder">
        {variants.map((v, i) => (
          <div key={i} className="ab-row">
            <input
              className="ab-label"
              value={v.label}
              maxLength={20}
              onChange={(e) => setRow(i, { label: e.target.value })}
              placeholder="Label"
            />
            <input
              className="ab-url"
              value={v.url}
              onChange={(e) => setRow(i, { url: e.target.value })}
              placeholder="https://…"
            />
            <input
              className="ab-weight"
              type="number"
              min={1}
              max={100}
              value={v.weight}
              onChange={(e) => setRow(i, { weight: Math.max(0, Math.min(100, Number(e.target.value) || 0)) })}
            />
            <span className="muted">%</span>
            {variants.length > 2 && (
              <button className="btn-link" onClick={() => removeRow(i)} title="Remove">×</button>
            )}
          </div>
        ))}
        <div className="row" style={{ justifyContent: "space-between" }}>
          <span className={`muted ${sum === 100 ? "" : "weights-bad"}`}>Total: {sum}%</span>
          <span className="row" style={{ gap: 4 }}>
            <button className="btn-link" onClick={splitEvenly}>Even split</button>
            {variants.length < 4 && <button className="btn-link" onClick={addRow}>+ Add variant</button>}
          </span>
        </div>
      </div>
      {err && <div className="error-text">{err}</div>}
      <div className="row" style={{ gap: 8 }}>
        <button className="btn-secondary" onClick={onCancel} disabled={busy}>Cancel</button>
        <button className="btn-primary" onClick={submit} disabled={busy || sum !== 100}>
          {busy && <span className="spinner" />}Create A/B link
        </button>
      </div>
    </div>
  );
}

function RecentAbTests({
  items, loading, onChanged, showToast,
}: {
  items: AbTestItem[];
  loading: boolean;
  onChanged: () => void;
  showToast: (t: Toast) => void;
}) {
  const [busyId, setBusyId] = useState<number | null>(null);

  const declare = async (linkId: number, variantId: number) => {
    setBusyId(linkId);
    try {
      await api.declareAbWinner(linkId, variantId);
      showToast({ kind: "success", text: "Winner declared — link now points to the chosen URL." });
      onChanged();
    } catch (e: any) {
      showToast({ kind: "error", text: e?.message || "Could not declare winner" });
    } finally {
      setBusyId(null);
    }
  };

  if (loading && items.length === 0) {
    return <div className="ab-list muted">Loading recent A/B tests…</div>;
  }
  if (items.length === 0) return null;

  return (
    <div className="ab-list">
      <div className="ab-list-title">Recent A/B tests</div>
      {items.map((it) => {
        const totalClicks = it.variants.variants.reduce((acc, v) => acc + v.clicks, 0);
        const leader = it.variants.variants.find((v) => v.id === it.variants.leader_variant_id);
        const isDone = it.variants.winner_variant_id !== null;
        return (
          <div key={it.link.id} className="ab-card">
            <div className="row" style={{ justifyContent: "space-between" }}>
              <div className="ab-card-title">
                <span className="ab-badge">A/B</span>{" "}
                {it.link.short_url || `/${it.link.alias}`}
              </div>
              <span className="muted">{totalClicks} click{totalClicks === 1 ? "" : "s"}</span>
            </div>
            <Sparkline variants={it.variants.variants} totalClicks={totalClicks} />
            <div className="muted" style={{ marginTop: 4 }}>
              {isDone
                ? <>Winner declared.</>
                : leader && totalClicks > 0
                  ? <>Leader: <strong>{leader.label || `Variant ${leader.id}`}</strong> ({leader.clicks}/{totalClicks})</>
                  : <>Waiting for clicks…</>}
            </div>
            {!isDone && totalClicks > 0 && (
              <div className="row" style={{ gap: 4, marginTop: 4, flexWrap: "wrap" }}>
                {it.variants.variants.map((v) => (
                  <button
                    key={v.id}
                    className="btn-link"
                    disabled={busyId === it.link.id}
                    onClick={() => declare(it.link.id, v.id)}
                    title={`Declare ${v.label || `Variant ${v.id}`} as winner`}
                  >
                    Declare {v.label || `#${v.id}`}
                  </button>
                ))}
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
}

function Sparkline({ variants, totalClicks }: { variants: AbTestItem["variants"]["variants"]; totalClicks: number }) {
  if (variants.length === 0) return null;
  const colors = ["#5b8def", "#22c55e", "#f59e0b", "#ef4444"];
  return (
    <div className="ab-bar">
      {variants.map((v, i) => {
        const pct = totalClicks > 0 ? (v.clicks / totalClicks) * 100 : v.weight;
        return (
          <div
            key={v.id}
            className="ab-bar-seg"
            style={{ width: `${pct}%`, background: colors[i % colors.length] }}
            title={`${v.label || `#${v.id}`}: ${v.clicks} clicks (${pct.toFixed(0)}%)`}
          />
        );
      })}
    </div>
  );
}

function SettingsView({
  settings, pixels, onSaved, onPixelsSaved, showToast,
}: {
  settings: ExtSettings;
  pixels: WorkspacePixels | null;
  onSaved: () => void;
  onPixelsSaved: (p: WorkspacePixels) => void;
  showToast: (t: Toast) => void;
}) {
  const [apiBase, setApi] = useState(settings.apiBaseUrl);
  const [web, setWeb] = useState(settings.webBaseUrl);
  const [tags, setTags] = useState((settings.contactDefaultTags || []).join(", "));
  const [allowOneClick, setAllowOneClick] = useState(!!settings.contactAllowOneClick);
  const [contactWs, setContactWs] = useState<number | null>(settings.contactWorkspaceId ?? null);
  const [saved, setSaved] = useState(false);
  // Tracking-pixel form state, seeded from server-side workspace pixels.
  const [meta, setMeta] = useState(pixels?.meta_id || "");
  const [tiktok, setTiktok] = useState(pixels?.tiktok_id || "");
  const [google, setGoogle] = useState(pixels?.google_id || "");
  const [googleLabel, setGoogleLabel] = useState(pixels?.google_label || "");
  const [pixErr, setPixErr] = useState<string | null>(null);
  const [pixBusy, setPixBusy] = useState(false);

  useEffect(() => {
    setMeta(pixels?.meta_id || "");
    setTiktok(pixels?.tiktok_id || "");
    setGoogle(pixels?.google_id || "");
    setGoogleLabel(pixels?.google_label || "");
  }, [pixels?.meta_id, pixels?.tiktok_id, pixels?.google_id, pixels?.google_label]);

  const save = async () => {
    const tagList = tags.split(",").map((t) => t.trim()).filter(Boolean).slice(0, 20);
    await setSettings({
      apiBaseUrl: apiBase.replace(/\/$/, ""),
      webBaseUrl: web.replace(/\/$/, ""),
      contactDefaultTags: tagList,
      contactAllowOneClick: allowOneClick,
      contactWorkspaceId: contactWs,
    });
    setSaved(true);
    setTimeout(() => setSaved(false), 1500);
    onSaved();
  };
  const reset = async () => {
    await setSettings({ apiBaseUrl: "https://1inme.com/api/v1", webBaseUrl: "https://1inme.com" });
    onSaved();
  };

  const validatePixels = (): string | null => {
    if (meta && !/^[0-9]{15,16}$/.test(meta)) return "Meta Pixel ID must be 15–16 digits";
    if (tiktok && !/^[A-Z0-9]{10,40}$/i.test(tiktok)) return "TikTok Pixel ID looks invalid";
    if (google && !/^AW-[0-9]{6,15}$/i.test(google)) return "Google Ads ID must look like AW-1234567890";
    if (googleLabel && !/^[A-Za-z0-9_\-]{1,60}$/.test(googleLabel)) return "Google conversion label must be alphanumeric";
    return null;
  };

  const savePixels = async () => {
    const err = validatePixels();
    if (err) { setPixErr(err); return; }
    setPixErr(null);
    setPixBusy(true);
    try {
      const r = await api.saveWorkspacePixels({
        meta_id: meta || null,
        tiktok_id: tiktok || null,
        google_id: google || null,
        google_label: googleLabel || null,
      }, settings.workspaceId);
      onPixelsSaved(r.pixels);
      showToast({ kind: "success", text: "Tracking pixels saved" });
    } catch (e: any) {
      setPixErr(e?.message || "Save failed");
    } finally {
      setPixBusy(false);
    }
  };

  const openTestLink = async () => {
    // Open a sample short link in a new tab so the creator can verify
    // pixels fire using Meta Pixel Helper / TikTok Pixel Helper.
    try {
      const r = await api.recentLinks(1);
      const first = r.items?.[0];
      if (first) {
        await browser.tabs.create({ url: first.short_url || `${settings.webBaseUrl}/${first.alias}` });
      } else {
        showToast({ kind: "info", text: "Create a link first, then re-open this panel to test." });
      }
    } catch (e: any) {
      showToast({ kind: "error", text: e?.message || "Could not open test link" });
    }
  };

  return (
    <div className="body">
      {settings.token && (
        <>
          <h3 className="section-h">Tracking pixels</h3>
          <div className="muted" style={{ fontSize: 12 }}>
            Pixels fire on every short link from this workspace (auto-pixel: on). Visitors get a
            sub-200&nbsp;ms interstitial that loads your pixel scripts, then redirects.
          </div>
          <div className="settings-row">
            <label>Meta (Facebook) Pixel ID</label>
            <input value={meta} onChange={(e) => setMeta(e.target.value.trim())} placeholder="123456789012345" />
            <span className="muted">15–16 digits. <a href="https://www.facebook.com/business/help/952192354843755" target="_blank" rel="noreferrer">Find your ID</a></span>
          </div>
          <div className="settings-row">
            <label>TikTok Pixel ID</label>
            <input value={tiktok} onChange={(e) => setTiktok(e.target.value.trim())} placeholder="C7XXXXXXXXXXXXXXXX" />
            <span className="muted"><a href="https://ads.tiktok.com/help/article/get-started-pixel" target="_blank" rel="noreferrer">Find your ID</a></span>
          </div>
          <div className="settings-row">
            <label>Google Ads Conversion ID</label>
            <input value={google} onChange={(e) => setGoogle(e.target.value.trim())} placeholder="AW-1234567890" />
            <span className="muted">Starts with AW-.</span>
          </div>
          <div className="settings-row">
            <label>Google Ads Conversion Label</label>
            <input value={googleLabel} onChange={(e) => setGoogleLabel(e.target.value.trim())} placeholder="abcDEF123_-" />
            <span className="muted">Optional. Pairs with the Conversion ID.</span>
          </div>
          {pixErr && <div className="error-text">{pixErr}</div>}
          <button className="btn-primary" disabled={pixBusy} onClick={savePixels}>
            {pixBusy && <span className="spinner" />}Save tracking pixels
          </button>
          <button className="btn-secondary" onClick={openTestLink}>Test pixels (open a link)</button>
          <div className="muted" style={{ fontSize: 12 }}>
            Verify with{" "}
            <a href="https://chrome.google.com/webstore/detail/meta-pixel-helper/fdgfkebogiimcoedlicjlajpkdmockpc" target="_blank" rel="noreferrer">Meta Pixel Helper</a>
            {" · "}
            <a href="https://chrome.google.com/webstore/detail/tiktok-pixel-helper/aelgobmabdmlfmiblddjfnjodalhidnn" target="_blank" rel="noreferrer">TikTok Pixel Helper</a>
            {" · "}
            <a href="https://chrome.google.com/webstore/detail/google-tag-assistant-lega/kejbdjndbnbjgmefkgdddjlbokphdefk" target="_blank" rel="noreferrer">Tag Assistant</a>.
          </div>
          <hr className="divider" />
        </>
      )}
      <h3 className="section-h">General</h3>
      <div className="settings-row">
        <label>API base URL</label>
        <input value={apiBase} onChange={(e) => setApi(e.target.value)} />
        <span className="muted">Default: https://1inme.com/api/v1</span>
      </div>
      <div className="settings-row">
        <label>Web base URL</label>
        <input value={web} onChange={(e) => setWeb(e.target.value)} />
        <span className="muted">Used to open the editor and dashboard.</span>
      </div>

      <h3 className="section-h">Contacts</h3>
      <div className="settings-row">
        <label>Default tags</label>
        <input value={tags} onChange={(e) => setTags(e.target.value)} placeholder="from-extension, lead" />
        <span className="muted">Comma-separated. Applied to every contact saved from the extension.</span>
      </div>
      <label className="checkbox-row">
        <input type="checkbox" checked={allowOneClick} onChange={(e) => setAllowOneClick(e.target.checked)} />
        <span>Allow one-click save (skip the preview when validation passes)</span>
      </label>
      <div className="settings-row">
        <label>Workspace for saved contacts</label>
        <select
          className="workspace-select"
          value={contactWs ?? ""}
          onChange={(e) => setContactWs(e.target.value === "" ? null : Number(e.target.value))}
        >
          <option value="">(use active workspace)</option>
          {settings.workspaces.map((w) => (
            <option key={w.id} value={w.id}>{w.name}</option>
          ))}
        </select>
      </div>

      <button className="btn-primary" onClick={save}>{saved ? "Saved ✓" : "Save"}</button>
      <button className="btn-secondary" onClick={reset}>Reset URLs to defaults</button>
    </div>
  );
}

// ─── Contact preview / editable card ──────────────────────────────

const SOURCE_LABEL: Record<ExtractionSource, string> = {
  vcard: "from vCard",
  hcard: "from hCard",
  jsonld: "from JSON-LD",
  scraped: "scraped",
  manual: "manual",
};

function SourceBadge({ source }: { source?: ExtractionSource }) {
  if (!source) return null;
  return <span className={`badge badge-${source}`}>{SOURCE_LABEL[source]}</span>;
}

function ContactPreview({
  candidate, settings, onCancel, onSaved, showToast,
}: {
  candidate: ContactCandidate;
  settings: ExtSettings;
  onCancel: () => void;
  onSaved: (name: string, id: number) => void;
  showToast: (t: Toast) => void;
}) {
  const [c, setC] = useState<ContactCandidate>(candidate);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [duplicateOf, setDuplicateOf] = useState<number | null>(null);
  const [duplicateInfo, setDuplicateInfo] = useState<any>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [tagInput, setTagInput] = useState("");

  // Run an initial validate so the user sees server-side dedupe + warnings up front.
  useEffect(() => {
    (async () => {
      try {
        const res = await api.validateContact(buildPayload(c, settings));
        setErrors(res.errors || {});
        setDuplicateOf(res.duplicate_of ?? null);
        if (res.duplicate_of) {
          try {
            const info = await api.getContact(res.duplicate_of);
            setDuplicateInfo(info.contact);
          } catch { /* ignore */ }
        }
      } catch { /* validation is best-effort */ }
    })();
    // Only on mount.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const setField = <K extends keyof ContactCandidate>(k: K, v: ContactCandidate[K]) =>
    setC((prev) => ({ ...prev, [k]: v }));

  const updateEmail = (i: number, patch: Partial<ContactCandidate["emails"][number]>) =>
    setC((prev) => ({ ...prev, emails: prev.emails.map((e, idx) => idx === i ? { ...e, ...patch } : e) }));
  const removeEmail = (i: number) => setC((prev) => ({ ...prev, emails: prev.emails.filter((_, idx) => idx !== i) }));
  const addEmail = () => setC((prev) => ({ ...prev, emails: [...prev.emails, { value: "", source: "manual" }] }));

  const updatePhone = (i: number, patch: Partial<ContactCandidate["phones"][number]>) =>
    setC((prev) => ({ ...prev, phones: prev.phones.map((p, idx) => idx === i ? { ...p, ...patch } : p) }));
  const removePhone = (i: number) => setC((prev) => ({ ...prev, phones: prev.phones.filter((_, idx) => idx !== i) }));
  const addPhone = () => setC((prev) => ({ ...prev, phones: [...prev.phones, { value: "", source: "manual" }] }));

  const addTag = () => {
    const t = tagInput.trim();
    if (!t) return;
    setC((prev) => ({ ...prev, tags: Array.from(new Set([...(prev.tags ?? []), t])) }));
    setTagInput("");
  };
  const removeTag = (t: string) => setC((prev) => ({ ...prev, tags: (prev.tags ?? []).filter((x) => x !== t) }));

  const fieldErr = (path: string): string | null => (errors[path]?.[0] ?? null);

  const handleSave = async () => {
    setBusy("save");
    try {
      const result = await api.createContact(buildPayload(c, settings));
      onSaved(result.contact.display_name, result.contact.id);
    } catch (e: any) {
      if (e instanceof ApiError && e.code === "contact_invalid" && e.payload && (e.payload as any).details?.errors) {
        setErrors((e.payload as any).details.errors);
        showToast({ kind: "error", text: "Please fix the highlighted fields." });
      } else {
        showToast({ kind: "error", text: e?.message || "Save failed" });
      }
    } finally {
      setBusy(null);
    }
  };

  const handleMerge = async () => {
    if (!duplicateOf) return;
    setBusy("merge");
    try {
      const result = await api.mergeContact(duplicateOf, buildPayload(c, settings));
      onSaved(result.contact.display_name, result.contact.id);
    } catch (e: any) {
      showToast({ kind: "error", text: e?.message || "Merge failed" });
    } finally {
      setBusy(null);
    }
  };

  return (
    <div className="body contact-preview">
      <div className="preview-header">
        <strong>Save contact</strong>
        <button className="btn-link" onClick={onCancel}>← Back</button>
      </div>

      {duplicateOf && (
        <div className="dedupe-panel">
          <div className="dedupe-title">
            Looks like {duplicateInfo?.display_name || "this person"} is already in your contacts.
          </div>
          {duplicateInfo && (
            <div className="muted dedupe-row">
              {duplicateInfo.emails?.[0]?.value || duplicateInfo.phones?.[0]?.value || ""}
              {duplicateInfo.organization ? ` · ${duplicateInfo.organization}` : ""}
            </div>
          )}
          <div className="dedupe-actions">
            <button className="btn-secondary" disabled={busy !== null} onClick={handleMerge}>
              {busy === "merge" && <span className="spinner" />}Merge into existing
            </button>
            <button className="btn-link" onClick={() => setDuplicateOf(null)}>Save as new anyway</button>
          </div>
        </div>
      )}

      <div className="field">
        <div className="field-label-row">
          <label>Display name</label>
          <SourceBadge source={c.provenance.display_name} />
        </div>
        <input value={c.display_name ?? ""} onChange={(e) => setField("display_name", e.target.value)} />
        {fieldErr("display_name") && <div className="error-text">{fieldErr("display_name")}</div>}
      </div>

      <div className="row-2">
        <div className="field">
          <label>Given name</label>
          <input value={c.given_name ?? ""} onChange={(e) => setField("given_name", e.target.value)} />
        </div>
        <div className="field">
          <label>Family name</label>
          <input value={c.family_name ?? ""} onChange={(e) => setField("family_name", e.target.value)} />
        </div>
      </div>

      <div className="row-2">
        <div className="field">
          <label>Company</label>
          <input value={c.organization ?? ""} onChange={(e) => setField("organization", e.target.value)} />
        </div>
        <div className="field">
          <label>Role</label>
          <input value={c.job_title ?? ""} onChange={(e) => setField("job_title", e.target.value)} />
        </div>
      </div>

      <div className="field">
        <div className="field-label-row">
          <label>Website</label>
          <SourceBadge source={c.provenance.website} />
        </div>
        <input value={c.website ?? ""} onChange={(e) => setField("website", e.target.value)} />
        {fieldErr("website") && <div className="error-text">{fieldErr("website")}</div>}
      </div>

      <div className="field">
        <div className="field-label-row">
          <label>Emails</label>
          <button className="btn-link" onClick={addEmail}>+ add</button>
        </div>
        {c.emails.length === 0 && <div className="muted">No emails detected.</div>}
        {c.emails.map((e, i) => (
          <div className="multi-row" key={i}>
            <input value={e.value} placeholder="name@example.com" onChange={(ev) => updateEmail(i, { value: ev.target.value })} />
            <SourceBadge source={e.source} />
            <button className="btn-link" onClick={() => removeEmail(i)} title="Remove">×</button>
            {fieldErr(`emails.${i}.value`) && <div className="error-text full">{fieldErr(`emails.${i}.value`)}</div>}
          </div>
        ))}
      </div>

      <div className="field">
        <div className="field-label-row">
          <label>Phones</label>
          <button className="btn-link" onClick={addPhone}>+ add</button>
        </div>
        {c.phones.length === 0 && <div className="muted">No phones detected.</div>}
        {c.phones.map((p, i) => (
          <div className="multi-row" key={i}>
            <input value={p.value} placeholder="+1 555 555 5555" onChange={(ev) => updatePhone(i, { value: ev.target.value })} />
            <input className="country" maxLength={2} placeholder="US" value={p.country ?? ""} onChange={(ev) => updatePhone(i, { country: ev.target.value.toUpperCase() })} />
            <SourceBadge source={p.source} />
            <button className="btn-link" onClick={() => removePhone(i)} title="Remove">×</button>
            {fieldErr(`phones.${i}.value`) && <div className="error-text full">{fieldErr(`phones.${i}.value`)}</div>}
          </div>
        ))}
      </div>

      {Object.keys(c.socials).length > 0 && (
        <div className="field">
          <div className="field-label-row">
            <label>Socials</label>
          </div>
          {Object.entries(c.socials).map(([platform, value]) => (
            <div className="multi-row" key={platform}>
              <span className="social-platform">{platform}</span>
              <input value={value} onChange={(ev) => setC((prev) => ({ ...prev, socials: { ...prev.socials, [platform]: ev.target.value } }))} />
            </div>
          ))}
        </div>
      )}

      <div className="field">
        <label>Notes</label>
        <textarea rows={2} value={c.notes ?? ""} onChange={(e) => setField("notes", e.target.value)} />
      </div>

      <div className="field">
        <label>Tags</label>
        <div className="tag-list">
          {(c.tags ?? []).map((t) => (
            <span key={t} className="tag">
              {t}
              <button className="btn-link" onClick={() => removeTag(t)}>×</button>
            </span>
          ))}
          <input
            value={tagInput}
            placeholder="add tag…"
            onChange={(e) => setTagInput(e.target.value)}
            onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); addTag(); } }}
          />
        </div>
      </div>

      <div className="field">
        <label>Source page</label>
        <div className="url-card" title={c.source_url}>{c.source_url}</div>
      </div>

      {fieldErr("_form") && <div className="error-text">{fieldErr("_form")}</div>}

      <button className="btn-primary" disabled={busy !== null} onClick={handleSave}>
        {busy === "save" && <span className="spinner" />}{duplicateOf ? "Save as new contact" : "Save contact"}
      </button>
      <button className="btn-secondary" disabled={busy !== null} onClick={onCancel}>Cancel</button>
    </div>
  );
}
