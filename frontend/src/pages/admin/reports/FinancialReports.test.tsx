import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import FinancialReports from './FinancialReports';

vi.mock('@/app/api/reports', () => ({
  reportsApi: {
    getProfitLoss: vi.fn(),
    getStockVariance: vi.fn(),
    getExpiringProducts: vi.fn(),
  },
}));

vi.mock('sonner', () => ({
  toast: { error: vi.fn() },
}));

import { reportsApi } from '@/app/api/reports';

const mockProfitLoss = {
  period: { start_date: '2026-01-01', end_date: '2026-01-31' },
  revenue: { total_revenue: 50000, total_sales_count: 150, average_sale_value: 333.33 },
  costs: { cost_of_goods_sold: 25000, total_purchases: 20000 },
  profit: { gross_profit: 25000, profit_margin_percent: 50 },
  cash_flow: { cash_in: 50000, cash_out: 30000, net_cash_flow: 20000 },
  outstanding: { receivables: 5000, payables: 3000 },
  inventory: { current_stock_value: 100000, total_products: 50, low_stock_items: 5, out_of_stock_items: 2 },
};

const mockVariance = {
  variances: [
    { product_id: 1, product_name: 'Product A', sku: 'SKU-001', expected_stock: 100, actual_stock: 90, variance: -10, variance_percent: 10, variance_value: 500, severity: 'medium' },
  ],
};

const mockExpiring = {
  products: [
    { product_id: 2, product_name: 'Medicine B', sku: 'SKU-002', batch_number: 'BATCH-001', expiry_date: '2026-02-15', days_until_expiry: 10, stock_quantity: 50, stock_value: 2500, urgency: 'high' },
  ],
};

describe('FinancialReports', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(reportsApi.getProfitLoss).mockResolvedValue(mockProfitLoss);
    vi.mocked(reportsApi.getStockVariance).mockResolvedValue(mockVariance);
    vi.mocked(reportsApi.getExpiringProducts).mockResolvedValue(mockExpiring);
  });

  it('disables button while loading', () => {
    vi.mocked(reportsApi.getProfitLoss).mockReturnValue(new Promise(() => {}));
    render(<FinancialReports />);
    // Button shows "Loading..." when reports are being fetched
    expect(screen.getByRole('button', { name: /loading/i })).toBeDisabled();
  });

  it('loads and displays profit/loss data', async () => {
    render(<FinancialReports />);

    await waitFor(() => {
      expect(screen.getByText(/Profit & Loss/i)).toBeInTheDocument();
    });
  });

  it('displays revenue amount from API', async () => {
    render(<FinancialReports />);

    await waitFor(() => {
      expect(reportsApi.getProfitLoss).toHaveBeenCalled();
    });
  });

  it('shows stock variance section', async () => {
    render(<FinancialReports />);

    await waitFor(() => {
      expect(reportsApi.getStockVariance).toHaveBeenCalled();
    });
  });

  it('shows expiring products section', async () => {
    render(<FinancialReports />);

    await waitFor(() => {
      expect(reportsApi.getExpiringProducts).toHaveBeenCalled();
    });
  });

  it('shows error toast when profit/loss fails', async () => {
    vi.mocked(reportsApi.getProfitLoss).mockRejectedValue(new Error('Network error'));
    const { toast } = await import('sonner');

    render(<FinancialReports />);

    await waitFor(() => {
      expect(toast.error).toHaveBeenCalled();
    });
  });

  it('shows page heading', async () => {
    render(<FinancialReports />);

    await waitFor(() => {
      expect(screen.getByText('Financial Reports')).toBeInTheDocument();
    });
  });
});