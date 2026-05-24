import { describe, it, expect, beforeEach, vi } from 'vitest';
import { getUsers, createUser, updateUser, deleteUser, forceDeleteUser } from './users';
import { api } from '@/app/lib/api';

vi.mock('@/app/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

describe('users API', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('fetches user list', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: [{ id: 1, name: 'Admin' }] } });

    await getUsers();

    expect(api.get).toHaveBeenCalledWith('/admin/users');
  });

  it('creates a user with required fields', async () => {
    const data = { name: 'John', email: 'john@test.com', password: 'secret', role: 'cashier' as const };
    vi.mocked(api.post).mockResolvedValue({ data: { data: { id: 2, ...data } } });

    await createUser(data);

    expect(api.post).toHaveBeenCalledWith('/admin/users', data);
  });

  it('updates a user with PATCH', async () => {
    const data = { name: 'Updated', role: 'manager' as const };
    vi.mocked(api.patch).mockResolvedValue({ data: { data: { id: 1, ...data } } });

    await updateUser(1, data);

    expect(api.patch).toHaveBeenCalledWith('/admin/users/1', data);
  });

  it('updates user active status', async () => {
    vi.mocked(api.patch).mockResolvedValue({ data: { data: { id: 1, is_active: false } } });

    await updateUser(1, { is_active: false });

    expect(api.patch).toHaveBeenCalledWith('/admin/users/1', { is_active: false });
  });

  it('soft-deletes a user', async () => {
    vi.mocked(api.delete).mockResolvedValue({ data: { message: 'User deleted' } });

    await deleteUser(1);

    expect(api.delete).toHaveBeenCalledWith('/admin/users/1');
  });

  it('force-deletes a user', async () => {
    vi.mocked(api.delete).mockResolvedValue({ data: { message: 'User force deleted' } });

    await forceDeleteUser(1);

    expect(api.delete).toHaveBeenCalledWith('/admin/users/1/force');
  });
});