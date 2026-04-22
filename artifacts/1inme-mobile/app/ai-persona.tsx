import { AiMindPickerScreen } from "@/components/AiMindPicker";

export default function AiPersonaScreen() {
  return (
    <AiMindPickerScreen
      feature="persona"
      title="Persona Minds"
      subtitle="Pick which Minds Persona should ground generations in. We'll remember your selection for next time."
    />
  );
}
