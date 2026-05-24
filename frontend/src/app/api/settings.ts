import { api } from "@/app/lib/api";

export interface Settings {
  general: {
    store_name: string;
    store_phone: string;
    store_email: string;
    store_address: string;
    store_tagline: string;
    currency_symbol: string;
    vat_rate: number;
    credit_overdue_threshold_days: number;
    receipt_prompt_enabled: boolean;
  };
  receipt: {
    receipt_footer: string;
  };
}

export async function getSettings(): Promise<Settings> {
  const response = await api.get("/settings");
  return response.data;
}

export async function updateSettings(settings: { key: string; value: string | number | boolean }[]): Promise<Settings> {
  const response = await api.put("/settings", { settings });
  return response.data;
}