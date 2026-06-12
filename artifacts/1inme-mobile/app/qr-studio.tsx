import { WebFeatureRedirect } from "@/components/WebFeatureRedirect";

export default function QrStudioScreen() {
  return (
    <WebFeatureRedirect
      title="QR studio"
      iconName="grid"
      blurb="Advanced QR code styles, frames and content types beyond the simple generator."
      webPath="/user/qr-codes/create"
      features={[
        "Wi-Fi, vCard, calendar, geolocation and more",
        "Custom colors, logo and corner styles",
        "Download print-ready PNG or SVG",
      ]}
    />
  );
}
