/**
 * Zio Browser — Electron main process entry point.
 */
import path from 'path';
import { app, BrowserWindow, Menu, session, nativeTheme } from 'electron';
import type { BaseWindow } from 'electron';
import { initDb, getPreference, setPreference, setActiveProfileId } from './db';
import { PREFERENCE_KEYS } from '../shared/db-schema';
import { sessionPartitionForProfile, DEFAULT_PROFILE_ID } from '../shared/profile-store';
import { TabManager } from './tab-manager';
import { WindowModeManager, CHROME_HEIGHT } from './window-mode-manager';
import {
  registerIpcHandlers,
  registerTabManager,
  registerModeManager,
  getTabManagerForWindow,
  getModeManagerForWindow,
} from './ipc-handlers';
import { setupDownloadManager } from './download-manager';
import { getPrivateSession, registerPrivateWindow } from './private-session';
import type { WindowMode } from '../shared/window-mode';
import { setupAutoUpdater } from './auto-updater';
import type { RecentlyClosedEntry } from './tab-manager';

const isDev = process.env['NODE_ENV'] === 'development';

let mainWindow: BrowserWindow | null = null;

function getRendererUrl(): string {
  if (isDev) {
    return `http://localhost:${process.env['VITE_PORT'] ?? 5173}`;
  }
  return `file://${path.join(__dirname, '../renderer/index.html')}`;
}

// ── Normal window ─────────────────────────────────────────────────────────────

