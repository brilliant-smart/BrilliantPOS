import { describe, it, expect, beforeEach, vi } from 'vitest';
import { supplierApi } from './suppliers';
import { api } from '@/app/lib/api';

vi.mock('@/app/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

describe('supplierApi', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('fetches all suppliers with pagination', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: [], current_page: 1 } });

    await supplierApi.getAll({ page: 2, per_page: 25 });

    const calledUrl = vi.mocked(api.get).mock.calls[0][0] as string;
    expect(calledUrl).toContain('page=2');
    expect(calledUrl).toContain('per_page=25');
  });

  it('fetches all suppliers with search', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: [] } });

    await supplierApi.getAll({ search: 'acme' });

    const calledUrl = vi.mocked(api.get).mock.calls[0][0] as string;
    expect(calledUrl).toContain('search=acme');
  });

  it('fetches a single supplier', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: { id: 1, name: 'Acme Corp' } } });

    const result = await supplierApi.getById(1);

    expect(api.get).toHaveBeenCalledWith('/suppliers/1');
    expect(result.name).toBe('Acme Corp');
  });

  it('creates a supplier', async () => {
    const data = { name: 'New Supplier', contact_person: 'John', phone: '08012345678' };
    vi.mocked(api.post).mockResolvedValue({ data: { data: { id: 2, ...data } } });

    await supplierApi.create(data);

    expect(api.post).toHaveBeenCalledWith('/suppliers', data);
  });

  it('updates a supplier', async () => {
    const data = { name: 'Updated Supplier' };
    vi.mocked(api.put).mockResolvedValue({ data: { data: { id: 1 } } });

    await supplierApi.update(1, data);

    expect(api.put).toHaveBeenCalledWith('/suppliers/1', data);
  });

  it('deletes a supplier', async () => {
    vi.mocked(api.delete).mockResolvedValue({ data: { message: 'Deleted' } });

    await supplierApi.delete(1);

    expect(api.delete).toHaveBeenCalledWith('/suppliers/1');
  });

  it('toggles supplier active status', async () => {
    vi.mocked(api.patch).mockResolvedValue({ data: { data: { id: 1, is_active: false } } });

    await supplierApi.toggleActive(1);

    expect(api.patch).toHaveBeenCalledWith('/suppliers/1/toggle-active');
  });
});