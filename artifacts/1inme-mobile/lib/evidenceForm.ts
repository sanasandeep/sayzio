export type EvidenceUpload = {
  uri: string;
  name: string;
  mimeType?: string | null;
};
export async function evidenceForm(
  values: Record<string, unknown>,
  files: EvidenceUpload[],
): Promise<FormData> {
  const form = new FormData();
  for (const [key, value] of Object.entries(values)) {
    if (value == null) continue;
    if (Array.isArray(value))
      value.forEach((v) => form.append(`${key}[]`, String(v)));
    else form.append(key, String(value));
  }
  for (const file of files) {
    if (file.uri.startsWith("blob:") || file.uri.startsWith("data:"))
      form.append(
        "proof_files[]",
        await (await fetch(file.uri)).blob(),
        file.name,
      );
    else
      form.append("proof_files[]", {
        uri: file.uri,
        name: file.name,
        type: file.mimeType ?? "application/octet-stream",
      } as unknown as Blob);
  }
  return form;
}
