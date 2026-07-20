const { withAndroidManifest } = require("expo/config-plugins");

// Android 11+ package-visibility: without these <queries> entries,
// PackageManager.getPackageInfo() cannot see the messaging apps, so the
// WhatsApp vs WhatsApp Business chooser could never trigger.
const PACKAGES = [
  "com.whatsapp",
  "com.whatsapp.w4b",
  "org.telegram.messenger",
  "com.instagram.android",
];

module.exports = function withAndroidQueries(config) {
  return withAndroidManifest(config, (config) => {
    const manifest = config.modResults.manifest;
    if (!Array.isArray(manifest.queries)) manifest.queries = [];
    if (!manifest.queries[0]) manifest.queries[0] = {};
    const queries = manifest.queries[0];
    if (!Array.isArray(queries.package)) queries.package = [];
    for (const name of PACKAGES) {
      if (!queries.package.some((p) => p.$?.["android:name"] === name)) {
        queries.package.push({ $: { "android:name": name } });
      }
    }
    return config;
  });
};
