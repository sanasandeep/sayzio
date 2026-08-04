# EAS build verification — fresh checkout uses the new Expo project

Verified 2026-08-04 (task: confirm fresh GitHub checkout builds under owner eefind / projectId 7d456b75-3a4b-4f59-95b1-369f0cb1ba77).

## 1. GitHub main app.json matches workspace
- Fetched `artifacts/1inme-mobile/app.json` from GitHub main via API: `owner: eefind`, `extra.eas.projectId: 7d456b75-3a4b-4f59-95b1-369f0cb1ba77`, no `extra.eas.build.experimental` appExtensions block; byte-identical to the workspace copy.

## 2. expo config resolves cleanly (no silent exit-1)
```
exit 0; owner: eefind projectId: 7d456b75-3a4b-4f59-95b1-369f0cb1ba77
```

## 3. EAS Android preview build (eas-apk-build workflow, EAS_NO_VCS=1)
```
buildId: 32d3d1d5-a6a6-4254-a0f1-6765b80f98c6
status: FINISHED
platform: ANDROID
project: @eefind/sayzio-mobile (id 7d456b75-3a4b-4f59-95b1-369f0cb1ba77)
apk: https://expo.dev/artifacts/eas/Ydbox-hCdGm249NgxSPfGMyUdhVAeoUWsiGPMUgxmLo.apk
```

Keystore: Build Credentials t_sTTUkFSn (new project keystore). Build page: https://expo.dev/accounts/eefind/projects/sayzio-mobile/builds/32d3d1d5-a6a6-4254-a0f1-6765b80f98c6
