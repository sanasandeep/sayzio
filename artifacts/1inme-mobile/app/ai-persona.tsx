import { AiMindPickerScreen } from "@/components/AiMindPicker";

export default function AiPersonaScreen() {
  return (
    <AiMindPickerScreen
      feature="persona"
      title="AI Persona Generator"
      subtitle="Pick which AI Minds AI Persona Generator should ground generations in. We'll remember your selection for next time."
      disabledFeature="AI Persona Generator"
    />
  );
}
