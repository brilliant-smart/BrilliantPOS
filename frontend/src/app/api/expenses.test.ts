import { describe, it, expect, beforeEach, vi } from 'vitest';
import {
  fetchExpenses,
  fetchExpense,
  createExpense,
  updateExpense,
  deleteExpense,
  fetchExpenseAnalytics,
  fetchExpenseCategories,
  createExpenseCategory,
} from './expenses';
import { api } from '@/app/lib/api';

vi.mock('@/app/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}));

describe('expenses API', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ---- fetchExpenses ----

  it('fetches expenses without filters', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: [], current_page: 1 } });

    await fetchExpenses();

    expect(api.get).toHaveBeenCalledWith('/expenses');
  });

  it('fetches expenses with all filters', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: [], current_page: 1 } });

    await fetchExpenses({
      search: 'rent',
      start_date: '2026-01-01',
      end_date: '2026-01-31',
      category_id: 3,
      payment_method: 'cash',
      per_page: 25,
      page: 2,
    });

    const calledUrl = vi.mocked(api.get).mock.calls[0][0] as string;
    expect(calledUrl).toContain('search=rent');
    expect(calledUrl).toContain('start_date=2026-01-01');
    expect(calledUrl).toContain('end_date=2026-01-31');
    expect(calledUrl).toContain('category_id=3');
    expect(calledUrl).toContain('payment_method=cash');
    expect(calledUrl).toContain('per_page=25');
    expect(calledUrl).toContain('page=2');
  });

  it('fetches a single expense by ID', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { id: 5, title: 'Office Supplies' } });

    await fetchExpense(5);

    expect(api.get).toHaveBeenCalledWith('/expenses/5');
  });

  // ---- createExpense ----

  it('creates an expense', async () => {
    const data = {
      title: 'Rent',
      amount: 50000,
      payment_method: 'bank_transfer' as const,
      expense_date: '2026-01-15',
    };
    vi.mocked(api.post).mockResolvedValue({ data: { message: 'Created', expense: { id: 1 } } });

    const result = await createExpense(data);

    expect(api.post).toHaveBeenCalledWith('/expenses', data);
    expect(result.expense.id).toBe(1);
  });

  // ---- updateExpense ----

  it('updates an expense', async () => {
    const data = { amount: 55000 };
    vi.mocked(api.put).mockResolvedValue({ data: { message: 'Updated', expense: { id: 1 } } });

    await updateExpense(1, data);

    expect(api.put).toHaveBeenCalledWith('/expenses/1', data);
  });

  // ---- deleteExpense ----

  it('deletes an expense', async () => {
    vi.mocked(api.delete).mockResolvedValue({ data: { message: 'Deleted' } });

    await deleteExpense(1);

    expect(api.delete).toHaveBeenCalledWith('/expenses/1');
  });

  // ---- fetchExpenseAnalytics ----

  it('fetches analytics with default period', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { summary: { total_expenses: 10 } } });

    await fetchExpenseAnalytics();

    const calledUrl = vi.mocked(api.get).mock.calls[0][0] as string;
    expect(calledUrl).toContain('period=this_month');
  });

  it('fetches analytics with custom date range', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { summary: { total_expenses: 5 } } });

    await fetchExpenseAnalytics('custom', '2026-01-01', '2026-01-31');

    const calledUrl = vi.mocked(api.get).mock.calls[0][0] as string;
    expect(calledUrl).toContain('period=custom');
    expect(calledUrl).toContain('start_date=2026-01-01');
    expect(calledUrl).toContain('end_date=2026-01-31');
  });

  // ---- fetchExpenseCategories ----

  it('fetches all categories by default', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: [{ id: 1, name: 'Rent' }] });

    await fetchExpenseCategories();

    expect(api.get).toHaveBeenCalledWith('/expense-categories');
  });

  it('fetches active-only categories', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: [{ id: 1, name: 'Rent', is_active: true }] });

    await fetchExpenseCategories(true);

    expect(api.get).toHaveBeenCalledWith('/expense-categories?active_only=true');
  });

  // ---- createExpenseCategory ----

  it('creates an expense category', async () => {
    const data = { name: 'Utilities', description: 'Utility bills', color: '#3B82F6' };
    vi.mocked(api.post).mockResolvedValue({ data: { message: 'Created', category: { id: 2 } } });

    await createExpenseCategory(data);

    expect(api.post).toHaveBeenCalledWith('/expense-categories', data);
  });
});