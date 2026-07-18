import type { Configuration } from 'electron-builder';

const config: Configuration = {
  appId: 'com.sayzio.browser',
  productName: 'SayZio Browser',
  copyright: 'Copyright © 2025 SayZio',

  directories: {
    output: 'release',
    buildResources: 'build-resources',
  },

  files: [
    'dist/**/*',
    'node_modules/**/*',
    'package.json',
  ],

  extraMetadata: {
    main: 'dist/main/index.js',
  },

  // macOS
  mac: {
    category: 'public.app-category.productivity',
    target: [
      { target: 'dmg', arch: ['x64', 'arm64'] },
      { target: 'zip', arch: ['x64', 'arm64'] },
    ],
    // Code signing — set CSC_LINK and CSC_KEY_PASSWORD secrets in CI
    // identity: null means unsigned (suitable for development/testing)
    identity: null,
    hardenedRuntime: false,
    gatekeeperAssess: false,
    // Notarization — set APPLE_ID, APPLE_APP_SPECIFIC_PASSWORD, APPLE_TEAM_ID in CI
    // notarize: { teamId: process.env.APPLE_TEAM_ID }
  },

  dmg: {
    title: 'SayZio Browser',
    icon: 'build-resources/icon.icns',
    contents: [
      { x: 130, y: 220 },
      { x: 410, y: 220, type: 'link', path: '/Applications' },
    ],
    window: { width: 540, height: 380 },
  },

  // Windows
  win: {
    target: [
      { target: 'nsis', arch: ['x64'] },
    ],
    // Code signing — set WIN_CSC_LINK and WIN_CSC_KEY_PASSWORD in CI
    // certificateFile: process.env.WIN_CSC_LINK
    // sign: false means unsigned (suitable for development/testing)
  },

  nsis: {
    oneClick: false,
    allowToChangeInstallationDirectory: true,
    installerIcon: 'build-resources/icon.ico',
    uninstallerIcon: 'build-resources/icon.ico',
    installerHeaderIcon: 'build-resources/icon.ico',
    createDesktopShortcut: true,
    createStartMenuShortcut: true,
    shortcutName: 'SayZio Browser',
  },

  // Auto-update server (configure when ready)
  publish: null,
};

export default config;
