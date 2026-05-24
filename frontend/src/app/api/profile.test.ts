import { describe, it, expect, beforeEach, vi } from 'vitest';
import { getProfile, updateProfile, updatePassword } from './profile';
import { api } from '@/app/lib/api';

vi.mock('@/app/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
  },
}));

describe('profile API', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('fetches user profile', async () => {
    vi.mocked(api.get).mockResolvedValue({
      data: { id: 1, name: 'Admin', email: 'admin@store.com', role: 'owner' },
    });

    await getProfile();

    expect(api.get).toHaveBeenCalledWith('/profile');
  });

  it('updates profile with FormData', async () => {
    const formData = new FormData();
    formData.append('name', 'New Name');
    vi.mocked(api.post).mockResolvedValue({ data: { id: 1, name: 'New Name' } });

    await updateProfile(formData);

    expect(api.post).toHaveBeenCalledWith('/profile', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  });

  it('updates password', async () => {
    const data = {
      current_password: 'oldpass',
      password: 'newpass',
      password_confirmation: 'newpass',
    };
    vi.mocked(api.put).mockResolvedValue({ data: { message: 'Password updated' } });

    await updatePassword(data);

    expect(api.put).toHaveBeenCalledWith('/profile/password', data);
  });
});