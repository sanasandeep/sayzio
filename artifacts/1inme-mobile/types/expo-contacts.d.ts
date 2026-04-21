declare module "expo-contacts" {
  export const Fields: {
    FirstName: string;
    LastName: string;
    Name: string;
    Company: string;
    Emails: string;
    PhoneNumbers: string;
    [key: string]: string;
  };
  export function requestPermissionsAsync(): Promise<{ status: string }>;
  export function getContactsAsync(opts?: {
    fields?: string[];
    pageSize?: number;
  }): Promise<{ data: any[] }>;
}
