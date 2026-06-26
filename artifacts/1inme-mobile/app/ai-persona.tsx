import { AiMindPickerScreen } from "@/components/AiMindPicker";

export default function AiPersonaScreen() {
  return (
    <AiMindPickerScreen
      feature="persona"
      title="Persona Generator"
      subtitle="Pick which Knowledge Bases Persona Generator should ground generations in. We'll remember your selection for next time."
      disabledFeature="Persona Generator"
    />
  );
}
