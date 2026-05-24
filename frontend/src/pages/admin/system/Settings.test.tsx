import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import Settings from './Settings';

vi.mock('@/app/api/settings', () => ({
  getSettings: vi.fn(),
  updateSettings: vi.fn(),
}));

vi.mock('sonner', () => ({
  toast: { success: vi.fn(), error: vi.fn() },
}));

import { getSettings, updateSettings } from '@/app/api/settings';

const mockSettings = {
  general: {
    store_name: 'Brilliant Store',
    store_phone: '08012345678',
    store_email: 'store@test.com',
    store_address: '123 Main St',
    store_tagline: 'Best prices',
    currency_symbol: '₦',
    vat_rate: 7.5,
    credit_overdue_threshold_days: 30,
    receipt_prompt_enabled: true,
  },
  receipt: { receipt_footer: 'Thank you!' },
};

describe('Settings', () => {
  beforeEach(() => {
    localStorage.clear();
    vi.clearAllMocks();
  });

  it('renders loading state initially', () => {
    vi.mocked(getSettings).mockReturnValue(new Promise(() => {}));
    render(<Settings />);
    expect(document.querySelector('.animate-spin')).toBeInTheDocument();
  });

  it('loads and displays settings', async () => {
    vi.mocked(getSettings).mockResolvedValue(mockSettings);

    render(<Settings />);

    await waitFor(() => {
      expect(screen.getByLabelText('Store Name')).toHaveValue('Brilliant Store');
    });
    expect(screen.getByLabelText('Phone')).toHaveValue('08012345678');
    expect(screen.getByLabelText('Email')).toHaveValue('store@test.com');
    expect(screen.getByLabelText('Currency Symbol')).toHaveValue('₦');
    // VAT Rate is a number input — toHaveValue checks string equality for number inputs
    const vatInput = screen.getByLabelText('VAT Rate (%)') as HTMLInputElement;
    expect(parseFloat(vatInput.value)).toBe(7.5);
    expect(screen.getByLabelText('Receipt Footer Message')).toHaveValue('Thank you!');
  });

  it('persists receipt_prompt_enabled to localStorage on load', async () => {
    vi.mocked(getSettings).mockResolvedValue(mockSettings);

    render(<Settings />);

    await waitFor(() => {
      expect(localStorage.getItem('brilliant_pos_receipt_prompt')).toBe('1');
    });
  });

  it('shows error toast when settings fail to load', async () => {
    vi.mocked(getSettings).mockRejectedValue(new Error('Network error'));
    const { toast } = await import('sonner');

    render(<Settings />);

    await waitFor(() => {
      expect(toast.error).toHaveBeenCalledWith('Failed to load settings');
    });
  });

  it('saves settings on form submission', async () => {
    vi.mocked(getSettings).mockResolvedValue(mockSettings);
    vi.mocked(updateSettings).mockResolvedValue(mockSettings);
    const { toast } = await import('sonner');

    render(<Settings />);

    await waitFor(() => {
      expect(screen.getByLabelText('Store Name')).toHaveValue('Brilliant Store');
    });

    fireEvent.click(screen.getByRole('button', { name: /save settings/i }));

    await waitFor(() => {
      expect(updateSettings).toHaveBeenCalled();
    });

    expect(toast.success).toHaveBeenCalledWith('Settings saved successfully');
    expect(localStorage.getItem('brilliant_pos_receipt_prompt')).toBe('1');
  });

  it('updates form field on input change', async () => {
    vi.mocked(getSettings).mockResolvedValue(mockSettings);

    render(<Settings />);

    await waitFor(() => {
      expect(screen.getByLabelText('Store Name')).toHaveValue('Brilliant Store');
    });

    const nameInput = screen.getByLabelText('Store Name');
    fireEvent.change(nameInput, { target: { value: 'New Store Name' } });

    expect(nameInput).toHaveValue('New Store Name');
  });

  it('sends correct key-value pairs on save', async () => {
    vi.mocked(getSettings).mockResolvedValue(mockSettings);
    vi.mocked(updateSettings).mockResolvedValue(mockSettings);

    render(<Settings />);

    await waitFor(() => {
      expect(screen.getByLabelText('Store Name')).toHaveValue('Brilliant Store');
    });

    fireEvent.click(screen.getByRole('button', { name: /save settings/i }));

    await waitFor(() => {
      expect(updateSettings).toHaveBeenCalledWith(
        expect.arrayContaining([
          expect.objectContaining({ key: 'store_name', value: 'Brilliant Store' }),
          expect.objectContaining({ key: 'vat_rate', value: '7.5' }),
          expect.objectContaining({ key: 'receipt_prompt_enabled', value: '1' }),
        ]),
      );
    });
  });

  it('shows error toast when save fails', async () => {
    vi.mocked(getSettings).mockResolvedValue(mockSettings);
    vi.mocked(updateSettings).mockRejectedValue(new Error('Save failed'));
    const { toast } = await import('sonner');

    render(<Settings />);

    await waitFor(() => {
      expect(screen.getByLabelText('Store Name')).toHaveValue('Brilliant Store');
    });

    fireEvent.click(screen.getByRole('button', { name: /save settings/i }));

    await waitFor(() => {
      expect(toast.error).toHaveBeenCalledWith('Failed to save settings');
    });
  });

  it('disables save button while saving', async () => {
    vi.mocked(getSettings).mockResolvedValue(mockSettings);
    vi.mocked(updateSettings).mockReturnValue(new Promise(() => {})); // never resolves

    render(<Settings />);

    await waitFor(() => {
      expect(screen.getByLabelText('Store Name')).toHaveValue('Brilliant Store');
    });

    fireEvent.click(screen.getByRole('button', { name: /save settings/i }));

    // After clicking, button should show "Saving..." and be disabled
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /saving/i })).toBeDisabled();
    });
  });
});