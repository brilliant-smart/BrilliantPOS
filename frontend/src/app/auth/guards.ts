import { User } from "./types";

export const isAuthenticated = (user: User | null) => !!user;

export const isOwner = (user: User | null) =>
  user?.role === "owner";

export const isManager = (user: User | null) =>
  user?.role === "manager";

export const isCashier = (user: User | null) =>
  user?.role === "cashier";