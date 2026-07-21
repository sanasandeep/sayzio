import { AiMindPickerScreen } from "@/components/AiMindPicker";

export default function AiCoachScreen() {
  return (
    <AiMindPickerScreen
      feature="coach"
      title="AI Link Optimizer"
      subtitle="Pick which AI Minds AI Link Optimizer should reference when suggesting experiments. We'll remember your selection for next time."
      disabledFeature="AI Link Optimizer"
    />
  );
}
