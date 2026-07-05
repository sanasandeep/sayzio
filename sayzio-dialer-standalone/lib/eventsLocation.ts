import AsyncStorage from "@react-native-async-storage/async-storage";
import * as Location from "expo-location";

/**
 * The single saved "my location" used to anchor Events discovery (near-me
 * filtering + nearby-event alerts). Set either via device GPS or by dropping
 * a pin in the same `MapPickerModal` the dialer already uses for contact
 * locations, so there is exactly one location concept in this app — not a
 * separate ad-hoc geolocation call per screen.
 */
export type SavedEventsLocation = {
  lat: number;
  lng: number;
  label: string | null;
};

const STORAGE_KEY = "1inme.events.location.v1";

const listeners = new Set<(loc: SavedEventsLocation | null) => void>();

export function onEventsLocationChange(
  listener: (loc: SavedEventsLocation | null) => void,
): () => void {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

export async function getSavedEventsLocation(): Promise<SavedEventsLocation | null> {
  try {
    const raw = await AsyncStorage.getItem(STORAGE_KEY);
    return raw ? (JSON.parse(raw) as SavedEventsLocation) : null;
  } catch {
    return null;
  }
}

export async function setSavedEventsLocation(
  loc: SavedEventsLocation | null,
): Promise<void> {
  try {
    if (loc) {
      await AsyncStorage.setItem(STORAGE_KEY, JSON.stringify(loc));
    } else {
      await AsyncStorage.removeItem(STORAGE_KEY);
    }
  } catch {
    // Best-effort — worst case the user re-picks a location next time.
  }
  for (const fn of listeners) {
    try {
      fn(loc);
    } catch {
      // noop
    }
  }
}

/**
 * Resolve a location for "near me" filtering without requiring the user to
 * have explicitly picked one yet: prefer the saved pin, else fall back to a
 * one-off device GPS read (not persisted unless the user opts in via the
 * picker).
 */
export async function resolveEventsLocation(): Promise<SavedEventsLocation | null> {
  const saved = await getSavedEventsLocation();
  if (saved) return saved;
  try {
    const { status } = await Location.getForegroundPermissionsAsync();
    if (status !== "granted") return null;
    const pos = await Location.getCurrentPositionAsync({});
    return { lat: pos.coords.latitude, lng: pos.coords.longitude, label: null };
  } catch {
    return null;
  }
}
