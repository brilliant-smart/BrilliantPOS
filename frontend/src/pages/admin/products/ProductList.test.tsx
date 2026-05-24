import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import ProductList from './ProductList';

// Mock auth
vi.mock('@/app/auth/AuthContext', () => ({
  useAuth: () => ({ user: { id: 1, name: 'Owner', email: 'owner@store.com', role: 'owner' } }),
}));

// Mock toast
const mockToast = vi.fn();
vi.mock('@/hooks/use-toast', () => ({
  useToast: () => ({ toast: mockToast }),
}));

// Mock products API
vi.mock('@/app/api/products', () => ({
  getProducts: vi.fn(),
  createProduct: vi.fn(),
  updateProduct: vi.fn(),
  deleteProduct: vi.fn(),
  searchByBarcode: vi.fn(),
  searchProducts: vi.fn(),
}));

vi.mock('@/components/BarcodeScanner', () => ({
  default: () => <div data-testid="barcode-scanner">Scanner</div>,
}));

vi.mock('@/components/StockAdjustmentModal', () => ({
  StockAdjustmentModal: () => <div data-testid="stock-adjustment-modal">Adjust</div>,
}));

vi.mock('@/components/StockHistoryModal', () => ({
  StockHistoryModal: () => <div data-testid="stock-history-modal">History</div>,
}));

vi.mock('@/components/MonthYearPicker', () => ({
  MonthYearPicker: ({ value, onChange }: any) => (
    <input data-testid="month-year-picker" value={value || ''} onChange={() => {}} />
  ),
}));

vi.mock('@/components/DatePicker', () => ({
  DatePicker: ({ value, onChange }: any) => (
    <input data-testid="date-picker" value={value || ''} onChange={() => {}} />
  ),
}));

import { getProducts } from '@/app/api/products';

const mockProducts = {
  data: [
    {
      id: 1,
      name: 'Paracetamol 500mg',
      sku: 'SKU-001',
      price: 150,
      cost_price: 80,
      stock_quantity: 200,
      is_active: true,
      low_stock_threshold: 20,
      category: { name: 'Medicines' },
      unit_type: 'piece',
    },
    {
      id: 2,
      name: 'Cough Syrup',
      sku: 'SKU-002',
      price: 500,
      cost_price: 250,
      stock_quantity: 5,
      is_active: true,
      low_stock_threshold: 10,
      category: { name: 'Medicines' },
      unit_type: 'piece',
    },
    {
      id: 3,
      name: 'Discontinued Item',
      sku: 'SKU-003',
      price: 100,
      cost_price: 50,
      stock_quantity: 0,
      is_active: false,
      low_stock_threshold: 5,
      category: null,
      unit_type: 'piece',
    },
  ],
};

describe('ProductList', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockToast.mockClear();
    vi.mocked(getProducts).mockResolvedValue(mockProducts);
  });

  it('renders loading spinner initially', () => {
    vi.mocked(getProducts).mockReturnValue(new Promise(() => {}));
    render(<ProductList />);
    expect(document.querySelector('.animate-spin')).toBeInTheDocument();
  });

  it('loads and displays products', async () => {
    render(<ProductList />);

    await waitFor(() => {
      expect(screen.getByText('Paracetamol 500mg')).toBeInTheDocument();
    });
    expect(screen.getByText('Cough Syrup')).toBeInTheDocument();
  });

  it('shows product table after loading', async () => {
    render(<ProductList />);

    await waitFor(() => {
      expect(screen.getByText('Paracetamol 500mg')).toBeInTheDocument();
    });
    // Verify table is present with products
    expect(screen.getByRole('table')).toBeInTheDocument();
  });

  it('shows Add Product button', async () => {
    render(<ProductList />);

    await waitFor(() => {
      expect(screen.getByText('Paracetamol 500mg')).toBeInTheDocument();
    });

    expect(screen.getByRole('button', { name: /add product/i })).toBeInTheDocument();
  });

  it('shows search input', async () => {
    render(<ProductList />);

    await waitFor(() => {
      expect(screen.getByText('Paracetamol 500mg')).toBeInTheDocument();
    });

    expect(screen.getByPlaceholderText(/search/i)).toBeInTheDocument();
  });

  it('calls toast on load error', async () => {
    vi.mocked(getProducts).mockRejectedValue(new Error('Network error'));

    render(<ProductList />);

    await waitFor(() => {
      expect(mockToast).toHaveBeenCalledWith(
        expect.objectContaining({ variant: 'destructive' }),
      );
    });
  });

  it('shows page heading', async () => {
    render(<ProductList />);

    await waitFor(() => {
      expect(screen.getByText('Products')).toBeInTheDocument();
    });
  });
});