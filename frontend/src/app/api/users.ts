import { api } from "@/app/lib/api";

export const getUsers = () => {
  return api.get("/admin/users");
};

export const createUser = (data: {
  name: string;
  email: string;
  password: string;
  role: "owner" | "manager" | "cashier";
}) => {
  return api.post("/admin/users", data);
};

export const updateUser = (
  id: number,
  data: {
    name?: string;
    email?: string;
    password?: string;
    role?: "owner" | "manager" | "cashier";
    is_active?: boolean;
  }
) => {
  return api.patch(`/admin/users/${id}`, data);
};

export const deleteUser = (id: number) => {
  return api.delete(`/admin/users/${id}`);
};

export const forceDeleteUser = (id: number) => {
  return api.delete(`/admin/users/${id}/force`);
};
