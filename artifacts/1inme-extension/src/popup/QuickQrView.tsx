import React, { useEffect, useRef, useState } from "react";
import { api, ApiError } from "../lib/api";

interface QrPreset {
  id: string;
  name: string;
  design: Record<string, unknown>;
}

interface QrCode {
  id: number;
  name: string;
  short_url?: string;
  encoded?: string;
  svg?: string;
}

interface Props {
  tabUrl: string;
  tabTitle: string;
  workspaceId: number | null;
  webBaseUrl: string;
  onCancel: () => void;
  onCreated: (qr: QrCode) => void;
  showToast: (t: { kind: "success" | "error" | "info"; text: string }) => void;
}

export function QuickQrView({ tabUrl, tabTitle, workspaceId, webBaseUrl, onCancel, onCreated, showToast }: Props) {
  const [presets, setPresets] = useState<QrPreset[]>([]);
  const [selectedPreset, setSelectedPreset] = useState<string>("");
  const [name, setName] = useState((tabTitle || "").slice(0, 60) || "Quick QR");
  const [loadingCatalog, setLoadingCatalog] = useState(true);
  const [creating, setCreating] = useState(false);
  const [created, setCreated] = useState<QrCode | null>(null);
  const mountedRef = useRef(true);

  useEffect(() => {
    mountedRef.current = true;
    api.getQrCatalog()
      .then((r) => {
        if (!mountedRef.current) return;
        const p = (r.presets ?? []).slice(0, 12);
        setPresets(p);
        if (p.length) setSelectedPreset(p[0].id);
      })
      .catch(() => {
        if (mountedRef.current) setLoadingCatalog(false);
      })
      .finally(() => { if (mountedRef.current) setLoadingCatalog(false); });
    return () => { mountedRef.current = false; };
  }, []);

  const create = async () => {
    setCreating(true);
    try {
      const design = presets.find((p) => p.id === selectedPreset)?.design;
      const resp = await api.createQrCode(
        name || "Quick QR",
        "url",
        { url: tabUrl },
        undefined,
        design,
      );
      if (!mountedRef.current) return;
      setCreated(resp.qr_code);
      onCreated(resp.qr_code);
    } catch (e: any) {
      if (!mountedRef.current) return;
      showToast({ kind: "error", text: e instanceof ApiError ? e.message : "Could not create QR code" });
    } finally {
      if (mountedRef.current) setCreating(false);
    }
  };

  const openStudio = () => {
    const url = `${webBaseUrl}/dashboard/qr-studio`;
    chrome.tabs.create({ url });
  };

  if (created) {
    return (
      <div className="body" style={{ textAlign: "center" }}>
        <div style={{ fontSize: 36, marginBottom: 8 }}>✅</div>
        <div style={{ fontWeight: 600, marginBottom: 4 }}>QR code created!</div>
        <div className="muted" style={{ marginBottom: 12, fontSize: 12, wordBreak: "break-all" }}>
          {created.name}
        </div>
        <div style={{ display: "flex", gap: 8, justifyContent: "center" }}>
          <button className="btn-secondary" onClick={openStudio}>Open QR Studio</button>
          <button className="btn-primary" onClick={onCancel}>Done</button>
        </div>
      </div>
    );
  }

  return (
    <div className="body">
      <h3 className="section-h" style={{ marginBottom: 10 }}>Design a QR code for this page</h3>
      <div className="muted" style={{ fontSize: 11, marginBottom: 10, wordBreak: "break-all" }}>
        {tabUrl}
      </div>

      <div className="field">
        <label>QR code name</label>
        <input
          value={name}
          onChange={(e) => setName(e.target.value)}
          maxLength={80}
          placeholder="e.g. Product launch QR"
        />
      </div>

      <div className="field">
        <label>Template</label>
        {loadingCatalog ? (
          <div className="muted">Loading templates…</div>
        ) : presets.length === 0 ? (
          <div className="muted">No templates found. Will use default style.</div>
        ) : (
          <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 6 }}>
            {presets.map((p) => (
              <label
                key={p.id}
                style={{
                  display: "flex",
                  flexDirection: "column",
                  alignItems: "center",
                  gap: 3,
                  padding: "6px 4px",
                  borderRadius: 8,
                  border: `2px solid ${selectedPreset === p.id ? "#3b82f6" : "rgba(255,255,255,.1)"}`,
                  cursor: "pointer",
                  background: selectedPreset === p.id ? "rgba(59,130,246,.08)" : "transparent",
                  fontSize: 10,
                  textAlign: "center",
                  transition: "border-color .15s",
                }}
              >
                <input type="radio" name="preset" value={p.id} checked={selectedPreset === p.id}
                  onChange={() => setSelectedPreset(p.id)} style={{ display: "none" }} />
                <span style={{ fontSize: 22 }}>◼</span>
                <span style={{ lineHeight: 1.2 }}>{p.name}</span>
              </label>
            ))}
          </div>
        )}
      </div>

      <div style={{ display: "flex", gap: 8, marginTop: 12 }}>
        <button className="btn-secondary" onClick={onCancel} disabled={creating}>Cancel</button>
        <button className="btn-primary" onClick={create} disabled={creating}>
          {creating && <span className="spinner" />}Create QR
        </button>
        <button className="btn-link" onClick={openStudio} title="Open the full QR Studio">
          Full studio ↗
        </button>
      </div>
    </div>
  );
}
