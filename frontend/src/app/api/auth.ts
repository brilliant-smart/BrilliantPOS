import { api } from "@/app/lib/api";

export const loginRequest = (email: string, password: string) => {
  return api.post("/login", { email, password });
};

export const logoutRequest = () => {
  return api.post("/logout");
};
