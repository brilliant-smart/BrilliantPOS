import { api } from "@/app/lib/api";
import type { Product, ProductUnitType } from "@/types/ims";

export interface BarcodeSearchResult {
  product: Product;
  matched_unit_type?: ProductUnitType | null;
}

export const getProducts = () => {
  return api.get("/admin/products", {
    params: {
      limit: 1000,
    },
  });
};

export const getProduct = (id: number) => {
  return api.get(`/products/${id}`);
};

export const createProduct = (data: FormData) => {
  return api.post("/products", data);
};

export const updateProduct = (id: number, data: FormData) => {
  data.append('_method', 'PUT');
  return api.post(`/admin/products/${id}`, data);
};

export const deleteProduct = (id: number) => {
  return api.delete(`/admin/products/${id}`);
};

export const searchByBarcode = async (barcode: string): Promise<BarcodeSearchResult> => {
  const response = await api.get('/products/barcode/search', {
    params: { barcode }
  });
  return response.data;
};

export const searchProducts = async (query: string, opts?: { signal?: AbortSignal }) => {
  const response = await api.get('/products', {
    params: {
      search: query,
      limit: 10
    },
    signal: opts?.signal,
  });
  return response.data;
};

export const productApi = {
  getAll: getProducts,
  get: getProduct,
  create: createProduct,
  update: updateProduct,
  delete: deleteProduct,
  searchByBarcode: searchByBarcode,
  search: searchProducts,
};
