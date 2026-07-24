/**
 * Zio Browser — auto-update bootstrap (electron-updater).
 *
 * The update feed is the GitHub Releases channel configured in
 * electron-builder.config.ts (`publish: { provider: 'github', ... }`).
 * electron-builder embeds that feed into the packaged app (app-update.yml),
 * so no URL needs to be configured here.
 *
 * Updates only run in packaged builds — in dev this module is a no-op.
 */
import { app, dialog } from 'electron';

const CHECK_INTERVAL_MS = 4 * 60 * 60 * 1000; // every 4 hours

export function setupAutoUpdater(): void {
  if (!app.isPackaged) return;

  // Lazy require so a missing/incompatible updater never breaks app startup.
  let autoUpdater: typeof import('electron-updater').autoUpdater;
  try {
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    autoUpdater = (require('electron-updater') as typeof import('electron-updater')).autoUpdater;
  } catch (err) {
    console.error('electron-updater unavailable:', err);
    return;
  }

  autoUpdater.autoDownload = true;
  autoUpdater.autoInstallOnAppQuit = true;

  autoUpdater.on('error', (err) => {
    // Unsigned macOS builds cannot auto-update (Squirrel.Mac requires a
    // signed app); log and move on rather than bothering the user.
    console.error('Auto-update error:', err?.message ?? err);
  });

  autoUpdater.on('update-downloaded', (info) => {
    void dialog
      .showMessageBox({
        type: 'info',
        title: 'Update ready',
        message: `Zio Browser ${info.version} has been downloaded.`,
        detail: 'Restart the app to apply the update.',
        buttons: ['Restart now', 'Later'],
        defaultId: 0,
        cancelId: 1,
      })
      .then(({ response }) => {
        if (response === 0) autoUpdater.quitAndInstall();
      });
  });

  const check = (): void => {
    autoUpdater.checkForUpdates().catch((err: unknown) => {
      console.error('Update check failed:', err);
    });
  };

  check();
  setInterval(check, CHECK_INTERVAL_MS);
}
