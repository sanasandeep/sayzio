/**
 * Zio Browser — electron-builder configuration.
 *
 * Code signing & notarization are driven entirely by environment variables so
 * the same config produces unsigned dev builds locally and fully
 * signed/notarized builds in CI once the secrets are configured:
 *
 *   macOS signing:      CSC_LINK (base64 .p12) + CSC_KEY_PASSWORD
 *   macOS notarization: APPLE_ID + APPLE_APP_SPECIFIC_PASSWORD + APPLE_TEAM_ID
 *   Windows signing:    WIN_CSC_LINK (base64 .pfx) + WIN_CSC_KEY_PASSWORD
 *
 * When the variables are absent, electron-builder skips signing and the
 * notarize block below is disabled — builds still succeed (unsigned).
 */
const macSigningEnabled = Boolean(process.env['CSC_LINK']);
const macNotarizeEnabled = Boolean(
  process.env['APPLE_ID'] &&
    process.env['APPLE_APP_SPECIFIC_PASSWORD'] &&
    process.env['APPLE_TEAM_ID'],
);

/** @type {import('electron-builder').Configuration} */
const config = {
  // Unsigned mac builds: electron-builder's repack invalidates Electron's
  // original ad-hoc signature, and macOS 15+ reports an app with a broken
  // signature as "contains malware" (hard block, no Open Anyway). Re-applying
  // a fresh ad-hoc signature after packaging restores internal consistency so
  // Gatekeeper downgrades to the normal unidentified-developer warning that
  // users can bypass. Skipped when real signing is enabled.
  afterPack: async (context) => {
    if (context.electronPlatformName !== 'darwin' || macSigningEnabled) return;
    const path = require('path');
    const { execSync } = require('child_process');
    const appPath = path.join(
      context.appOutDir,
      `${context.packager.appInfo.productFilename}.app`,
    );
    execSync(`codesign --force --deep --sign - "${appPath}"`, { stdio: 'inherit' });
    execSync(`codesign --verify --deep --strict "${appPath}"`, { stdio: 'inherit' });
  },
  appId: 'com.sayzio.browser',
  productName: 'Zio Browser',
  // No spaces in artifact names: GitHub converts spaces to dots on upload,
  // which breaks the electron-updater feed (latest.yml uses hyphenated names).
  artifactName: 'Zio-Browser-${version}-${arch}.${ext}',
  copyright: 'Copyright © 2026 Sayzio',

  directories: {
    output: 'release',
    buildResources: 'build-resources',
  },

  files: [
    'dist/**/*',
    'package.json',
  ],

  // Native modules (better-sqlite3) CANNOT be loaded from inside app.asar.
  // electron-builder's smartUnpack fails to detect them in this pnpm
  // workspace (symlinked node_modules), so v0.1.7 shipped better_sqlite3.node
  // packed inside the asar — initDb() threw at runtime on every install
  // ("Database not initialized" downstream error). Force-unpack them.
  asarUnpack: ['**/*.node'],

  extraMetadata: {
    main: 'dist/main/main/index.js',
  },

  // macOS
  mac: {
    category: 'public.app-category.productivity',
    icon: 'build-resources/icon.png',
    // ZIP feeds electron-updater on macOS; DMG is the headline installer the
    // /download page requires. The DMG target was flaky on GitHub macOS
    // runners (dmgbuild default-background tiff ENOENT race when both arch
    // builds run in one invocation) — mitigated by the solid backgroundColor
    // below (skips the generated tiff entirely) plus sequential per-arch
    // packaging in CI (see zio-browser-build.yml).
    target: [
      { target: 'zip', arch: ['x64', 'arm64'] },
      { target: 'dmg', arch: ['x64', 'arm64'] },
    ],
    // Signed builds require hardened runtime for notarization to pass.
    // Unsigned (no CSC_LINK): identity null keeps local/dev builds working.
    ...(macSigningEnabled
      ? {
          hardenedRuntime: true,
          gatekeeperAssess: false,
          entitlements: 'build-resources/entitlements.mac.plist',
          entitlementsInherit: 'build-resources/entitlements.mac.plist',
          ...(macNotarizeEnabled
            ? { notarize: { teamId: process.env['APPLE_TEAM_ID'] } }
            : {}),
        }
      : {
          identity: null,
          hardenedRuntime: false,
          gatekeeperAssess: false,
        }),
  },

  // Solid background color makes dmgbuild skip generating its default
  // background.tiff — the file whose ENOENT race broke DMG builds on GitHub
  // macOS runners. The update feed uses the mac ZIPs, not the DMGs.
  dmg: {
    backgroundColor: '#ffffff',
    writeUpdateInfo: false,
  },

  // Windows — WIN_CSC_LINK / WIN_CSC_KEY_PASSWORD enable signing when present.
  win: {
    icon: 'build-resources/icon.png',
    target: [{ target: 'nsis', arch: ['x64'] }],
  },

  nsis: {
    oneClick: false,
    allowToChangeInstallationDirectory: true,
    createDesktopShortcut: true,
    createStartMenuShortcut: true,
    shortcutName: 'Zio Browser',
  },

  // Auto-update feed — electron-updater reads GitHub Releases of this repo.
  // CI publishes latest*.yml + installers into each release.
  publish: {
    provider: 'github',
    owner: 'sanasandeep',
    repo: 'sayzio',
    releaseType: 'release',
  },
};

module.exports = config;
