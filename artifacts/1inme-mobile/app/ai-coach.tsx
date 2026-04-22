import { AiMindPickerScreen } from "@/components/AiMindPicker";

export default function AiCoachScreen() {
  return (
    <AiMindPickerScreen
      feature="coach"
      title="Coach Minds"
      subtitle="Pick which Minds Coach should reference when suggesting experiments. We'll remember your selection for next time."
    />
  );
}
