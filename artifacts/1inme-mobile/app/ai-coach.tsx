import { AiMindPickerScreen } from "@/components/AiMindPicker";

export default function AiCoachScreen() {
  return (
    <AiMindPickerScreen
      feature="coach"
      title="AI Growth Coach"
      subtitle="Pick which AI Knowledge Bases AI Growth Coach should reference when suggesting experiments. We'll remember your selection for next time."
      disabledFeature="AI Growth Coach"
    />
  );
}
