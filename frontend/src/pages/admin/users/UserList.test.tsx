import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import UserList from './UserList';

// Mock auth
vi.mock('@/app/auth/AuthContext', () => ({
  useAuth: () => ({ user: { id: 1, name: 'Admin', email: 'admin@store.com', role: 'owner' } }),
}));

// Mock toast
const mockToast = vi.fn();
vi.mock('@/hooks/use-toast', () => ({
  useToast: () => ({ toast: mockToast }),
}));

// Mock users API
vi.mock('@/app/api/users', () => ({
  getUsers: vi.fn(),
  createUser: vi.fn(),
  updateUser: vi.fn(),
  deleteUser: vi.fn(),
  forceDeleteUser: vi.fn(),
}));

import { getUsers, createUser, updateUser } from '@/app/api/users';

const mockUsers = {
  data: [
    { id: 1, name: 'Admin User', email: 'admin@store.com', role: 'owner', is_active: true },
    { id: 2, name: 'Manager Jane', email: 'jane@store.com', role: 'manager', is_active: true },
    { id: 3, name: 'Cashier Bob', email: 'bob@store.com', role: 'cashier', is_active: false },
  ],
};

describe('UserList', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockToast.mockClear();
    vi.mocked(getUsers).mockResolvedValue(mockUsers);
  });

  it('renders loading spinner initially', () => {
    vi.mocked(getUsers).mockReturnValue(new Promise(() => {}));
    render(<UserList />);
    expect(document.querySelector('.animate-spin')).toBeInTheDocument();
  });

  it('loads and displays user list', async () => {
    render(<UserList />);

    await waitFor(() => {
      expect(screen.getByText('Admin User')).toBeInTheDocument();
    });
    expect(screen.getByText('Manager Jane')).toBeInTheDocument();
    expect(screen.getByText('Cashier Bob')).toBeInTheDocument();
  });

  it('shows user emails', async () => {
    render(<UserList />);

    await waitFor(() => {
      expect(screen.getByText('admin@store.com')).toBeInTheDocument();
    });
  });

  it('shows role badges', async () => {
    render(<UserList />);

    await waitFor(() => {
      expect(screen.getByText('Owner')).toBeInTheDocument();
    });
    expect(screen.getByText('Manager')).toBeInTheDocument();
    expect(screen.getByText('Cashier')).toBeInTheDocument();
  });

  it('shows Add User button', async () => {
    render(<UserList />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /add user/i })).toBeInTheDocument();
    });
  });

  it('opens create dialog on Add User click', async () => {
    render(<UserList />);

    await waitFor(() => {
      expect(screen.getByText('Admin User')).toBeInTheDocument();
    });

    fireEvent.click(screen.getByRole('button', { name: /add user/i }));

    // Dialog should open with form fields
    await waitFor(() => {
      expect(screen.getByLabelText(/name/i)).toBeInTheDocument();
    });
  });

  it('creates a new user', async () => {
    vi.mocked(createUser).mockResolvedValue({ data: { id: 4, name: 'New User' } });

    render(<UserList />);

    await waitFor(() => {
      expect(screen.getByText('Admin User')).toBeInTheDocument();
    });

    fireEvent.click(screen.getByRole('button', { name: /add user/i }));

    await waitFor(() => {
      expect(screen.getByLabelText(/name/i)).toBeInTheDocument();
    });

    fireEvent.change(screen.getByLabelText(/name/i), { target: { value: 'New User' } });
    fireEvent.change(screen.getByLabelText(/email/i), { target: { value: 'new@store.com' } });

    // Submit form
    const form = screen.getByLabelText(/name/i).closest('form');
    if (form) {
      fireEvent.submit(form);
    }

    await waitFor(() => {
      expect(createUser).toHaveBeenCalled();
    });
  });

  it('calls toast on load error', async () => {
    vi.mocked(getUsers).mockRejectedValue(new Error('Network error'));

    render(<UserList />);

    await waitFor(() => {
      expect(mockToast).toHaveBeenCalledWith(
        expect.objectContaining({ variant: 'destructive' }),
      );
    });
  });

  it('shows page heading', async () => {
    render(<UserList />);

    await waitFor(() => {
      expect(screen.getByText('Users')).toBeInTheDocument();
    });
    expect(screen.getByText('Manage admin users and staff')).toBeInTheDocument();
  });
});