function createWindow(): BrowserWindow {
  session.defaultSession.webRequest.onHeadersReceived((details, callback) => {
    callback({ responseHeaders: { ...details.responseHeaders } });
  });

  const win = new BrowserWindow({
    width: 1280,
    height: 800,
    minWidth: 800,
    minHeight: 600,
    title: 'Zio Browser',
    backgroundColor: nativeTheme.shouldUseDarkColors ? '#1a1a2e' : '#ffffff',
    webPreferences: {
      preload: path.join(__dirname, '../preload/index.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: false,
      webSecurity: true,
    },
    titleBarStyle: process.platform === 'darwin' ? 'hiddenInset' : 'default',
    trafficLightPosition: { x: 12, y: 20 },
    show: false,
  });

  // Restore the active profile from persisted preferences
  const savedProfileId = getPreference(PREFERENCE_KEYS.ACTIVE_PROFILE) ?? DEFAULT_PROFILE_ID;
  setActiveProfileId(savedProfileId);

  // Pre-warm the profile session so it's available before the first tab opens
  if (savedProfileId !== DEFAULT_PROFILE_ID) {
    void session.fromPartition(sessionPartitionForProfile(savedProfileId));
  }

  // Initialize the tab manager with the restored profile session
  const tabManager = new TabManager(win);
  tabManager.setActiveProfilePartition(savedProfileId);
  registerTabManager(win, tabManager);


  tabManager.setCallbacks({
    onTabStateChange: (tabId, state) => win.webContents.send('tab:state-changed', tabId, state),
    onTabCreated:      (tabId)        => win.webContents.send('tab:created', tabId),
    onTabClosed:       (tabId)        => win.webContents.send('tab:closed', tabId),
    onActiveTabChange: (tabId)        => win.webContents.send('tab:activated', tabId),
    onNavigate:        (tabId, url, title) => win.webContents.send('tab:navigated', tabId, url, title),
    onAddToBiolink:    (url, title)   => win.webContents.send('biolink:add-page', url, title),
    onDeviceLabPreview: (url)         => win.webContents.send('device-lab:preview-url', url),
    onFindResult:      (result) => win.webContents.send('tab:find-result', result),
    onTabOrderChange: (order) => win.webContents.send('tab:order-changed', order),
    onPinnedUrlsChange: (urls) => { setPreference(PREFERENCE_KEYS.PINNED_TABS, JSON.stringify(urls)); },
    onRecentlyClosedChange: (entries: RecentlyClosedEntry[]) => win.webContents.send('tab:recently-closed-changed', entries),
  });

  const savedMode  = (getPreference(PREFERENCE_KEYS.WINDOW_MODE) as WindowMode | null) ?? 'browser';
  const savedRatio = parseFloat(getPreference(PREFERENCE_KEYS.SPLIT_RATIO) ?? '0.35') || 0.35;

  const modeManager = new WindowModeManager(win, tabManager, savedMode, savedRatio);
  registerModeManager(win, modeManager);
  modeManager.setModeChangeCallback((mode) => win.webContents.send('window:mode-changed', mode));

  setupDownloadManager(session.defaultSession, win, false);

  win.on('resize', () => modeManager.applyBounds());
  void win.loadURL(getRendererUrl());

  win.once('ready-to-show', () => {
    win.show();
    modeManager.setMode(savedMode);
    if (savedMode === 'browser') {
      // Restore pinned tabs from persistence (background, so they load silently)
      const savedPinnedJson = getPreference(PREFERENCE_KEYS.PINNED_TABS) ?? '[]';
      let savedPinnedUrls: string[] = [];
      try {
        savedPinnedUrls = JSON.parse(savedPinnedJson) as string[];
      } catch {
        savedPinnedUrls = [];
      }
      if (savedPinnedUrls.length > 0) {
        tabManager?.initPinnedUrls(savedPinnedUrls);
      }

      // Open the default new tab (active, placed after pinned tabs)
      const newTabUrl = getPreference(PREFERENCE_KEYS.NEW_TAB_PAGE) ?? undefined;
      tabManager.createTab(newTabUrl);
    }
    if (isDev) win.webContents.openDevTools({ mode: 'detach' });
  });

  win.on('closed', () => {
    modeManager.destroy();
    tabManager.destroyAll();
    if (win === mainWindow) mainWindow = null;
  });

  return win;
}

// ── Private / incognito window ────────────────────────────────────────────────

export function createPrivateWindow(): BrowserWindow {
  const privateSession = getPrivateSession();

  const win = new BrowserWindow({
    width: 1280,
    height: 800,
    minWidth: 800,
    minHeight: 600,
    // Always dark — unmistakable visual signal that this is an incognito window
    title: '🔒 Private – Zio Browser',
    backgroundColor: '#0d0d1a',
    webPreferences: {
      preload: path.join(__dirname, '../preload/index.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: false,
      webSecurity: true,
      // The renderer (app chrome) uses its own default session.
      // Only the tab WebContentsViews use the isolated private session.
    },
    titleBarStyle: process.platform === 'darwin' ? 'hiddenInset' : 'default',
    trafficLightPosition: { x: 12, y: 20 },
    show: false,
  });

  // Register before any 'closed' listener so teardown fires correctly.
  registerPrivateWindow(win);

  // Private TabManager uses the isolated in-memory session for all tabs.
  const tabManager = new TabManager(win, { privateSession });
  registerTabManager(win, tabManager);

  tabManager.setCallbacks({
    onTabStateChange: (tabId, state) => win.webContents.send('tab:state-changed', tabId, state),
    onTabCreated:      (tabId)        => win.webContents.send('tab:created', tabId),
    onTabClosed:       (tabId)        => win.webContents.send('tab:closed', tabId),
    onActiveTabChange: (tabId)        => win.webContents.send('tab:activated', tabId),
    onNavigate:        (tabId, url, title) => win.webContents.send('tab:navigated', tabId, url, title),
    // Link tools (shorten/QR) still work in private mode — they require the
    // account credentials but the visited page itself is never recorded.
    onAddToBiolink: (url, title) => win.webContents.send('biolink:add-page', url, title),
    onDeviceLabPreview: (url) => win.webContents.send('device-lab:preview-url', url),
    onFindResult: (result) => win.webContents.send('tab:find-result', result),
  });

  // Private windows are browser-only — no dashboard or split pane.
  const modeManager = new WindowModeManager(win, tabManager, 'browser', 0.35);
  registerModeManager(win, modeManager);
  modeManager.setModeChangeCallback((mode) => win.webContents.send('window:mode-changed', mode));

  // Downloads complete normally but are NOT written to the persistent DB.
  setupDownloadManager(privateSession, win, true);

  win.on('resize', () => modeManager.applyBounds());
  void win.loadURL(getRendererUrl());

  win.once('ready-to-show', () => {
    win.show();
    modeManager.setMode('browser');
    tabManager.createTab();
  });

  win.on('closed', () => {
    modeManager.destroy();
    tabManager.destroyAll();
  });

  return win;
}

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Electron menu click callbacks type `win` as `BaseWindow | undefined`.
 * Cast to `BrowserWindow` so our registry lookups compile.
 * `BrowserWindow` IS a `BaseWindow`, so the cast is always safe when the
 * menu is triggered from a `BrowserWindow` — which is always the case for
 * this application.
 */
function asBrowserWin(win: BaseWindow | undefined): BrowserWindow | undefined {
  return win as BrowserWindow | undefined;
}

// ── Application menu ──────────────────────────────────────────────────────────

function buildMenu(): void {
  const isMac = process.platform === 'darwin';

  const template: Electron.MenuItemConstructorOptions[] = [
    ...(isMac ? [{ label: app.getName(), submenu: [
      { role: 'about' as const },
      { type: 'separator' as const },
      { role: 'services' as const },
      { type: 'separator' as const },
      { role: 'hide' as const },
      { role: 'hideOthers' as const },
      { role: 'unhide' as const },
      { type: 'separator' as const },
      { role: 'quit' as const },
    ] }] : []),
    {
      label: 'File',
      submenu: [
        {
          label: 'New Tab',
          accelerator: 'CmdOrCtrl+T',
          click: (_item, bw) => {
            const browserWin = asBrowserWin(bw);
            if (!browserWin) return;
            const tm = getTabManagerForWindow(browserWin);
            const mm = getModeManagerForWindow(browserWin);
            if (!tm) return;
            if (mm?.getMode() === 'dashboard') {
              mm.setMode('browser');
            }
            tm.createTab();
          },
        },
        {
          label: 'New Private Window',
          accelerator: 'CmdOrCtrl+Shift+N',
          click: () => { createPrivateWindow(); },
        },
        {
          label: 'Close Tab',
          accelerator: 'CmdOrCtrl+W',
          click: (_item, bw) => {
            const browserWin = asBrowserWin(bw);
            if (!browserWin) return;
            const tm = getTabManagerForWindow(browserWin);
            const id = tm?.getActiveTabId();
            if (id) tm?.closeTab(id);
          },
        },
        {
          label: 'Reopen Closed Tab',
          accelerator: 'CmdOrCtrl+Shift+T',
          click: (_item, bw) => {
            const browserWin = asBrowserWin(bw);
            if (!browserWin) return;
            getTabManagerForWindow(browserWin)?.reopenClosedTab();
          },
        },
        { type: 'separator' },
        isMac ? { role: 'close' as const } : { role: 'quit' as const },
      ],
    },
    {
      label: 'Edit',
      submenu: [
        { role: 'undo' as const },
        { role: 'redo' as const },
        { type: 'separator' as const },
        { role: 'cut' as const },
        { role: 'copy' as const },
        { role: 'paste' as const },
        { role: 'selectAll' as const },
        { type: 'separator' as const },
        {
          label: 'Command Palette',
          accelerator: 'CmdOrCtrl+K',
          click: (_item, bw) => {
            asBrowserWin(bw)?.webContents.send('palette:open');
          },
        },
        { label: 'Find on Page', accelerator: 'CmdOrCtrl+F', click: (_item, bw) => {
          asBrowserWin(bw)?.webContents.send('find:open');
        }},
        {
          label: 'Search Tabs',
          accelerator: 'CmdOrCtrl+Shift+A',
          click: () => {
            mainWindow?.webContents.send('tab:search-open');
          },
        },
      ],
    },
    {
      label: 'View',
      submenu: [
        {
          label: 'Dashboard Mode',
          accelerator: 'CmdOrCtrl+Shift+1',
          click: (_item, bw) => {
            const browserWin = asBrowserWin(bw);
            if (!browserWin) return;
            getModeManagerForWindow(browserWin)?.setMode('dashboard');
          },
        },
        {
          label: 'Split Mode',
          accelerator: 'CmdOrCtrl+Shift+2',
          click: (_item, bw) => {
            const browserWin = asBrowserWin(bw);
            if (!browserWin) return;
            getModeManagerForWindow(browserWin)?.setMode('split');
          },
        },
        {
          label: 'Browser Mode',
          accelerator: 'CmdOrCtrl+Shift+3',
          click: (_item, bw) => {
            const browserWin = asBrowserWin(bw);
            if (!browserWin) return;
            getModeManagerForWindow(browserWin)?.setMode('browser');
          },
        },
        { type: 'separator' as const },
        { label: 'Zoom In', accelerator: 'CmdOrCtrl+=', click: (_item, bw) => {
          const browserWin = asBrowserWin(bw);
          if (!browserWin) return;
          const tm = getTabManagerForWindow(browserWin);
          const id = tm?.getActiveTabId();
          if (id) { const s = tm?.getTabState(id); tm?.setZoom(id, (s?.zoomFactor ?? 1) + 0.1); }
        }},
        { label: 'Zoom Out', accelerator: 'CmdOrCtrl+-', click: (_item, bw) => {
          const browserWin = asBrowserWin(bw);
          if (!browserWin) return;
          const tm = getTabManagerForWindow(browserWin);
          const id = tm?.getActiveTabId();
          if (id) { const s = tm?.getTabState(id); tm?.setZoom(id, (s?.zoomFactor ?? 1) - 0.1); }
        }},
        { label: 'Reset Zoom', accelerator: 'CmdOrCtrl+0', click: (_item, bw) => {
          const browserWin = asBrowserWin(bw);
          if (!browserWin) return;
          const tm = getTabManagerForWindow(browserWin);
          const id = tm?.getActiveTabId();
          if (id) tm?.setZoom(id, 1.0);
        }},
        { type: 'separator' as const },
        { label: 'Reload', accelerator: 'CmdOrCtrl+R', click: (_item, bw) => {
          const browserWin = asBrowserWin(bw);
          if (!browserWin) return;
          const tm = getTabManagerForWindow(browserWin);
          const id = tm?.getActiveTabId();
          if (id) tm?.reload(id);
        }},
        { label: 'Force Reload', accelerator: 'CmdOrCtrl+Shift+R', click: (_item, bw) => {
          const browserWin = asBrowserWin(bw);
          if (!browserWin) return;
          const tm = getTabManagerForWindow(browserWin);
          const id = tm?.getActiveTabId();
          if (id) tm?.reload(id, true);
        }},
        { type: 'separator' as const },
        { label: 'Developer Tools', accelerator: 'F12', click: (_item, bw) => {
          const browserWin = asBrowserWin(bw);
          if (!browserWin) return;
          const tm = getTabManagerForWindow(browserWin);
          const id = tm?.getActiveTabId();
          if (id) tm?.getWebContents(id)?.openDevTools();
        }},
      ],
    },
    {
      label: 'History',
      submenu: [
        { label: 'Back', accelerator: 'Alt+Left', click: (_item, bw) => {
          const browserWin = asBrowserWin(bw);
          if (!browserWin) return;
          const tm = getTabManagerForWindow(browserWin);
          const id = tm?.getActiveTabId();
          if (id) tm?.goBack(id);
        }},
        { label: 'Forward', accelerator: 'Alt+Right', click: (_item, bw) => {
          const browserWin = asBrowserWin(bw);
          if (!browserWin) return;
          const tm = getTabManagerForWindow(browserWin);
          const id = tm?.getActiveTabId();
          if (id) tm?.goForward(id);
        }},
      ],
    },
    {
      label: 'Window',
      submenu: [
        { role: 'minimize' as const },
        { role: 'zoom' as const },
        ...(isMac ? [{ type: 'separator' as const }, { role: 'front' as const }] : []),
      ],
    },
  ];

  Menu.setApplicationMenu(Menu.buildFromTemplate(template));
}

// ── App lifecycle ─────────────────────────────────────────────────────────────

app.whenReady().then(() => {
  try {
    initDb();
  } catch (err) {
    console.error('Failed to initialize database:', err);
  }
  mainWindow = createWindow();
  setupAutoUpdater();

  // Register IPC handlers once — global, serves all windows.
  registerIpcHandlers(mainWindow);

  buildMenu();

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      mainWindow = createWindow();
    }
  });
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') app.quit();
});

// Security: Prevent new window creation from web content
app.on('web-contents-created', (_, contents) => {
  contents.on('will-attach-webview', (event) => {
    event.preventDefault();
  });
});

// Silence the unused CHROME_HEIGHT import warning
void CHROME_HEIGHT;
