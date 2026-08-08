/**
 * Startup diagnostic log — a plain-text trace of everything that happens
 * while the chrome window boots, so a white-window report from a user's
 * machine can be diagnosed from a single file.
 *
 * File: <userData>/startup.log — truncated at each launch (keeps only the
 * current run plus the previous one in startup-prev.log).
 */
import { app } from 'electron';
import fs from 'node:fs';
import path from 'node:path';

let logPath: string | null = null;
let ready = false;

function ts(): string {
  return new Date().toISOString();
}

/** Initialize (rotate) the log. Safe to call before app ready. */
export function initStartupLog(): void {
  try {
    const dir = app.getPath('userData');
    fs.mkdirSync(dir, { recursive: true });
    logPath = path.join(dir, 'startup.log');
    const prev = path.join(dir, 'startup-prev.log');
    try { if (fs.existsSync(logPath)) fs.renameSync(logPath, prev); } catch { /* rotation best-effort */ }
    fs.writeFileSync(logPath, `[${ts()}] launch: Zio Browser ${app.getVersion()} on ${process.platform}/${process.arch}, electron ${process.versions.electron}\n`);
    ready = true;
  } catch { /* logging must never break startup */ }
}

/** Append one line to the startup log. No-op when logging unavailable. */
export function slog(line: string): void {
  if (!ready || !logPath) return;
  try { fs.appendFileSync(logPath, `[${ts()}] ${line}\n`); } catch { /* best-effort */ }
}

/** Absolute path of the current startup log (for "reveal" menu action). */
export function startupLogPath(): string | null {
  return logPath;
}
