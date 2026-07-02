import { Feather } from "@expo/vector-icons";
import * as Location from "expo-location";
import { useEffect, useMemo, useRef, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Modal,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { useColors } from "@/hooks/useColors";

// Lazy-require so the web bundle never tries to evaluate the native module.
let WebView: typeof import("react-native-webview").WebView | null = null;
if (Platform.OS !== "web") {
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  WebView = require("react-native-webview").WebView;
}

export type PickedPoint = { lat: number; lng: number; address: string };

export type MapPickerModalProps = {
  visible: boolean;
  initialLat?: number | null;
  initialLng?: number | null;
  onClose: () => void;
  onPick: (point: PickedPoint) => void;
};

const PIN_SVG =
  '<svg viewBox="0 0 34 44" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
  '<defs><linearGradient id="g" x1="0" y1="0" x2="0" y2="1">' +
  '<stop offset="0%" stop-color="#7d9bff"/><stop offset="100%" stop-color="#3d6bff"/>' +
  "</linearGradient></defs>" +
  '<path d="M17 0C7.6 0 0 7.5 0 16.7c0 11.7 14.6 25.5 16 26.8.6.6 1.5.6 2 0 1.5-1.3 16-15.1 16-26.8C34 7.5 26.4 0 17 0z" fill="url(#g)" stroke="rgba(255,255,255,0.85)" stroke-width="1.5"/>' +
  '<circle cx="17" cy="16" r="6" fill="#fff"/>' +
  '<text x="17" y="19.5" text-anchor="middle" font-family="sans-serif" font-size="8" font-weight="700" fill="#3d6bff">1</text>' +
  "</svg>";

function buildHtml(lat: number | null, lng: number | null): string {
  const hasPoint = lat !== null && lng !== null;
  const initLat = hasPoint ? lat : 20;
  const initLng = hasPoint ? lng : 0;
  const initZoom = hasPoint ? 15 : 2;
  return `<!DOCTYPE html><html><head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
  html, body, #map { height: 100%; margin: 0; padding: 0; background: #1e2330; }
  .pin { width: 30px; height: 40px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.45)); }
  .pin svg { width: 100%; height: 100%; display: block; }
  .leaflet-control-attribution { font-size: 9px; }
</style>
</head><body>
<div id="map"></div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  function post(o){ if (window.ReactNativeWebView) window.ReactNativeWebView.postMessage(JSON.stringify(o)); }
  var hasPoint = ${hasPoint ? "true" : "false"};
  var map = L.map('map', { zoomControl: true }).setView([${initLat}, ${initLng}], ${initZoom});
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);
  var icon = L.divIcon({ className: '', html: '<div class="pin">${PIN_SVG}</div>', iconSize: [30,40], iconAnchor: [15,40] });
  var marker = L.marker([${initLat}, ${initLng}], { icon: icon, draggable: true }).addTo(map);
  if (!hasPoint) marker.setOpacity(0);
  function reverse(lat, lng){
    fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + lat + '&lon=' + lng, { headers: { 'Accept': 'application/json' } })
      .then(function(r){ return r.ok ? r.json() : null; })
      .then(function(d){ post({ type: 'point', lat: lat, lng: lng, address: (d && d.display_name) || '' }); })
      .catch(function(){ post({ type: 'point', lat: lat, lng: lng, address: '' }); });
  }
  function setPoint(lat, lng, recenter){
    marker.setLatLng([lat, lng]);
    marker.setOpacity(1);
    if (recenter) map.setView([lat, lng], Math.max(map.getZoom(), 15));
    reverse(lat, lng);
  }
  map.on('click', function(e){ setPoint(e.latlng.lat, e.latlng.lng, false); });
  marker.on('dragend', function(){ var p = marker.getLatLng(); setPoint(p.lat, p.lng, false); });
  window.__search = function(q){
    if (!q) return;
    fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
      .then(function(r){ return r.ok ? r.json() : null; })
      .then(function(d){ if (d && d.length) { setPoint(parseFloat(d[0].lat), parseFloat(d[0].lon), true); } else { post({ type: 'noresult' }); } })
      .catch(function(){});
  };
  window.__locate = function(lat, lng){ setPoint(lat, lng, true); };
  setTimeout(function(){ map.invalidateSize(); post({ type: 'ready' }); }, 120);
</script>
</body></html>`;
}

export function MapPickerModal({
  visible,
  initialLat,
  initialLng,
  onClose,
  onPick,
}: MapPickerModalProps) {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const webRef = useRef<import("react-native-webview").WebView | null>(null);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [selected, setSelected] = useState<PickedPoint | null>(null);

  const html = useMemo(
    () => buildHtml(initialLat ?? null, initialLng ?? null),
    [initialLat, initialLng],
  );

  useEffect(() => {
    if (visible) {
      setLoading(true);
      setSelected(
        initialLat != null && initialLng != null
          ? { lat: initialLat, lng: initialLng, address: "" }
          : null,
      );
      setSearch("");
    }
  }, [visible, initialLat, initialLng]);

  const runSearch = () => {
    const q = search.trim();
    if (!q || !webRef.current) return;
    webRef.current.injectJavaScript(
      `window.__search(${JSON.stringify(q)}); true;`,
    );
  };

  const useMyLocation = async () => {
    try {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status !== "granted") {
        Alert.alert(
          "Permission needed",
          "Allow location access to center the map on where you are.",
        );
        return;
      }
      const pos = await Location.getCurrentPositionAsync({});
      webRef.current?.injectJavaScript(
        `window.__locate(${pos.coords.latitude}, ${pos.coords.longitude}); true;`,
      );
    } catch {
      Alert.alert("Error", "Could not get your current location.");
    }
  };

  const confirm = () => {
    if (!selected) {
      Alert.alert("Pick a spot", "Tap the map to drop a pin first.");
      return;
    }
    onPick(selected);
    onClose();
  };

  const onMessage = (raw: string) => {
    try {
      const msg = JSON.parse(raw) as {
        type: string;
        lat?: number;
        lng?: number;
        address?: string;
      };
      if (msg.type === "ready") {
        setLoading(false);
      } else if (
        msg.type === "point" &&
        typeof msg.lat === "number" &&
        typeof msg.lng === "number"
      ) {
        setSelected({ lat: msg.lat, lng: msg.lng, address: msg.address ?? "" });
      } else if (msg.type === "noresult") {
        Alert.alert("No match", "We couldn't find that place.");
      }
    } catch {
      /* ignore malformed messages */
    }
  };

  return (
    <Modal
      visible={visible}
      animationType="slide"
      onRequestClose={onClose}
      presentationStyle="pageSheet"
    >
      <View style={[styles.root, { backgroundColor: colors.background }]}>
        <View
          style={[
            styles.header,
            {
              paddingTop: insets.top + 10,
              borderBottomColor: colors.border,
              backgroundColor: colors.card,
            },
          ]}
        >
          <Pressable onPress={onClose} hitSlop={12} style={styles.headerBtn}>
            <Feather name="x" size={22} color={colors.foreground} />
          </Pressable>
          <Text
            style={[styles.headerTitle, { color: colors.foreground }]}
            numberOfLines={1}
          >
            Pick location
          </Text>
          <View style={styles.headerBtn} />
        </View>

        <View
          style={[
            styles.searchBar,
            { borderBottomColor: colors.border, backgroundColor: colors.card },
          ]}
        >
          <TextInput
            value={search}
            onChangeText={setSearch}
            onSubmitEditing={runSearch}
            placeholder="Search a place or address…"
            placeholderTextColor={colors.mutedForeground}
            autoCapitalize="none"
            returnKeyType="search"
            style={[
              styles.searchInput,
              { color: colors.foreground, borderColor: colors.border },
            ]}
          />
          <Pressable
            onPress={runSearch}
            style={[styles.searchBtn, { backgroundColor: colors.primary }]}
          >
            <Feather name="search" size={16} color="#fff" />
          </Pressable>
          <Pressable
            onPress={useMyLocation}
            style={[styles.searchBtn, { borderColor: colors.border, borderWidth: 1 }]}
          >
            <Feather name="navigation" size={16} color={colors.primary} />
          </Pressable>
        </View>

        <View style={{ flex: 1 }}>
          {WebView ? (
            <WebView
              ref={(r) => {
                webRef.current = r;
              }}
              originWhitelist={["*"]}
              source={{ html }}
              style={{ flex: 1, backgroundColor: colors.background }}
              javaScriptEnabled
              domStorageEnabled
              onMessage={(e) => onMessage(e.nativeEvent.data)}
            />
          ) : (
            <View style={[styles.center, { flex: 1 }]}>
              <Text style={{ color: colors.mutedForeground, textAlign: "center", paddingHorizontal: 24 }}>
                The map picker isn't available here. Enter the coordinates
                manually instead.
              </Text>
            </View>
          )}
          {loading && WebView ? (
            <View pointerEvents="none" style={styles.spinner}>
              <ActivityIndicator color={colors.primary} />
            </View>
          ) : null}
        </View>

        <View
          style={[
            styles.footer,
            {
              paddingBottom: insets.bottom + 12,
              borderTopColor: colors.border,
              backgroundColor: colors.card,
            },
          ]}
        >
          <Text
            numberOfLines={2}
            style={{
              color: colors.mutedForeground,
              fontSize: 12,
              marginBottom: 10,
              fontFamily: "SpaceGrotesk_400Regular",
            }}
          >
            {selected
              ? selected.address ||
                `${selected.lat.toFixed(5)}, ${selected.lng.toFixed(5)}`
              : "Tap the map or drag the pin to choose a location."}
          </Text>
          <Pressable
            onPress={confirm}
            disabled={!selected}
            style={[
              styles.confirmBtn,
              { backgroundColor: colors.primary, opacity: selected ? 1 : 0.5 },
            ]}
          >
            <Text style={styles.confirmText}>Use this location</Text>
          </Pressable>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1 },
  center: { alignItems: "center", justifyContent: "center" },
  header: {
    flexDirection: "row",
    alignItems: "center",
    paddingHorizontal: 16,
    paddingBottom: 10,
    borderBottomWidth: StyleSheet.hairlineWidth,
    gap: 12,
  },
  headerBtn: { padding: 4, width: 30 },
  headerTitle: {
    flex: 1,
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 15,
    textAlign: "center",
  },
  searchBar: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  searchInput: {
    flex: 1,
    borderWidth: 1,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 9,
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 14,
  },
  searchBtn: {
    width: 40,
    height: 40,
    borderRadius: 10,
    alignItems: "center",
    justifyContent: "center",
  },
  spinner: {
    ...StyleSheet.absoluteFillObject,
    alignItems: "center",
    justifyContent: "center",
  },
  footer: {
    paddingHorizontal: 16,
    paddingTop: 12,
    borderTopWidth: StyleSheet.hairlineWidth,
  },
  confirmBtn: {
    paddingVertical: 13,
    borderRadius: 12,
    alignItems: "center",
  },
  confirmText: {
    color: "#fff",
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 15,
  },
});
