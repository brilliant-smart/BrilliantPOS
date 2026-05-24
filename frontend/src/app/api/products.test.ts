import { describe, it, expect, beforeEach, vi } from 'vitest';
import { getProducts, getProduct, createProduct, updateProduct, deleteProduct, searchByBarcode, searchProducts, productApi } from './products';
import { api } from '@/app/lib/api';

vi.mock('@/app/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
  },
}));

describe('products API', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ---- getProducts ----

  it('fetches product list with default limit', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: [] } });

    await getProducts();

    expect(api.get).toHaveBeenCalledWith('/admin/products', { params: { limit: 1000 } });
  });

  // ---- getProduct ----

  it('fetches a single product by ID', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: { id: 5, name: 'Widget' } } });

    await getProduct(5);

    expect(api.get).toHaveBeenCalledWith('/products/5');
  });

  // ---- createProduct ----

  it('creates a product with FormData', async () => {
    const formData = new FormData();
    formData.append('name', 'New Product');
    vi.mocked(api.post).mockResolvedValue({ data: { data: { id: 1, name: 'New Product' } } });

    await createProduct(formData);

    expect(api.post).toHaveBeenCalledWith('/products', formData);
  });

  // ---- updateProduct ----

  it('updates a product using POST with _method PUT override', async () => {
    const formData = new FormData();
    formData.append('name', 'Updated');
    vi.mocked(api.post).mockResolvedValue({ data: { data: { id: 1 } } });

    await updateProduct(1, formData);

    expect(formData.get('_method')).toBe('PUT');
    expect(api.post).toHaveBeenCalledWith('/admin/products/1', formData);
  });

  // ---- deleteProduct ----

  it('deletes a product by ID', async () => {
    vi.mocked(api.delete).mockResolvedValue({ data: { message: 'Deleted' } });

    await deleteProduct(3);

    expect(api.delete).toHaveBeenCalledWith('/admin/products/3');
  });

  // ---- searchByBarcode ----

  it('searches by barcode', async () => {
    vi.mocked(api.get).mockResolvedValue({
      data: { product: { id: 1, name: 'Scanned Item' }, matched_unit_type: null },
    });

    const result = await searchByBarcode('1234567890');

    expect(api.get).toHaveBeenCalledWith('/products/barcode/search', { params: { barcode: '1234567890' } });
    expect(result.product.name).toBe('Scanned Item');
  });

  // ---- searchProducts ----

  it('searches products with query and limit', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: [] });

    await searchProducts('widget', { signal: undefined });

    expect(api.get).toHaveBeenCalledWith('/products', {
      params: { search: 'widget', limit: 10 },
      signal: undefined,
    });
  });

  it('passes abort signal to search', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: [] });
    const controller = new AbortController();

    await searchProducts('test', { signal: controller.signal });

    expect(api.get).toHaveBeenCalledWith('/products', {
      params: { search: 'test', limit: 10 },
      signal: controller.signal,
    });
  });

  // ---- productApi alias object ----

  it('productApi.getAll delegates to getProducts', () => {
    expect(productApi.getAll).toBe(getProducts);
  });

  it('productApi.get delegates to getProduct', () => {
    expect(productApi.get).toBe(getProduct);
  });

  it('productApi.create delegates to createProduct', () => {
    expect(productApi.create).toBe(createProduct);
  });

  it('productApi.update delegates to updateProduct', () => {
    expect(productApi.update).toBe(updateProduct);
  });

  it('productApi.delete delegates to deleteProduct', () => {
    expect(productApi.delete).toBe(deleteProduct);
  });
});