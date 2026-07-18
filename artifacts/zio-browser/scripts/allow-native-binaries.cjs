/**
 * CI helper: the workspace pnpm-workspace.yaml blocks non-Linux native
 * binaries (dev-environment size optimization) by overriding them to '-'.
 * macOS/Windows CI runners need their own platform binaries, so strip
 * those override lines before `pnpm install`.
 */
const fs = require('fs');
const path = require('path');

const file = path.resolve(__dirname, '../../../pnpm-workspace.yaml');
const before = fs.readFileSync(file, 'utf8');
const after = before
  .split('\n')
  .filter((line) => !line.trimEnd().endsWith(": '-'"))
  .join('\n');
fs.writeFileSync(file, after);
console.log(
  `allow-native-binaries: removed ${before.split('\n').length - after.split('\n').length} platform-block override lines`,
);
