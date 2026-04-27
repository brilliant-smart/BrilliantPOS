import { api } from '../lib/api';

export interface ExpenseCategory {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  icon: string | null;
  color: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
  expenses_count?: number;
}

export interface Expense {
  id: number;
  expense_number: string;
  title: string;
  description: string | null;
  amount: string;
  payment_method: 'cash' | 'bank_transfer' | 'pos_terminal' | 'personal_payment' | 'shop_account' | 'other';
  category_id: number | null;
  recorded_by: number;
  expense_date: string;
  vendor: string | null;
  receipt_number: string | null;
  notes: string | null;
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
  category?: ExpenseCategory;
  recorder?: {
    id: number;
    name: string;
    email: string;
  };
}

export interface ExpensePaginatedResponse {
  data: Expense[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

export interface ExpenseAnalytics {
  summary: {
    total_expenses: number;
    total_amount: number;
    average_expense: number;
  };
  payment_breakdown: Array<{
    method: string;
    count: number;
    amount: number;
  }>;
  category_breakdown: Array<{
    category_id: number | null;
    category_name: string;
    category_color: string;
    count: number;
    amount: number;
  }>;
  top_vendors: Array<{
    vendor: string;
    count: number;
    amount: number;
  }>;
  daily_trend: Array<{
    date: string;
    count: number;
    amount: number;
  }>;
  period: {
    start: string;
    end: string;
    type: string;
  };
}

export interface ExpenseFilters {
  search?: string;
  start_date?: string;
  end_date?: string;
  category_id?: number;
  payment_method?: string;
  per_page?: number;
  page?: number;
}

export interface CreateExpenseData {
  title: string;
  description?: string;
  amount: number;
  payment_method: 'cash' | 'bank_transfer' | 'pos_terminal' | 'personal_payment' | 'shop_account' | 'other';
  category_id?: number;
  expense_date: string;
  vendor?: string;
  receipt_number?: string;
  notes?: string;
}

export interface UpdateExpenseData extends Partial<CreateExpenseData> {}

// Expense API functions
export const fetchExpenses = async (filters?: ExpenseFilters): Promise<ExpensePaginatedResponse> => {
  const params = new URLSearchParams();
  
  if (filters?.search) params.append('search', filters.search);
  if (filters?.start_date) params.append('start_date', filters.start_date);
  if (filters?.end_date) params.append('end_date', filters.end_date);
  if (filters?.category_id) params.append('category_id', filters.category_id.toString());
  if (filters?.payment_method) params.append('payment_method', filters.payment_method);
  if (filters?.per_page) params.append('per_page', filters.per_page.toString());
  if (filters?.page) params.append('page', filters.page.toString());
  
  const queryString = params.toString();
  const url = queryString ? `/expenses?${queryString}` : '/expenses';
  
  const response = await api.get(url);
  return response.data;
};

export const fetchExpense = async (id: number): Promise<Expense> => {
  const response = await api.get(`/expenses/${id}`);
  return response.data;
};

export const createExpense = async (data: CreateExpenseData): Promise<{ message: string; expense: Expense }> => {
  const response = await api.post('/expenses', data);
  return response.data;
};

export const updateExpense = async (id: number, data: UpdateExpenseData): Promise<{ message: string; expense: Expense }> => {
  const response = await api.put(`/expenses/${id}`, data);
  return response.data;
};

export const deleteExpense = async (id: number): Promise<{ message: string }> => {
  const response = await api.delete(`/expenses/${id}`);
  return response.data;
};

export const fetchExpenseAnalytics = async (
  period: 'today' | 'yesterday' | 'this_week' | 'last_week' | 'this_month' | 'last_month' | 'custom' = 'this_month',
  startDate?: string,
  endDate?: string
): Promise<ExpenseAnalytics> => {
  const params = new URLSearchParams();
  params.append('period', period);
  
  if (period === 'custom' && startDate && endDate) {
    params.append('start_date', startDate);
    params.append('end_date', endDate);
  }
  
  const response = await api.get(`/expenses/analytics?${params.toString()}`);
  return response.data;
};

// Expense Category API functions
export const fetchExpenseCategories = async (activeOnly = false): Promise<ExpenseCategory[]> => {
  const params = activeOnly ? '?active_only=true' : '';
  const response = await api.get(`/expense-categories${params}`);
  return response.data;
};

export const fetchExpenseCategory = async (id: number): Promise<ExpenseCategory> => {
  const response = await api.get(`/expense-categories/${id}`);
  return response.data;
};

export const createExpenseCategory = async (data: {
  name: string;
  description?: string;
  icon?: string;
  color?: string;
}): Promise<{ message: string; category: ExpenseCategory }> => {
  const response = await api.post('/expense-categories', data);
  return response.data;
};

export const updateExpenseCategory = async (
  id: number,
  data: Partial<{
    name: string;
    description: string;
    icon: string;
    color: string;
    is_active: boolean;
  }>
): Promise<{ message: string; category: ExpenseCategory }> => {
  const response = await api.put(`/expense-categories/${id}`, data);
  return response.data;
};

export const deleteExpenseCategory = async (id: number): Promise<{ message: string }> => {
  const response = await api.delete(`/expense-categories/${id}`);
  return response.data;
};
