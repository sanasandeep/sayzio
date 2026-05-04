import { Feather } from "@expo/vector-icons";
import { useMemo, useState } from "react";
import {
  FlatList,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { AppIcon } from "@/components/AppIcon";
import { useColors } from "@/hooks/useColors";

// Icon catalog mirrors the web editor's
// `resources/views/user/links/partials/icon-picker.blade.php` so picks
// roam between web and mobile. We keep the *full* FontAwesome class
// (e.g. `fas fa-check`) as the stored value, matching what the web
// picker writes — this lets the public web template render the icon
// unchanged. The mobile public renderer goes through `AppIcon`, which
// already understands both the bare (`fa-check`) and prefixed
// (`fas fa-check` / `fab fa-github`) forms.
type CatalogIcon = { c: string; n: string; t: string };

const CATALOG: readonly CatalogIcon[] = [
  { c: "fas fa-globe", n: "Globe", t: "general" },
  { c: "fas fa-link", n: "Link", t: "general" },
  { c: "fas fa-external-link-alt", n: "External Link", t: "general" },
  { c: "fas fa-home", n: "Home", t: "general" },
  { c: "fas fa-star", n: "Star", t: "general" },
  { c: "fas fa-heart", n: "Heart", t: "general" },
  { c: "fas fa-fire", n: "Fire", t: "general" },
  { c: "fas fa-bolt", n: "Bolt", t: "general" },
  { c: "fas fa-crown", n: "Crown", t: "general" },
  { c: "fas fa-gem", n: "Gem", t: "general" },
  { c: "fas fa-trophy", n: "Trophy", t: "general" },
  { c: "fas fa-medal", n: "Medal", t: "general" },
  { c: "fas fa-award", n: "Award", t: "general" },
  { c: "fas fa-certificate", n: "Certificate", t: "general" },
  { c: "fas fa-check", n: "Check", t: "general" },
  { c: "fas fa-check-circle", n: "Check Circle", t: "general" },
  { c: "fas fa-times", n: "Times", t: "general" },
  { c: "fas fa-plus", n: "Plus", t: "general" },
  { c: "fas fa-minus", n: "Minus", t: "general" },
  { c: "fas fa-info-circle", n: "Info", t: "general" },
  { c: "fas fa-exclamation-circle", n: "Exclamation", t: "general" },
  { c: "fas fa-question-circle", n: "Question", t: "general" },
  { c: "fas fa-bell", n: "Bell", t: "general" },
  { c: "fas fa-bookmark", n: "Bookmark", t: "general" },
  { c: "fas fa-flag", n: "Flag", t: "general" },
  { c: "fas fa-tag", n: "Tag", t: "general" },
  { c: "fas fa-tags", n: "Tags", t: "general" },
  { c: "fas fa-thumbs-up", n: "Thumbs Up", t: "general" },
  { c: "fas fa-thumbs-down", n: "Thumbs Down", t: "general" },
  { c: "fas fa-smile", n: "Smile", t: "general" },
  { c: "fas fa-laugh", n: "Laugh", t: "general" },
  { c: "fas fa-grin-stars", n: "Star Eyes", t: "general" },
  { c: "fas fa-rocket", n: "Rocket", t: "general" },
  { c: "fas fa-paper-plane", n: "Paper Plane", t: "general" },
  { c: "fas fa-lightbulb", n: "Lightbulb", t: "general" },
  { c: "fas fa-magic", n: "Magic", t: "general" },
  { c: "fas fa-sparkles", n: "Sparkles", t: "general" },
  { c: "fas fa-sun", n: "Sun", t: "general" },
  { c: "fas fa-moon", n: "Moon", t: "general" },
  { c: "fas fa-cloud", n: "Cloud", t: "general" },
  { c: "fas fa-snowflake", n: "Snowflake", t: "general" },
  { c: "fas fa-leaf", n: "Leaf", t: "general" },
  { c: "fas fa-seedling", n: "Seedling", t: "general" },
  { c: "fas fa-tree", n: "Tree", t: "general" },
  { c: "fas fa-paw", n: "Paw", t: "general" },
  { c: "fas fa-feather", n: "Feather", t: "general" },
  { c: "fas fa-user", n: "User", t: "people" },
  { c: "fas fa-users", n: "Users", t: "people" },
  { c: "fas fa-user-circle", n: "User Circle", t: "people" },
  { c: "fas fa-user-plus", n: "User Plus", t: "people" },
  { c: "fas fa-user-tie", n: "User Tie", t: "people" },
  { c: "fas fa-people-group", n: "Group", t: "people" },
  { c: "fas fa-handshake", n: "Handshake", t: "people" },
  { c: "fas fa-hand-holding-heart", n: "Holding Heart", t: "people" },
  { c: "fas fa-envelope", n: "Email", t: "communication" },
  { c: "fas fa-envelope-open", n: "Email Open", t: "communication" },
  { c: "fas fa-phone", n: "Phone", t: "communication" },
  { c: "fas fa-phone-alt", n: "Phone Alt", t: "communication" },
  { c: "fas fa-mobile-alt", n: "Mobile", t: "communication" },
  { c: "fas fa-comment", n: "Comment", t: "communication" },
  { c: "fas fa-comments", n: "Comments", t: "communication" },
  { c: "fas fa-comment-dots", n: "Chat", t: "communication" },
  { c: "fas fa-inbox", n: "Inbox", t: "communication" },
  { c: "fas fa-at", n: "At", t: "communication" },
  { c: "fas fa-share-alt", n: "Share", t: "communication" },
  { c: "fas fa-share", n: "Share Arrow", t: "communication" },
  { c: "fab fa-instagram", n: "Instagram", t: "social" },
  { c: "fab fa-x-twitter", n: "X/Twitter", t: "social" },
  { c: "fab fa-facebook-f", n: "Facebook", t: "social" },
  { c: "fab fa-tiktok", n: "TikTok", t: "social" },
  { c: "fab fa-youtube", n: "YouTube", t: "social" },
  { c: "fab fa-linkedin-in", n: "LinkedIn", t: "social" },
  { c: "fab fa-github", n: "GitHub", t: "social" },
  { c: "fab fa-discord", n: "Discord", t: "social" },
  { c: "fab fa-telegram", n: "Telegram", t: "social" },
  { c: "fab fa-whatsapp", n: "WhatsApp", t: "social" },
  { c: "fab fa-snapchat-ghost", n: "Snapchat", t: "social" },
  { c: "fab fa-pinterest", n: "Pinterest", t: "social" },
  { c: "fab fa-reddit", n: "Reddit", t: "social" },
  { c: "fab fa-twitch", n: "Twitch", t: "social" },
  { c: "fab fa-spotify", n: "Spotify", t: "social" },
  { c: "fab fa-apple", n: "Apple", t: "social" },
  { c: "fab fa-google", n: "Google", t: "social" },
  { c: "fab fa-amazon", n: "Amazon", t: "social" },
  { c: "fab fa-dribbble", n: "Dribbble", t: "social" },
  { c: "fab fa-behance", n: "Behance", t: "social" },
  { c: "fab fa-figma", n: "Figma", t: "social" },
  { c: "fab fa-slack", n: "Slack", t: "social" },
  { c: "fab fa-medium", n: "Medium", t: "social" },
  { c: "fab fa-patreon", n: "Patreon", t: "social" },
  { c: "fab fa-paypal", n: "PayPal", t: "social" },
  { c: "fab fa-stripe", n: "Stripe", t: "social" },
  { c: "fab fa-etsy", n: "Etsy", t: "social" },
  { c: "fab fa-shopify", n: "Shopify", t: "social" },
  { c: "fab fa-soundcloud", n: "SoundCloud", t: "social" },
  { c: "fab fa-vimeo-v", n: "Vimeo", t: "social" },
  { c: "fab fa-steam", n: "Steam", t: "social" },
  { c: "fab fa-xbox", n: "Xbox", t: "social" },
  { c: "fab fa-playstation", n: "PlayStation", t: "social" },
  { c: "fas fa-shopping-cart", n: "Cart", t: "commerce" },
  { c: "fas fa-shopping-bag", n: "Bag", t: "commerce" },
  { c: "fas fa-store", n: "Store", t: "commerce" },
  { c: "fas fa-credit-card", n: "Card", t: "commerce" },
  { c: "fas fa-wallet", n: "Wallet", t: "commerce" },
  { c: "fas fa-money-bill-wave", n: "Money", t: "commerce" },
  { c: "fas fa-coins", n: "Coins", t: "commerce" },
  { c: "fas fa-dollar-sign", n: "Dollar", t: "commerce" },
  { c: "fas fa-percent", n: "Percent", t: "commerce" },
  { c: "fas fa-receipt", n: "Receipt", t: "commerce" },
  { c: "fas fa-gift", n: "Gift", t: "commerce" },
  { c: "fas fa-box", n: "Box", t: "commerce" },
  { c: "fas fa-truck", n: "Truck", t: "commerce" },
  { c: "fas fa-barcode", n: "Barcode", t: "commerce" },
  { c: "fas fa-camera", n: "Camera", t: "media" },
  { c: "fas fa-image", n: "Image", t: "media" },
  { c: "fas fa-images", n: "Images", t: "media" },
  { c: "fas fa-video", n: "Video", t: "media" },
  { c: "fas fa-film", n: "Film", t: "media" },
  { c: "fas fa-music", n: "Music", t: "media" },
  { c: "fas fa-headphones", n: "Headphones", t: "media" },
  { c: "fas fa-microphone", n: "Microphone", t: "media" },
  { c: "fas fa-podcast", n: "Podcast", t: "media" },
  { c: "fas fa-play", n: "Play", t: "media" },
  { c: "fas fa-play-circle", n: "Play Circle", t: "media" },
  { c: "fas fa-pause", n: "Pause", t: "media" },
  { c: "fas fa-volume-up", n: "Volume", t: "media" },
  { c: "fas fa-palette", n: "Palette", t: "media" },
  { c: "fas fa-paint-brush", n: "Brush", t: "media" },
  { c: "fas fa-pen", n: "Pen", t: "media" },
  { c: "fas fa-pencil-alt", n: "Pencil", t: "media" },
  { c: "fas fa-file", n: "File", t: "content" },
  { c: "fas fa-file-alt", n: "File Text", t: "content" },
  { c: "fas fa-file-pdf", n: "PDF", t: "content" },
  { c: "fas fa-file-image", n: "File Image", t: "content" },
  { c: "fas fa-file-video", n: "File Video", t: "content" },
  { c: "fas fa-file-audio", n: "File Audio", t: "content" },
  { c: "fas fa-file-code", n: "File Code", t: "content" },
  { c: "fas fa-file-download", n: "Download", t: "content" },
  { c: "fas fa-folder", n: "Folder", t: "content" },
  { c: "fas fa-folder-open", n: "Folder Open", t: "content" },
  { c: "fas fa-clipboard", n: "Clipboard", t: "content" },
  { c: "fas fa-copy", n: "Copy", t: "content" },
  { c: "fas fa-book", n: "Book", t: "content" },
  { c: "fas fa-book-open", n: "Book Open", t: "content" },
  { c: "fas fa-newspaper", n: "Newspaper", t: "content" },
  { c: "fas fa-blog", n: "Blog", t: "content" },
  { c: "fas fa-quote-left", n: "Quote", t: "content" },
  { c: "fas fa-align-left", n: "Align", t: "content" },
  { c: "fas fa-list", n: "List", t: "content" },
  { c: "fas fa-table", n: "Table", t: "content" },
  { c: "fas fa-calendar", n: "Calendar", t: "content" },
  { c: "fas fa-calendar-alt", n: "Calendar Alt", t: "content" },
  { c: "fas fa-clock", n: "Clock", t: "content" },
  { c: "fas fa-hourglass-half", n: "Hourglass", t: "content" },
  { c: "fas fa-map-marker-alt", n: "Location", t: "misc" },
  { c: "fas fa-map", n: "Map", t: "misc" },
  { c: "fas fa-compass", n: "Compass", t: "misc" },
  { c: "fas fa-directions", n: "Directions", t: "misc" },
  { c: "fas fa-car", n: "Car", t: "misc" },
  { c: "fas fa-plane", n: "Plane", t: "misc" },
  { c: "fas fa-train", n: "Train", t: "misc" },
  { c: "fas fa-bicycle", n: "Bicycle", t: "misc" },
  { c: "fas fa-utensils", n: "Food", t: "misc" },
  { c: "fas fa-coffee", n: "Coffee", t: "misc" },
  { c: "fas fa-glass-cheers", n: "Cheers", t: "misc" },
  { c: "fas fa-birthday-cake", n: "Cake", t: "misc" },
  { c: "fas fa-graduation-cap", n: "Education", t: "misc" },
  { c: "fas fa-university", n: "University", t: "misc" },
  { c: "fas fa-flask", n: "Flask", t: "misc" },
  { c: "fas fa-microscope", n: "Microscope", t: "misc" },
  { c: "fas fa-dumbbell", n: "Fitness", t: "misc" },
  { c: "fas fa-heartbeat", n: "Health", t: "misc" },
  { c: "fas fa-stethoscope", n: "Medical", t: "misc" },
  { c: "fas fa-pills", n: "Pills", t: "misc" },
  { c: "fas fa-pray", n: "Pray", t: "misc" },
  { c: "fas fa-church", n: "Church", t: "misc" },
  { c: "fas fa-gavel", n: "Law", t: "misc" },
  { c: "fas fa-shield-alt", n: "Shield", t: "misc" },
  { c: "fas fa-lock", n: "Lock", t: "misc" },
  { c: "fas fa-unlock", n: "Unlock", t: "misc" },
  { c: "fas fa-key", n: "Key", t: "misc" },
  { c: "fas fa-eye", n: "Eye", t: "misc" },
  { c: "fas fa-search", n: "Search", t: "misc" },
  { c: "fas fa-cog", n: "Settings", t: "misc" },
  { c: "fas fa-cogs", n: "Gears", t: "misc" },
  { c: "fas fa-wrench", n: "Wrench", t: "misc" },
  { c: "fas fa-tools", n: "Tools", t: "misc" },
  { c: "fas fa-code", n: "Code", t: "misc" },
  { c: "fas fa-terminal", n: "Terminal", t: "misc" },
  { c: "fas fa-database", n: "Database", t: "misc" },
  { c: "fas fa-server", n: "Server", t: "misc" },
  { c: "fas fa-wifi", n: "WiFi", t: "misc" },
  { c: "fas fa-signal", n: "Signal", t: "misc" },
  { c: "fas fa-chart-bar", n: "Bar Chart", t: "misc" },
  { c: "fas fa-chart-line", n: "Line Chart", t: "misc" },
  { c: "fas fa-chart-pie", n: "Pie Chart", t: "misc" },
  { c: "fas fa-download", n: "Download", t: "misc" },
  { c: "fas fa-upload", n: "Upload", t: "misc" },
  { c: "fas fa-arrow-right", n: "Arrow Right", t: "misc" },
  { c: "fas fa-arrow-left", n: "Arrow Left", t: "misc" },
  { c: "fas fa-arrow-up", n: "Arrow Up", t: "misc" },
  { c: "fas fa-arrow-down", n: "Arrow Down", t: "misc" },
  { c: "fas fa-angle-right", n: "Angle Right", t: "misc" },
  { c: "fas fa-angle-double-right", n: "Double Right", t: "misc" },
  { c: "fas fa-chevron-right", n: "Chevron Right", t: "misc" },
  { c: "fas fa-long-arrow-alt-right", n: "Long Arrow", t: "misc" },
  { c: "fas fa-hand-point-right", n: "Point Right", t: "misc" },
  { c: "fas fa-mouse-pointer", n: "Cursor", t: "misc" },
  { c: "fas fa-bullhorn", n: "Bullhorn", t: "misc" },
  { c: "fas fa-megaphone", n: "Megaphone", t: "misc" },
  { c: "fas fa-bullseye", n: "Target", t: "misc" },
  { c: "fas fa-crosshairs", n: "Crosshairs", t: "misc" },
  { c: "fas fa-fingerprint", n: "Fingerprint", t: "misc" },
  { c: "fas fa-qrcode", n: "QR Code", t: "misc" },
  { c: "fas fa-hashtag", n: "Hashtag", t: "misc" },
  { c: "fas fa-puzzle-piece", n: "Puzzle", t: "misc" },
  { c: "fas fa-dice", n: "Dice", t: "misc" },
  { c: "fas fa-gamepad", n: "Gamepad", t: "misc" },
];

const CATEGORIES: readonly { key: string; label: string }[] = [
  { key: "all", label: "All" },
  { key: "general", label: "General" },
  { key: "social", label: "Social" },
  { key: "commerce", label: "Commerce" },
  { key: "media", label: "Media" },
  { key: "people", label: "People" },
  { key: "communication", label: "Communication" },
  { key: "content", label: "Content" },
  { key: "misc", label: "Misc" },
];

export type IconPickerModalProps = {
  visible: boolean;
  value: string;
  // `null` from `onChange` clears the icon (falls back to the block default).
  onChange: (next: string) => void;
  onClose: () => void;
  title?: string;
  // Render a "Use default" clear-button when the value is per-item icon
  // (list bullets); pricing items don't have a default to fall back to.
  allowClear?: boolean;
};

export function IconPickerModal({
  visible,
  value,
  onChange,
  onClose,
  title = "Pick an icon",
  allowClear = false,
}: IconPickerModalProps) {
  const colors = useColors();
  const [search, setSearch] = useState("");
  const [activeCat, setActiveCat] = useState("all");

  const filtered = useMemo(() => {
    const s = search.trim().toLowerCase();
    return CATALOG.filter((ic) => {
      if (activeCat !== "all" && ic.t !== activeCat) return false;
      if (!s) return true;
      return (
        ic.n.toLowerCase().indexOf(s) !== -1 ||
        ic.c.toLowerCase().indexOf(s) !== -1
      );
    });
  }, [search, activeCat]);

  return (
    <Modal
      visible={visible}
      animationType="slide"
      transparent
      onRequestClose={onClose}
    >
      <View style={styles.backdrop}>
        <View
          style={[
            styles.sheet,
            { backgroundColor: colors.background, borderColor: colors.border },
          ]}
        >
          <View style={styles.header}>
            <Text style={[styles.title, { color: colors.foreground }]}>
              {title}
            </Text>
            <Pressable onPress={onClose} hitSlop={10} style={{ padding: 6 }}>
              <Feather name="x" size={20} color={colors.foreground} />
            </Pressable>
          </View>

          <View
            style={[
              styles.searchWrap,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}
          >
            <Feather name="search" size={14} color={colors.mutedForeground} />
            <TextInput
              value={search}
              onChangeText={setSearch}
              placeholder="Search icons..."
              placeholderTextColor={colors.mutedForeground}
              autoCapitalize="none"
              autoCorrect={false}
              style={[styles.searchInput, { color: colors.foreground }]}
            />
            {search ? (
              <Pressable onPress={() => setSearch("")} hitSlop={8}>
                <Feather name="x" size={14} color={colors.mutedForeground} />
              </Pressable>
            ) : null}
          </View>

          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={{ gap: 6, paddingHorizontal: 4 }}
            style={{ marginTop: 8, flexGrow: 0 }}
          >
            {CATEGORIES.map((cat) => {
              const selected = activeCat === cat.key;
              return (
                <Pressable
                  key={cat.key}
                  onPress={() => setActiveCat(cat.key)}
                  style={{
                    paddingHorizontal: 10,
                    paddingVertical: 5,
                    borderRadius: 999,
                    backgroundColor: selected ? colors.primary : colors.card,
                    borderWidth: 1,
                    borderColor: selected ? colors.primary : colors.border,
                  }}
                >
                  <Text
                    style={{
                      color: selected ? "#fff" : colors.foreground,
                      fontWeight: "600",
                      fontSize: 11,
                    }}
                  >
                    {cat.label}
                  </Text>
                </Pressable>
              );
            })}
          </ScrollView>

          {allowClear ? (
            <Pressable
              onPress={() => {
                onChange("");
                onClose();
              }}
              style={[
                styles.clearBtn,
                { borderColor: colors.border, backgroundColor: colors.card },
              ]}
            >
              <Feather name="rotate-ccw" size={14} color={colors.mutedForeground} />
              <Text style={{ color: colors.mutedForeground, fontSize: 12, fontWeight: "600" }}>
                Use default
              </Text>
            </Pressable>
          ) : null}

          <FlatList
            data={filtered}
            keyExtractor={(item) => item.c}
            numColumns={5}
            keyboardShouldPersistTaps="handled"
            contentContainerStyle={{ paddingVertical: 8, paddingHorizontal: 4 }}
            ListEmptyComponent={
              <View style={{ paddingVertical: 32, alignItems: "center" }}>
                <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                  No icons found
                </Text>
              </View>
            }
            renderItem={({ item }) => {
              const selected = item.c === value;
              return (
                <Pressable
                  onPress={() => {
                    onChange(item.c);
                    onClose();
                  }}
                  style={{
                    flex: 1 / 5,
                    aspectRatio: 1,
                    margin: 3,
                    borderRadius: 10,
                    alignItems: "center",
                    justifyContent: "center",
                    backgroundColor: selected ? colors.primary : colors.card,
                    borderWidth: 1,
                    borderColor: selected ? colors.primary : colors.border,
                  }}
                >
                  <AppIcon
                    name={item.c}
                    size={20}
                    color={selected ? "#fff" : colors.foreground}
                  />
                  <Text
                    numberOfLines={1}
                    style={{
                      marginTop: 4,
                      maxWidth: "100%",
                      paddingHorizontal: 2,
                      fontSize: 8,
                      color: selected ? "#fff" : colors.mutedForeground,
                    }}
                  >
                    {item.n}
                  </Text>
                </Pressable>
              );
            }}
          />
        </View>
      </View>
    </Modal>
  );
}

// "Browse icons" trigger: shows a preview of the current icon, the
// stored class name, and opens the picker on tap. Reused for default
// bullet, per-item list bullets, and per-item pricing icons.
export function IconPickerButton({
  value,
  onPress,
  placeholder = "Choose icon...",
}: {
  value: string;
  onPress: () => void;
  placeholder?: string;
}) {
  const colors = useColors();
  const hasValue = !!value.trim();
  return (
    <Pressable
      onPress={onPress}
      style={[
        styles.trigger,
        { backgroundColor: colors.card, borderColor: colors.border },
      ]}
    >
      <View
        style={{
          width: 28,
          height: 28,
          borderRadius: 6,
          alignItems: "center",
          justifyContent: "center",
          backgroundColor: colors.background,
        }}
      >
        {hasValue ? (
          <AppIcon name={value} size={16} color={colors.foreground} />
        ) : (
          <Feather name="image" size={14} color={colors.mutedForeground} />
        )}
      </View>
      <View style={{ flex: 1, minWidth: 0 }}>
        <Text
          style={{
            color: hasValue ? colors.foreground : colors.mutedForeground,
            fontSize: 12,
            fontWeight: "600",
          }}
        >
          {hasValue ? "Browse icons" : placeholder}
        </Text>
        {hasValue ? (
          <Text
            numberOfLines={1}
            style={{
              color: colors.mutedForeground,
              fontSize: 10,
              marginTop: 1,
            }}
          >
            {value}
          </Text>
        ) : null}
      </View>
      <Feather name="chevron-down" size={14} color={colors.mutedForeground} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.45)",
    justifyContent: "flex-end",
  },
  sheet: {
    height: "82%",
    borderTopLeftRadius: 18,
    borderTopRightRadius: 18,
    borderTopWidth: 1,
    paddingHorizontal: 12,
    paddingTop: 12,
    paddingBottom: 16,
  },
  header: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingHorizontal: 4,
    marginBottom: 10,
  },
  title: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 16 },
  searchWrap: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingHorizontal: 10,
    paddingVertical: 8,
    borderRadius: 10,
    borderWidth: 1,
  },
  searchInput: {
    flex: 1,
    fontSize: 13,
    padding: 0,
  },
  clearBtn: {
    marginTop: 8,
    paddingHorizontal: 10,
    paddingVertical: 8,
    borderRadius: 10,
    borderWidth: 1,
    borderStyle: "dashed",
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 6,
  },
  trigger: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    padding: 8,
    borderRadius: 10,
    borderWidth: 1,
  },
});

export default IconPickerModal;
