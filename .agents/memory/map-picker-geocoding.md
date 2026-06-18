---
name: Map picker geocoding pattern
description: How interactive lat/lng pin pickers are built across web (Laravel/Alpine) and mobile (Expo) in 1INME.
---

Reusable pattern for "drop a pin to fill address + lat/lng" editors (first used on the Dialer Identity manual location editor).

- **Web (Laravel + Alpine):** load the *vendored* Leaflet (`public/{js,css}/vendor/leaflet.min.js/css`), never a CDN (SRI drift breaks it). Build the pin with `L.divIcon` + inline SVG (no marker image assets). Geocode with OpenStreetMap **Nominatim**: reverse `/reverse?format=jsonv2&lat=&lon=`, forward `/search?format=jsonv2&limit=1&q=`. Init the map lazily on first toggle and `invalidateSize()` after it becomes visible. Add `[x-cloak]{display:none}` + dark `.leaflet-container`/controls theming since the page is dark-mode by default.
- **Mobile (Expo):** there is no native Leaflet — host a Leaflet map inside `react-native-webview` (lazy-require it; it's null on web platform). Load Leaflet from a CDN *inside the WebView HTML* (CDN is fine there, it's sandboxed). The WebView posts `{type:'point',lat,lng,address}` back via `window.ReactNativeWebView.postMessage`; drive search/locate from RN via `injectJavaScript(window.__search/__locate)`. `expo-location` powers an optional "use my location".

**Why:** keeps the saved shape identical (`location[label]/address/lat/lng`) so backend normalizers (e.g. `DialerIdentity::normalizeManual`) need no change; Nominatim is free/keyless.
**How to apply:** copy `artifacts/1inme-mobile/components/MapPickerModal.tsx` for mobile; mirror the Alpine `dialerManual()` map methods on web.
