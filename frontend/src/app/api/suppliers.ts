import { api } from "@/app/lib/api";
import type { PaginatedResponse } from "./sales";
import type { Supplier } from "@/types/ims";

export const supplierApi = {
  // Get all suppliers (paginated)
  getAll: async (params?: { page?: number; per_page?: number; search?: string }) => {
    const queryParams = new URLSearchParams();
    if (params?.page) queryParams.append('page', String(params.page));
    if (params?.per_page) queryParams.append('per_page', String(params.per_page));
    if (params?.search) queryParams.append('search', params.search);

    const query = queryParams.toString();
    const response = await api.get(`/suppliers${query ? '?' + query : ''}`);
    return response.data as PaginatedResponse<Supplier>;
  },

  // Get single supplier
  getById: async (id: number) => {
    const response = await api.get(`/suppliers/${id}`);
    return response.data.data || response.data;
  },

  // Create supplier
  create: async (data: any) => {
    const response = await api.post('/suppliers', data);
    return response.data.data || response.data;
  },

  // Update supplier
  update: async (id: number, data: any) => {
    const response = await api.put(`/suppliers/${id}`, data);
    return response.data.data || response.data;
  },

  // Delete supplier
  delete: async (id: number) => {
    const response = await api.delete(`/suppliers/${id}`);
    return response.data;
  },

  // Toggle supplier active status
  toggleActive: async (id: number) => {
    const response = await api.patch(`/suppliers/${id}/toggle-active`);
    return response.data.data || response.data;
  },
};