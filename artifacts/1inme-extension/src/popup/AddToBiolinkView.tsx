import React, { useEffect, useRef, useState } from "react";
import { api, ApiError } from "../lib/api";

export interface BiolinkSummary {
  id: number;
  alias: string;
  title?: string | null;
  short_url?: string;
}

interface Props {
  tabUrl: string;
  tabTitle: string;
  workspaceId: number | null;
  onCancel: () => void;
  onDone: (msg: string) => void;
  showToast: (t: { kind: "success" | "error" | "info"; text: string }) => void;
}

const BLOCK_TYPES = [
  { type: "link",    label: "Link button",    desc: "A clickable button pointing to this page" },
  { type: "embed",   label: "Embed",          desc: "Embed the page as an iframe widget" },
  { type: "image",   label: "Image block",    desc: "Add the OG image with a link to this page" },
  { type: "text",    label: "Text / note",    desc: "A text card mentioning this page" },
];

export function AddToBiolinkView({ tabUrl, tabTitle, workspaceId, onCancel, onDone, showToast }: Props) {
  const [biolinks, setBiolinks] = useState<BiolinkSummary[]>([]);
  const [loadingBiolinks, setLoadingBiolinks] = useState(true);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [blockType, setBlockType] = useState("link");
  const [label, setLabel] = useState(tabTitle || "");
  const [busy, setBusy] = useState(false);
  const mountedRef = useRef(true);

  useEffect(() => {
    mountedRef.current = true;
    api.getBiolinks(20)
      .then((r) => {
        if (!mountedRef.current) return;
        setBiolinks(r.items ?? []);
        if (r.items?.length) setSelectedId(r.items[0].id);
      })
      .catch(() => {
        if (mountedRef.current) showToast({ kind: "error", text: "Could not load your bio-links" });
      })
      .finally(() => { if (mountedRef.current) setLoadingBiolinks(false); });
    return () => { mountedRef.current = false; };
  }, []);

  const add = async () => {
    if (!selectedId) { showToast({ kind: "error", text: "Select a bio-link page first" }); return; }
    setBusy(true);
    try {
      const settings: Record<string, unknown> = {
        url: tabUrl,
        link: tabUrl,
        label: label || tabTitle || tabUrl,
      };
      if (blockType === "embed") {
        settings.embed_url = tabUrl;
        settings.label    = label || tabTitle || tabUrl;
      }
      if (blockType === "image") {
        settings.destination_url = tabUrl;
        settings.label           = label || tabTitle;
      }
      if (blockType === "text") {
        settings.text = label || `Check out: ${tabTitle || tabUrl}\n${tabUrl}`;
      }
      await api.addBlock(selectedId, blockType, settings);
      onDone("Block added to bio-link!");
    } catch (e: any) {
      showToast({ kind: "error", text: e instanceof ApiError ? e.message : "Could not add block" });
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="body">
      <h3 className="section-h" style={{ marginBottom: 10 }}>Add to existing bio-link</h3>

      {loadingBiolinks ? (
        <div className="muted">Loading your bio-link pages…</div>
      ) : biolinks.length === 0 ? (
        <div className="muted">No bio-link pages yet. Create one on Sayzio first.</div>
      ) : (
        <>
          <div className="field">
            <label>Bio-link page</label>
            <select value={selectedId ?? ""} onChange={(e) => setSelectedId(Number(e.target.value))}>
              {biolinks.map((b) => (
                <option key={b.id} value={b.id}>{b.title || `/${b.alias}`}</option>
              ))}
            </select>
          </div>

          <div className="field">
            <label>Block type</label>
            <div style={{ display: "flex", flexDirection: "column", gap: 5 }}>
              {BLOCK_TYPES.map((bt) => (
                <label key={bt.type} style={{ display: "flex", gap: 8, alignItems: "flex-start", cursor: "pointer" }}>
                  <input type="radio" name="blockType" value={bt.type} checked={blockType === bt.type}
                    onChange={() => setBlockType(bt.type)} style={{ marginTop: 3 }} />
                  <span>
                    <strong>{bt.label}</strong>
                    <span className="muted" style={{ display: "block", fontSize: 11 }}>{bt.desc}</span>
                  </span>
                </label>
              ))}
            </div>
          </div>

          <div className="field">
            <label>{blockType === "text" ? "Text content" : "Label / title"}</label>
            <input
              value={label}
              onChange={(e) => setLabel(e.target.value)}
              placeholder={blockType === "text" ? "Write something…" : tabTitle || tabUrl}
            />
          </div>

          <div className="muted" style={{ fontSize: 11 }}>
            URL: <span style={{ wordBreak: "break-all" }}>{tabUrl}</span>
          </div>
        </>
      )}

      <div className="row" style={{ gap: 8, marginTop: 12 }}>
        <button className="btn-secondary" onClick={onCancel} disabled={busy}>Cancel</button>
        <button className="btn-primary" onClick={add} disabled={busy || loadingBiolinks || !selectedId}>
          {busy && <span className="spinner" />}Add block
        </button>
      </div>
    </div>
  );
}
