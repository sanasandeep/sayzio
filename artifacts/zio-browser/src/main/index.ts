/**
 * Zio Browser — Electron main process entry point.
 */
import path from 'path';
import { app, BrowserWindow, Menu, session, nativeTheme } from 'electron';
import { initDb, getPreference } from './db';
import { PREFERENCE_KEYS } from '../shared/db-schema';
import { TabManager } from './tab-manager';
import { registerIpcHandlers } from './ipc-handlers';
import { setupDownloadManager } from './download-manager';
import { setupAutoUpdater } from './auto-updater';

const isDev = process.env['NODE_ENV'] === 'development';
const CHROME_HEIGHT = 72; // height of the tab strip + address bar

let mainWindow: BrowserWindow | null = null;
let tabManager: TabManager | null = null;

function getRendererUrl(): string {
  if (isDev) {
    return `http://localhost:${process.env['VITE_PORT'] ?? 5173}`;
  }
  return `file://${path.join(__dirname, '../renderer/index.html')}`;
}

function createWindow(): void {
  // Security: Set up a restrictive CSP for all pages loaded by the app chrome
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
      sandbox: false, // preload needs Node
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
  });

  // Register all IPC handlers
  registerIpcHandlers(tabManager);

  // Setup download manager
  setupDownloadManager(session.defaultSession, mainWindow);

  // Handle window resize — update tab view bounds
  mainWindow.on('resize', () => {
    if (!mainWindow || !tabManager) return;
    const [w, h] = mainWindow.getContentSize();
    tabManager.resizeTabs({ x: 0, y: CHROME_HEIGHT, width: w, height: h - CHROME_HEIGHT });
  });

  // Load the renderer (app chrome)
  void mainWindow.loadURL(getRendererUrl());

  mainWindow.once('ready-to-show', () => {
    mainWindow?.show();

    // Load user preferences
    const searchEngine = getPreference(PREFERENCE_KEYS.SEARCH_ENGINE);
    if (searchEngine) {
      // Apply search engine preference to tab manager
    }

    // Open the default new tab
    const newTabUrl = getPreference(PREFERENCE_KEYS.NEW_TAB_PAGE) ?? undefined;
    tabManager?.createTab(newTabUrl);

    if (isDev) {
      mainWindow?.webContents.openDevTools({ mode: 'detach' });
    }
  });

  mainWindow.on('closed', () => {
    tabManager?.destroyAll();
    mainWindow = null;
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
        { label: 'New Tab', accelerator: 'CmdOrCtrl+T', click: () => tabManager?.createTab() },
        { label: 'Close Tab', accelerator: 'CmdOrCtrl+W', click: () => {
          const activeId = tabManager?.getActiveTabId();
          if (activeId) tabManager?.closeTab(activeId);
        }},
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
    // Disallow webview tags in the app chrome
    event.preventDefault();
  });
});
