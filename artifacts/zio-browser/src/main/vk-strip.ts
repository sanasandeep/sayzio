/**
 * VkStripWindow — the floating word-suggestion strip for the virtual keyboard.
 *
 * Native WebContentsViews cover the chrome renderer DOM, so a strip that can
 * float anywhere over the page must be a real window: a small frameless,
 * transparent, non-focusable child BrowserWindow. Dragging is native (the
 * grip is an app-region drag handle) and never steals typing focus from the
 * page. Selections come back via console messages with a magic prefix; moves
 * are reported so the caller can persist the position.
 */
import { BrowserWindow } from 'electron';
import {
  buildVkStripHtml,
  parseVkStripMessage,
  clampStripPos,
  VK_STRIP_WIDTH,
  VK_STRIP_HEIGHT,
  type VkStripUpdatePayload,
  type VkStripPos,
} from '../shared/virtual-keyboard';

export class VkStripWindow {
  private win: BrowserWindow | null = null;
  private loaded: Promise<void> | null = null;
  private pendingUpdate: VkStripUpdatePayload | null = null;

  constructor(
    private readonly parent: BrowserWindow,
    private readonly onSelect: (index: number) => void,
    private readonly onMoved: (pos: VkStripPos) => void,
  ) {
    // The strip dies with its parent window.
    parent.once('closed', () => this.destroy());
  }

  /** Show the strip at a parent-relative position (persisted or default). */
  show(pos: VkStripPos | null): void {
    if (!this.win || this.win.isDestroyed()) this.create();
    const win = this.win;
    if (!win) return;
    this.moveTo(pos ?? this.defaultPos());
    win.showInactive();
  }

  hide(): void {
    if (this.win && !this.win.isDestroyed() && this.win.isVisible()) this.win.hide();
  }

  /** Re-render the strip contents (suggestions, mode, theme). */
  update(payload: VkStripUpdatePayload): void {
    const win = this.win;
    if (!win || win.isDestroyed()) {
      this.pendingUpdate = payload;
      return;
    }
    void this.loaded?.then(() => {
      if (win.isDestroyed()) return;
      win.webContents
        .executeJavaScript(`window.__zioVkStripUpdate(${JSON.stringify(this.toPagePayload(payload))})`)
        .catch(() => { /* window closing */ });
    });
  }

  destroy(): void {
    if (this.win && !this.win.isDestroyed()) this.win.destroy();
    this.win = null;
    this.loaded = null;
  }

  // ── internals ──────────────────────────────────────────────────────────────

  /** The page renders plain labels; keep expansion previews main-side. */
  private toPagePayload(payload: VkStripUpdatePayload): {
    suggestions: Array<{ label: string; title: string; source: string }>;
    selectionMode: string;
    dwellMs: number;
    light: boolean;
    placeholder: string;
  } {
    return {
      suggestions: payload.suggestions.map(s => ({
        label: s.label,
        title: s.title,
        source: s.source,
      })),
      selectionMode: payload.selectionMode,
      dwellMs: payload.dwellMs,
      light: payload.light,
      placeholder: payload.placeholder,
    };
  }

  private create(): void {
    const win = new BrowserWindow({
      parent: this.parent,
      width: VK_STRIP_WIDTH,
      height: VK_STRIP_HEIGHT,
      frame: false,
      transparent: true,
      resizable: false,
      movable: true,
      minimizable: false,
      maximizable: false,
      fullscreenable: false,
      skipTaskbar: true,
      alwaysOnTop: true,
      show: false,
      // Never steal typing focus from the page the user is filling in.
      focusable: false,
      hasShadow: false,
      webPreferences: {
        nodeIntegration: false,
        contextIsolation: true,
        sandbox: true,
      },
    });
    this.win = win;
    this.loaded = win
      .loadURL(`data:text/html;charset=utf-8,${encodeURIComponent(buildVkStripHtml())}`)
      .then(() => {
        if (this.pendingUpdate) {
          const p = this.pendingUpdate;
          this.pendingUpdate = null;
          this.update(p);
        }
      })
      .catch(() => { /* window closing */ });

    win.webContents.on('console-message', (event) => {
      const msg = parseVkStripMessage(event.message ?? '');
      if (msg) this.onSelect(msg.index);
    });

    win.on('moved', () => {
      const rel = this.relativePos();
      if (rel) this.onMoved(rel);
    });
  }

  /** Default: centered horizontally, lower third of the parent content area. */
  private defaultPos(): VkStripPos {
    const b = this.parent.getContentBounds();
    return {
      x: Math.max(0, Math.round((b.width - VK_STRIP_WIDTH) / 2)),
      y: Math.max(0, Math.round(b.height * 0.55)),
    };
  }

  /** Move to a parent-relative position, clamped inside the parent. */
  private moveTo(pos: VkStripPos): void {
    const win = this.win;
    if (!win || win.isDestroyed()) return;
    const b = this.parent.getContentBounds();
    const clamped = clampStripPos(pos, {
      width: Math.max(0, b.width - VK_STRIP_WIDTH),
      height: Math.max(0, b.height - VK_STRIP_HEIGHT),
    });
    win.setBounds({
      x: b.x + Math.round(clamped.x),
      y: b.y + Math.round(clamped.y),
      width: VK_STRIP_WIDTH,
      height: VK_STRIP_HEIGHT,
    });
  }

  /** Current position relative to the parent's content bounds. */
  private relativePos(): VkStripPos | null {
    const win = this.win;
    if (!win || win.isDestroyed()) return null;
    const b = this.parent.getContentBounds();
    const s = win.getBounds();
    return { x: s.x - b.x, y: s.y - b.y };
  }
}
