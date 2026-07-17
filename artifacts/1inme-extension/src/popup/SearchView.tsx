import React, { useCallback, useEffect, useRef, useState } from "react";
import { api, ApiError } from "../lib/api";
import { browser } from "../lib/browser";

interface SearchGroup {
  label: string;
  items: SearchItem[];
}

interface SearchItem {
  id: number | string;
  title: string;
  subtitle?: string;
  type?: string;
  action_url?: string;
  copy_url?: string;
}

interface Props {
  webBaseUrl: string;
  onCancel: () => void;
  showToast: (t: { kind: "success" | "error" | "info"; text: string }) => void;
}

export function SearchView({ webBaseUrl, onCancel, showToast }: Props) {
  const [query, setQuery] = useState("");
  const [groups, setGroups] = useState<SearchGroup[]>([]);
  const [loading, setLoading] = useState(false);
  const [searched, setSearched] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);
  const abortRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    inputRef.current?.focus();
  }, []);

  const runSearch = useCallback(async (q: string) => {
    if (!q.trim()) {
      setGroups([]);
      setSearched(false);
      setLoading(false);
      return;
    }
    setLoading(true);
    setSearched(true);
    try {
      const resp = await api.dialerSearch(q.trim());
      const rawGroups: SearchGroup[] = [];
      for (const g of resp.groups ?? []) {
        if (!g.items?.length) continue;
        rawGroups.push({
          label: g.label,
          items: g.items.map((it: any) => ({
            id: it.id ?? it.alias ?? String(Math.random()),
            title: it.display_name ?? it.name ?? it.title ?? it.alias ?? "—",
            subtitle: it.type ?? it.organization ?? it.handle ?? it.alias ?? undefined,
            action_url: it.action_url ?? undefined,
            copy_url: it.copy_url ?? it.short_url ?? undefined,
          })),
        });
      }
      setGroups(rawGroups);
    } catch (e: any) {
      if (e instanceof ApiError && e.status === 404) {
        setGroups([]);
      } else {
        showToast({ kind: "error", text: e instanceof ApiError ? e.message : (e?.message || "Search failed") });
      }
    } finally {
      setLoading(false);
    }
  }, [showToast]);

  const handleInput = (val: string) => {
    setQuery(val);
    if (abortRef.current) clearTimeout(abortRef.current);
    abortRef.current = setTimeout(() => runSearch(val), 350);
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === "Enter") {
      if (abortRef.current) clearTimeout(abortRef.current);
      runSearch(query);
    }
  };

  const openItem = (item: SearchItem) => {
    const url = item.action_url || `${webBaseUrl}/dashboard`;
    browser.tabs.create({ url });
  };

  const copyItem = async (item: SearchItem) => {
    const url = item.copy_url;
    if (!url) { showToast({ kind: "info", text: "No URL to copy for this item." }); return; }
    try {
      await navigator.clipboard.writeText(url);
      showToast({ kind: "success", text: "Copied!" });
    } catch {
      showToast({ kind: "info", text: url });
    }
  };

  const totalResults = groups.reduce((n, g) => n + g.items.length, 0);

  return (
    <div className="body">
      <div className="preview-header">
        <strong>🔍 Universal search</strong>
        <button className="btn-link" onClick={onCancel}>← Back</button>
      </div>
      <div className="field" style={{ marginBottom: 8 }}>
        <input
          ref={inputRef}
          value={query}
          onChange={(e) => handleInput(e.target.value)}
          onKeyDown={handleKeyDown}
          placeholder="Search contacts, links, people, workspaces…"
          style={{ width: "100%", boxSizing: "border-box" }}
        />
      </div>
      {loading && <div className="muted">Searching…</div>}
      {!loading && searched && totalResults === 0 && (
        <div className="muted">No results for "{query}".</div>
      )}
      {!loading && groups.map((g) => (
        <div key={g.label} style={{ marginBottom: 10 }}>
          <div className="section-title" style={{ marginBottom: 4, fontSize: 10, textTransform: "uppercase", letterSpacing: "0.05em", opacity: 0.6 }}>
            {g.label}
          </div>
          <div className="recent-list">
            {g.items.map((item) => (
              <div key={item.id} className="recent-item" style={{ display: "flex", alignItems: "center", gap: 6 }}>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div className="alias" style={{ fontWeight: 500, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
                    {item.title}
                  </div>
                  {item.subtitle && (
                    <div className="muted" style={{ fontSize: 10, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
                      {item.subtitle}
                    </div>
                  )}
                </div>
                <div className="actions" style={{ display: "flex", gap: 4, flexShrink: 0 }}>
                  <button className="btn-link" style={{ fontSize: 11 }} onClick={() => openItem(item)}>Open</button>
                  {item.copy_url && (
                    <button className="btn-link" style={{ fontSize: 11 }} onClick={() => copyItem(item)}>Copy</button>
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>
      ))}
      {!searched && !loading && (
        <div className="muted" style={{ fontSize: 12 }}>
          Search across contacts, people, links, followed accounts, and workspaces.
          Uses the same universal finder as the Sayzio dialer.
        </div>
      )}
    </div>
  );
}
