import { api } from "@/app/lib/api";

export const supplierApi = {
  // Get all suppliers
  getAll: async () => {
    const response = await api.get('/suppliers');
    return response.data.data || response.data;
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