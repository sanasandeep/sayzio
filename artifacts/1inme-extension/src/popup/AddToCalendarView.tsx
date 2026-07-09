import React, { useEffect, useRef, useState } from "react";
import { api, ApiError } from "../lib/api";
import type { EventCandidate } from "../content/event-extract";

export interface CalendarItem {
  id: number;
  name: string;
  color?: string | null;
}

interface Props {
  tabUrl: string;
  tabTitle: string;
  workspaceId: number | null;
  onCancel: () => void;
  onCreated: () => void;
  showToast: (t: { kind: "success" | "error" | "info"; text: string }) => void;
}

function toLocalDatetimeValue(iso: string | null): string {
  if (!iso) return "";
  try {
    const d = new Date(iso);
    if (isNaN(d.getTime())) return "";
    const pad = (n: number) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  } catch {
    return "";
  }
}

function localDatetimeToIso(local: string): string | null {
  if (!local) return null;
  try {
    const d = new Date(local);
    if (isNaN(d.getTime())) return null;
    return d.toISOString();
  } catch {
    return null;
  }
}

export function AddToCalendarView({ tabUrl, tabTitle, workspaceId, onCancel, onCreated, showToast }: Props) {
  const [calendars, setCalendars] = useState<CalendarItem[]>([]);
  const [loadingCals, setLoadingCals] = useState(true);
  const [selectedCalId, setSelectedCalId] = useState<number | null>(null);
  const [extracting, setExtracting] = useState(true);

  const [title, setTitle] = useState(tabTitle || "");
  const [description, setDescription] = useState("");
  const [location, setLocation] = useState("");
  const [startDate, setStartDate] = useState("");
  const [endDate, setEndDate] = useState("");
  const [busy, setBusy] = useState(false);
  const mountedRef = useRef(true);

  useEffect(() => {
    mountedRef.current = true;

    api.getCalendars()
      .then((r) => {
        if (!mountedRef.current) return;
        const items = r.items ?? [];
        setCalendars(items);
        if (items.length) setSelectedCalId(items[0].id);
      })
      .catch(() => {
        if (mountedRef.current) showToast({ kind: "error", text: "Could not load calendars" });
      })
      .finally(() => { if (mountedRef.current) setLoadingCals(false); });

    chrome.tabs.query({ active: true, currentWindow: true }, async (tabs) => {
      const tabId = tabs[0]?.id;
      if (!tabId) { if (mountedRef.current) setExtracting(false); return; }
      try {
        const results = await chrome.scripting.executeScript({
          target: { tabId },
          files: ["content-event-extract.js"],
        });
        const candidate = results?.[0]?.result as EventCandidate | null;
        if (!mountedRef.current) return;
        if (candidate) {
          if (candidate.title) setTitle(candidate.title);
          if (candidate.description) setDescription(candidate.description);
          if (candidate.location) setLocation(candidate.location);
          if (candidate.startDate) setStartDate(toLocalDatetimeValue(candidate.startDate));
          if (candidate.endDate) setEndDate(toLocalDatetimeValue(candidate.endDate));
        }
      } catch { /* extraction is best-effort */ }
      finally { if (mountedRef.current) setExtracting(false); }
    });

    return () => { mountedRef.current = false; };
  }, []);

  const save = async () => {
    if (!selectedCalId) { showToast({ kind: "error", text: "Select a calendar first" }); return; }
    if (!title.trim()) { showToast({ kind: "error", text: "Event title is required" }); return; }
    setBusy(true);
    try {
      await api.createCalendarEvent(selectedCalId, {
        title: title.trim(),
        description: description || undefined,
        location: location || undefined,
        start_date: localDatetimeToIso(startDate) ?? undefined,
        end_date: localDatetimeToIso(endDate) ?? undefined,
        url: tabUrl,
      });
      onCreated();
    } catch (e: any) {
      showToast({ kind: "error", text: e instanceof ApiError ? e.message : "Could not add event" });
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="body">
      <h3 className="section-h" style={{ marginBottom: 10 }}>Add to calendar</h3>
      {extracting && <div className="muted" style={{ fontSize: 12, marginBottom: 8 }}>Detecting event details…</div>}

      <div className="field">
        <label>Calendar</label>
        {loadingCals ? (
          <div className="muted">Loading calendars…</div>
        ) : calendars.length === 0 ? (
          <div className="muted">No calendars found. Create one in Sayzio first.</div>
        ) : (
          <select value={selectedCalId ?? ""} onChange={(e) => setSelectedCalId(Number(e.target.value))}>
            {calendars.map((c) => (
              <option key={c.id} value={c.id}>{c.name}</option>
            ))}
          </select>
        )}
      </div>

      <div className="field">
        <label>Event title</label>
        <input value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Event name" required />
      </div>

      <div className="field">
        <label>Start date / time</label>
        <input type="datetime-local" value={startDate} onChange={(e) => setStartDate(e.target.value)} />
      </div>

      <div className="field">
        <label>End date / time</label>
        <input type="datetime-local" value={endDate} onChange={(e) => setEndDate(e.target.value)} />
      </div>

      <div className="field">
        <label>Location (optional)</label>
        <input value={location} onChange={(e) => setLocation(e.target.value)} placeholder="Address or venue name" />
      </div>

      <div className="field">
        <label>Description (optional)</label>
        <textarea
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          rows={3}
          placeholder="Event description…"
          style={{ resize: "vertical" }}
        />
      </div>

      <div className="row" style={{ gap: 8 }}>
        <button className="btn-secondary" onClick={onCancel} disabled={busy}>Cancel</button>
        <button className="btn-primary" onClick={save} disabled={busy || loadingCals}>
          {busy && <span className="spinner" />}Add to calendar
        </button>
      </div>
    </div>
  );
}
