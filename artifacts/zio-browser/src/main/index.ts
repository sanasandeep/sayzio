/**
 * Zio Browser — Electron main process entry point.
 */
import path from 'path';
import { app, BrowserWindow, Menu, session, nativeTheme } from 'electron';
import { initDb, getPreference } from './db';
import { PREFERENCE_KEYS } from '../shared/db-schema';
import { TabManager } from './tab-manager';
import { WindowModeManager, CHROME_HEIGHT } from './window-mode-manager';
import { registerIpcHandlers } from './ipc-handlers';
import { setupDownloadManager } from './download-manager';
import type { WindowMode } from '../shared/window-mode';
import { setupAutoUpdater } from './auto-updater';

const isDev = process.env['NODE_ENV'] === 'development';

let mainWindow: BrowserWindow | null = null;
let tabManager: TabManager | null = null;
let modeManager: WindowModeManager | null = null;

function getRendererUrl(): string {
  if (isDev) {
    return `http://localhost:${process.env['VITE_PORT'] ?? 5173}`;
  }
  return `file://${path.join(__dirname, '../renderer/index.html')}`;
}

function createWindow(): void {
  session.defaultSession.webRequest.onHeadersReceived((details, callback) => {
    callback({
      responseHeaders: {
        ...details.responseHeaders,
      },
    });
  });

  mainWindow = new BrowserWindow({
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

  // Initialize the tab manager
  tabManager = new TabManager(mainWindow);
  tabManager.setCallbacks({
    onTabStateChange: (tabId, state) => {
      mainWindow?.webContents.send('tab:state-changed', tabId, state);
    },
    onTabCreated: (tabId) => {
      mainWindow?.webContents.send('tab:created', tabId);
    },
    onTabClosed: (tabId) => {
      mainWindow?.webContents.send('tab:closed', tabId);
    },
    onActiveTabChange: (tabId) => {
      mainWindow?.webContents.send('tab:activated', tabId);
    },
    onNavigate: (tabId, url, title) => {
      mainWindow?.webContents.send('tab:navigated', tabId, url, title);
    },
    onAddToBiolink: (url, title) => {
      // Open the Zio panel (if not already open) and trigger the add-to-biolink
      // modal in the renderer by sending a typed IPC push event.
      mainWindow?.webContents.send('biolink:add-page', url, title);
    },
    onFindResult: (result) => {
      mainWindow?.webContents.send('tab:find-result', result);
    },
  });

  // Read persisted mode and split ratio
  const savedMode = (getPreference(PREFERENCE_KEYS.WINDOW_MODE) as WindowMode | null) ?? 'browser';
  const savedRatio = parseFloat(getPreference(PREFERENCE_KEYS.SPLIT_RATIO) ?? '0.35') || 0.35;

  // Initialize the window mode manager
  modeManager = new WindowModeManager(mainWindow, tabManager, savedMode, savedRatio);
  modeManager.setModeChangeCallback((mode) => {
    mainWindow?.webContents.send('window:mode-changed', mode);
  });

  // Register all IPC handlers
  registerIpcHandlers(tabManager, modeManager, mainWindow);

  // Setup download manager
  setupDownloadManager(session.defaultSession, mainWindow);

  // Handle window resize — update view bounds through the mode manager
  mainWindow.on('resize', () => {
    if (!mainWindow || !modeManager) return;
    modeManager.applyBounds();
  });

  // Load the renderer (app chrome)
  void mainWindow.loadURL(getRendererUrl());

  mainWindow.once('ready-to-show', () => {
    mainWindow?.show();

    // Apply the initial mode (sets up views)
    modeManager?.setMode(savedMode);

    // In browser mode, also open the default new tab
    if (savedMode === 'browser') {
      const newTabUrl = getPreference(PREFERENCE_KEYS.NEW_TAB_PAGE) ?? undefined;
      tabManager?.createTab(newTabUrl);
    }

    if (isDev) {
      mainWindow?.webContents.openDevTools({ mode: 'detach' });
    }
  });

  mainWindow.on('closed', () => {
    modeManager?.destroy();
    tabManager?.destroyAll();
    mainWindow = null;
    tabManager = null;
    modeManager = null;
  });

  buildMenu();
}

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
          click: () => {
            const mode = modeManager?.getMode() ?? 'browser';
            if (mode === 'dashboard') {
              // Switch to browser mode, then open a tab
              modeManager?.setMode('browser');
              tabManager?.createTab();
            } else {
              tabManager?.createTab();
            }
          },
        },
        {
          label: 'Close Tab',
          accelerator: 'CmdOrCtrl+W',
          click: () => {
            const activeId = tabManager?.getActiveTabId();
            if (activeId) tabManager?.closeTab(activeId);
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
        { label: 'Find on Page', accelerator: 'CmdOrCtrl+F', click: () => {
          mainWindow?.webContents.send('find:open');
        }},
      ],
    },
    {
      label: 'View',
      submenu: [
        {
          label: 'Dashboard Mode',
          accelerator: 'CmdOrCtrl+Shift+1',
          click: () => { modeManager?.setMode('dashboard'); },
        },
        {
          label: 'Split Mode',
          accelerator: 'CmdOrCtrl+Shift+2',
          click: () => { modeManager?.setMode('split'); },
        },
        {
          label: 'Browser Mode',
          accelerator: 'CmdOrCtrl+Shift+3',
          click: () => { modeManager?.setMode('browser'); },
        },
        { type: 'separator' as const },
        { label: 'Zoom In', accelerator: 'CmdOrCtrl+=', click: () => {
          const id = tabManager?.getActiveTabId();
          if (id) { const s = tabManager?.getTabState(id); tabManager?.setZoom(id, (s?.zoomFactor ?? 1) + 0.1); }
        }},
        { label: 'Zoom Out', accelerator: 'CmdOrCtrl+-', click: () => {
          const id = tabManager?.getActiveTabId();
          if (id) { const s = tabManager?.getTabState(id); tabManager?.setZoom(id, (s?.zoomFactor ?? 1) - 0.1); }
        }},
        { label: 'Reset Zoom', accelerator: 'CmdOrCtrl+0', click: () => {
          const id = tabManager?.getActiveTabId();
          if (id) tabManager?.setZoom(id, 1.0);
        }},
        { type: 'separator' as const },
        { label: 'Reload', accelerator: 'CmdOrCtrl+R', click: () => {
          const id = tabManager?.getActiveTabId();
          if (id) tabManager?.reload(id);
        }},
        { label: 'Force Reload', accelerator: 'CmdOrCtrl+Shift+R', click: () => {
          const id = tabManager?.getActiveTabId();
          if (id) tabManager?.reload(id, true);
        }},
        { type: 'separator' as const },
        { label: 'Developer Tools', accelerator: 'F12', click: () => {
          const id = tabManager?.getActiveTabId();
          if (id) tabManager?.getWebContents(id)?.openDevTools();
        }},
      ],
    },
    {
      label: 'History',
      submenu: [
        { label: 'Back', accelerator: 'Alt+Left', click: () => {
          const id = tabManager?.getActiveTabId();
          if (id) tabManager?.goBack(id);
        }},
        { label: 'Forward', accelerator: 'Alt+Right', click: () => {
          const id = tabManager?.getActiveTabId();
          if (id) tabManager?.goForward(id);
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

// App lifecycle
app.whenReady().then(() => {
  try {
    initDb();
  } catch (err) {
    console.error('Failed to initialize database:', err);
  }
  createWindow();
  setupAutoUpdater();

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) createWindow();
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
