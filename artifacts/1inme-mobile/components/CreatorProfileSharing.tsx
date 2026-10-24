import { useState } from "react";
import { Linking, Modal, Share, Text, View } from "react-native";
import * as Clipboard from "expo-clipboard";
import QRCode from "react-native-qrcode-svg";
import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import { getBaseUrl } from "@/lib/api";
import { showAlert } from "@/lib/webAlert";

export function CreatorProfileSharing({ handle }: { handle: string }) {
  const colors = useColors();
  const [qr, setQr] = useState(false);
  const url = `${getBaseUrl()}/@${encodeURIComponent(handle)}`;
  function open(value: string) {
    void Linking.openURL(value).catch(() =>
      showAlert("Could not open", "Try sharing the profile link instead."),
    );
  }
  return (
    <View style={{ gap: 8, padding: 16 }}>
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
        <Button
          label="Share"
          variant="secondary"
          onPress={() => {
            void Share.share({ message: url, url }).catch(() => {});
          }}
        />
        <Button
          label="Copy profile link"
          variant="secondary"
          onPress={() => {
            void Clipboard.setStringAsync(url).then(() =>
              showAlert("Copied", "Profile link copied."),
            );
          }}
        />
        <Button
          label="Save contact"
          variant="secondary"
          onPress={() => open(`${url}/contact.vcf`)}
        />
        <Button
          label="WhatsApp"
          variant="secondary"
          onPress={() => open(`https://wa.me/?text=${encodeURIComponent(url)}`)}
        />
        <Button
          label="Telegram"
          variant="secondary"
          onPress={() =>
            open(`https://t.me/share/url?url=${encodeURIComponent(url)}`)
          }
        />
        <Button
          label="Message"
          variant="secondary"
          onPress={() => open(`sms:?body=${encodeURIComponent(url)}`)}
        />
        <Button
          label="QR code"
          variant="secondary"
          onPress={() => setQr(true)}
        />
      </View>
      <Modal visible={qr} transparent onRequestClose={() => setQr(false)}>
        <View
          style={{
            flex: 1,
            backgroundColor: "#0008",
            alignItems: "center",
            justifyContent: "center",
            padding: 24,
          }}
        >
          <View
            style={{
              padding: 24,
              gap: 20,
              alignItems: "center",
              backgroundColor: colors.card,
              borderRadius: colors.radius,
            }}
          >
            <Text style={{ color: colors.foreground }}>@{handle}</Text>
            <View style={{ padding: 16, backgroundColor: "white" }}>
              <QRCode value={url} size={200} />
            </View>
            <Button label="Close QR code" onPress={() => setQr(false)} />
          </View>
        </View>
      </Modal>
    </View>
  );
}
