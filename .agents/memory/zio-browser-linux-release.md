---
name: Zio Browser Linux release verification
description: Gotchas from the first real Linux (AppImage + .deb) release of Zio Browser
---

- electron-builder's `deb` target hard-fails with "Please specify project homepage" unless `homepage` is set in the artifact's package.json (maintainer alone is not enough). First release with Linux assets is zio-browser-v0.3.8.
- Linux artifact names come out as `-x86_64.AppImage` / `-amd64.deb` (electron-builder arch naming), not `-x64`. The release-refresh parser keys on file extension, so this is fine — but don't grep releases for `-x64` Linux names.
- **Self-update can be smoke-tested headless in the dev container:** `--appimage-extract` the AppImage, binary-patch `"version": "X.Y.Z"` in resources/app.asar to an older same-length version, run the extracted binary under `xvfb-run` with `APPIMAGE=<writable copy>` and `--no-sandbox`; electron-updater downloads the new AppImage into `~/.cache/@workspacezio-browser-updater/pending`. Without APPIMAGE env the app logs "Auto-update disabled: non-AppImage Linux install (.deb)" (.deb path).
- **How to apply:** for CI fixes on GitHub main, prefer a minimal single-file commit via the contents API (PUT with the remote blob sha) over a full gh-sync squash — the isolated env may lag remote main and a full sync could regress merged work.
