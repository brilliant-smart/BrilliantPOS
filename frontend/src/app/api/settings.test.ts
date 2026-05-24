import { describe, it, expect, beforeEach, vi } from 'vitest';
import { getSettings, updateSettings } from './settings';
import type { Settings } from './settings';
import { api } from '@/app/lib/api';

vi.mock('@/app/lib/api', () => ({
  api: {
    get: vi.fn(),
    put: vi.fn(),
  },
}));

describe('settings API', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('fetches settings', async () => {
    const mockSettings: Settings = {
      general: {
        store_name: 'Test Store',
        store_phone: '08012345678',
        store_email: 'test@store.com',
        store_address: '123 Main St',
        store_tagline: 'Best prices',
        currency_symbol: '₦',
        vat_rate: 7.5,
        credit_overdue_threshold_days: 30,
        receipt_prompt_enabled: true,
      },
      receipt: { receipt_footer: 'Thank you!' },
    };
    vi.mocked(api.get).mockResolvedValue({ data: mockSettings });

    const result = await getSettings();

    expect(api.get).toHaveBeenCalledWith('/settings');
    expect(result.general.store_name).toBe('Test Store');
    expect(result.general.vat_rate).toBe(7.5);
    expect(result.receipt.receipt_footer).toBe('Thank you!');
  });

  it('updates settings with key-value pairs', async () => {
    const updates = [
      { key: 'store_name', value: 'New Name' },
      { key: 'vat_rate', value: 10 },
    ];
    vi.mocked(api.put).mockResolvedValue({ data: { general: { store_name: 'New Name', vat_rate: 10 } } });

    await updateSettings(updates);

    expect(api.put).toHaveBeenCalledWith('/settings', { settings: updates });
  });

  it('preserves boolean values in settings update', async () => {
    const updates = [
      { key: 'receipt_prompt_enabled', value: true },
    ];
    vi.mocked(api.put).mockResolvedValue({ data: {} });

    await updateSettings(updates);

    expect(api.put).toHaveBeenCalledWith('/settings', { settings: updates });
    expect(updates[0].value).toBe(true);
  });
});