import { AiMindPickerScreen } from "@/components/AiMindPicker";

export default function AiCoachScreen() {
  return (
    <AiMindPickerScreen
      feature="coach"
      title="Growth Coach"
      subtitle="Pick which Knowledge Bases Growth Coach should reference when suggesting experiments. We'll remember your selection for next time."
      disabledFeature="Growth Coach"
    />
  );
}
