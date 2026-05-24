import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, act } from '@testing-library/react';
import { AuthProvider, useAuth } from './AuthContext';
import * as authService from './authService';

vi.mock('./authService', () => ({
  login: vi.fn(),
  logout: vi.fn(),
  me: vi.fn(),
}));

vi.mock('@/app/lib/api', () => ({
  api: {
    interceptors: {
      request: { use: vi.fn() },
      response: { use: vi.fn() },
    },
  },
}));

function TestConsumer() {
  const { user, isAuthenticated, loading, login, logout } = useAuth();
  return (
    <div>
      <span data-testid="loading">{String(loading)}</span>
      <span data-testid="authenticated">{String(isAuthenticated)}</span>
      <span data-testid="user-name">{user?.name ?? 'none'}</span>
      <button onClick={() => login('test@store.com', 'password')}>Login</button>
      <button onClick={logout}>Logout</button>
    </div>
  );
}

describe('AuthContext', () => {
  beforeEach(() => {
    localStorage.clear();
    vi.clearAllMocks();
  });

  it('starts unauthenticated with no token', async () => {
    vi.mocked(authService.me).mockRejectedValue(new Error('No token'));

    await act(async () => {
      render(
        <AuthProvider>
          <TestConsumer />
        </AuthProvider>,
      );
    });

    // After restoration attempt, loading should be done
    expect(screen.getByTestId('authenticated').textContent).toBe('false');
  });

  it('restores authenticated user from token', async () => {
    localStorage.setItem('brilliant_auth_token', 'valid-token');
    vi.mocked(authService.me).mockResolvedValue({
      id: 1,
      name: 'Admin User',
      email: 'admin@store.com',
      role: 'owner',
    });

    await act(async () => {
      render(
        <AuthProvider>
          <TestConsumer />
        </AuthProvider>,
      );
    });

    expect(screen.getByTestId('authenticated').textContent).toBe('true');
    expect(screen.getByTestId('user-name').textContent).toBe('Admin User');
  });

  it('clears auth on 401 from me()', async () => {
    localStorage.setItem('brilliant_auth_token', 'expired-token');
    const axiosError = Object.create(new Error('Unauthorized'));
    axiosError.isAxiosError = true;
    axiosError.response = { status: 401 };
    vi.mocked(authService.me).mockRejectedValue(axiosError);

    await act(async () => {
      render(
        <AuthProvider>
          <TestConsumer />
        </AuthProvider>,
      );
    });

    expect(screen.getByTestId('authenticated').textContent).toBe('false');
    expect(screen.getByTestId('user-name').textContent).toBe('none');
  });

  it('uses cached user on network failure', async () => {
    localStorage.setItem('brilliant_auth_token', 'valid-token');
    localStorage.setItem('brilliant_pos_user', JSON.stringify({
      id: 2,
      name: 'Cached User',
      email: 'cached@store.com',
      role: 'manager',
    }));
    vi.mocked(authService.me).mockRejectedValue(new Error('Network Error'));

    await act(async () => {
      render(
        <AuthProvider>
          <TestConsumer />
        </AuthProvider>,
      );
    });

    expect(screen.getByTestId('authenticated').textContent).toBe('true');
    expect(screen.getByTestId('user-name').textContent).toBe('Cached User');
  });

  it('login stores token and sets authenticated state', async () => {
    vi.mocked(authService.me).mockRejectedValue(new Error('No token'));
    vi.mocked(authService.login).mockResolvedValue({
      token: 'new-token',
      user: { id: 1, name: 'New User', email: 'new@store.com', role: 'owner' },
    });

    await act(async () => {
      render(
        <AuthProvider>
          <TestConsumer />
        </AuthProvider>,
      );
    });

    await act(async () => {
      screen.getByText('Login').click();
    });

    expect(localStorage.getItem('brilliant_auth_token')).toBe('new-token');
    expect(screen.getByTestId('authenticated').textContent).toBe('true');
    expect(screen.getByTestId('user-name').textContent).toBe('New User');
  });

  it('logout clears token and sets unauthenticated state', async () => {
    localStorage.setItem('brilliant_auth_token', 'valid-token');
    vi.mocked(authService.me).mockResolvedValue({
      id: 1,
      name: 'Admin',
      email: 'admin@store.com',
      role: 'owner',
    });
    vi.mocked(authService.logout).mockResolvedValue(true);

    await act(async () => {
      render(
        <AuthProvider>
          <TestConsumer />
        </AuthProvider>,
      );
    });

    expect(screen.getByTestId('authenticated').textContent).toBe('true');

    await act(async () => {
      screen.getByText('Logout').click();
    });

    expect(screen.getByTestId('authenticated').textContent).toBe('false');
    expect(localStorage.getItem('brilliant_auth_token')).toBeNull();
  });

  it('throws when useAuth is used outside AuthProvider', () => {
    // Suppress console.error for this test
    const spy = vi.spyOn(console, 'error').mockImplementation(() => {});

    expect(() => {
      render(<TestConsumer />);
    }).toThrow('useAuth must be used inside AuthProvider');

    spy.mockRestore();
  });
});