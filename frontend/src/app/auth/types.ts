export type Role = "owner" | "manager" | "cashier";

export interface User {
  id: number;
  name: string;
  email: string;
  role: Role;
  avatar_url?: string;
}

export interface AuthState {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
}