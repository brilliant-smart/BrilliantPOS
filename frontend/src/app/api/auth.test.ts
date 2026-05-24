import { describe, it, expect, beforeEach, vi } from 'vitest';
import { loginRequest, logoutRequest } from './auth';
import { api } from '@/app/lib/api';

vi.mock('@/app/lib/api', () => ({
  api: {
    post: vi.fn(),
  },
}));

describe('auth API', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('sends login request with email and password', async () => {
    vi.mocked(api.post).mockResolvedValue({
      data: { token: 'abc123', user: { id: 1, name: 'Admin', role: 'owner' } },
    });

    await loginRequest('admin@store.com', 'password');

    expect(api.post).toHaveBeenCalledWith('/login', {
      email: 'admin@store.com',
      password: 'password',
    });
  });

  it('sends logout request', async () => {
    vi.mocked(api.post).mockResolvedValue({ data: { message: 'Logged out' } });

    await logoutRequest();

    expect(api.post).toHaveBeenCalledWith('/logout');
  });
});