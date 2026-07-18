import type { Configuration } from 'electron-builder';

/**
 * SayZio Browser — electron-builder configuration.
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

const config: Configuration = {
  appId: 'com.sayzio.browser',
  productName: 'SayZio Browser',
  copyright: 'Copyright © 2026 SayZio',

  directories: {
    output: 'release',
    buildResources: 'build-resources',
  },

  files: [
    'dist/**/*',
    'package.json',
  ],

  extraMetadata: {
    main: 'dist/main/main/index.js',
  },

  // macOS
  mac: {
    category: 'public.app-category.productivity',
    icon: 'build-resources/icon.png',
    target: [
      { target: 'dmg', arch: ['x64', 'arm64'] },
      { target: 'zip', arch: ['x64', 'arm64'] },
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
            ? { notarize: { teamId: process.env['APPLE_TEAM_ID'] as string } }
            : {}),
        }
      : {
          identity: null,
          hardenedRuntime: false,
          gatekeeperAssess: false,
        }),
  },

  dmg: {
    title: 'SayZio Browser',
    contents: [
      { x: 130, y: 220 },
      { x: 410, y: 220, type: 'link', path: '/Applications' },
    ],
    window: { width: 540, height: 380 },
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
    shortcutName: 'SayZio Browser',
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

export default config;
