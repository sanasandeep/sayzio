import { Platform, View, type StyleProp, type ViewStyle } from "react-native";

// Lazy-require so the web bundle never tries to evaluate the native module.
let WebView: typeof import("react-native-webview").WebView | null = null;
if (Platform.OS !== "web") {
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  WebView = require("react-native-webview").WebView;
}

const PIN_SVG =
  '<svg viewBox="0 0 34 44" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
  '<defs><linearGradient id="g" x1="0" y1="0" x2="0" y2="1">' +
  '<stop offset="0%" stop-color="#a78bfa"/><stop offset="100%" stop-color="#7c3aed"/>' +
  "</linearGradient></defs>" +
  '<path d="M17 0C7.6 0 0 7.5 0 16.7c0 11.7 14.6 25.5 16 26.8.6.6 1.5.6 2 0 1.5-1.3 16-15.1 16-26.8C34 7.5 26.4 0 17 0z" fill="url(#g)" stroke="rgba(255,255,255,0.85)" stroke-width="1.5"/>' +
  '<circle cx="17" cy="16" r="6" fill="#fff"/></svg>';

function buildHtml(lat: number, lng: number): string {
  return `<!DOCTYPE html><html><head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
  html, body, #map { height: 100%; margin: 0; padding: 0; background: #1e2330; }
  .pin { width: 30px; height: 40px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.45)); }
  .pin svg { width: 100%; height: 100%; display: block; }
  .leaflet-control-attribution { font-size: 8px; }
</style>
</head><body>
<div id="map"></div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  var map = L.map('map', {
    center: [${lat}, ${lng}], zoom: 15,
    zoomControl: false, attributionControl: true,
    dragging: false, touchZoom: false, scrollWheelZoom: false,
    doubleClickZoom: false, boxZoom: false, keyboard: false, tap: false
  });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);
  var icon = L.divIcon({ className: '', html: '<div class="pin">${PIN_SVG}</div>', iconSize: [30,40], iconAnchor: [15,40] });
  L.marker([${lat}, ${lng}], { icon: icon, interactive: false, keyboard: false }).addTo(map);
  setTimeout(function(){ map.invalidateSize(); }, 120);
</script>
</body></html>`;
}

function buildMultiHtml(markers: MapMarker[]): string {
  const pts = JSON.stringify(
    markers.map((m) => ({
      lat: m.lat,
      lng: m.lng,
      label: m.label ?? "",
      url: m.url ?? "",
    })),
  );
  return `<!DOCTYPE html><html><head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
  html, body, #map { height: 100%; margin: 0; padding: 0; background: #1e2330; }
  .pin { width: 30px; height: 40px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.45)); cursor: pointer; }
  .pin svg { width: 100%; height: 100%; display: block; }
  .leaflet-control-attribution { font-size: 8px; }
</style>
</head><body>
<div id="map"></div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  var pts = ${pts};
  var map = L.map('map', {
    zoomControl: false, attributionControl: true,
    dragging: false, touchZoom: false, scrollWheelZoom: false,
    doubleClickZoom: false, boxZoom: false, keyboard: false
  });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);
  var icon = L.divIcon({ className: '', html: '<div class="pin">${PIN_SVG}</div>', iconSize: [30,40], iconAnchor: [15,40] });
  var latlngs = [];
  pts.forEach(function (p) {
    if (!isFinite(p.lat) || !isFinite(p.lng)) return;
    var m = L.marker([p.lat, p.lng], { icon: icon, title: p.label }).addTo(map);
    m.on('click', function () {
      if (window.ReactNativeWebView && p.url) {
        window.ReactNativeWebView.postMessage(JSON.stringify({ url: p.url }));
      }
    });
    latlngs.push([p.lat, p.lng]);
  });
  if (latlngs.length > 1) {
    map.fitBounds(latlngs, { padding: [28, 28], maxZoom: 16 });
  } else if (latlngs.length === 1) {
    map.setView(latlngs[0], 15);
  }
  setTimeout(function(){ map.invalidateSize(); }, 120);
</script>
</body></html>`;
}

export type MapPreviewProps = {
  lat: number;
  lng: number;
  height?: number;
  style?: ViewStyle;
};

export type MapMarker = {
  lat: number;
  lng: number;
  label?: string;
  url?: string;
};

export type MapMarkersPreviewProps = {
  markers: MapMarker[];
  height?: number;
  style?: StyleProp<ViewStyle>;
  onMarkerPress?: (url: string) => void;
};

/**
 * Read-only Leaflet map showing every saved location as a pin, auto-fit to
 * bounds. Unlike `MapPreview`, this map receives touches so a pin tap can be
 * forwarded to `onMarkerPress` (which opens that location in Maps).
 */
export function MapMarkersPreview({
  markers,
  height = 200,
  style,
  onMarkerPress,
}: MapMarkersPreviewProps) {
  const valid = markers.filter(
    (m) => isFinite(m.lat) && isFinite(m.lng),
  );
  if (!WebView || valid.length === 0) return null;

  return (
    <View style={[{ height, width: "100%", backgroundColor: "#1e2330" }, style]}>
      <WebView
        originWhitelist={["*"]}
        source={{ html: buildMultiHtml(valid) }}
        style={{ flex: 1, backgroundColor: "#1e2330" }}
        scrollEnabled={false}
        javaScriptEnabled
        onMessage={(e) => {
          try {
            const data = JSON.parse(e.nativeEvent.data) as { url?: string };
            if (data?.url && onMarkerPress) onMarkerPress(data.url);
          } catch {
            // ignore malformed messages
          }
        }}
      />
    </View>
  );
}

/**
 * Read-only Leaflet map thumbnail centered on a saved point. Renders
 * inside a `pointerEvents="none"` wrapper so taps fall through to the
 * surrounding Pressable (which opens the location in Maps).
 */
export function MapPreview({ lat, lng, height = 130, style }: MapPreviewProps) {
  if (!WebView || !isFinite(lat) || !isFinite(lng)) return null;

  return (
    <View
      pointerEvents="none"
      style={[{ height, width: "100%", backgroundColor: "#1e2330" }, style]}
    >
      <WebView
        originWhitelist={["*"]}
        source={{ html: buildHtml(lat, lng) }}
        style={{ flex: 1, backgroundColor: "#1e2330" }}
        scrollEnabled={false}
        javaScriptEnabled
        pointerEvents="none"
      />
    </View>
  );
}
