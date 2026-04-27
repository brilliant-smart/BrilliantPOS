import { loginRequest, logoutRequest } from "@/app/api/auth";
import { User } from "./types";
import { api } from "@/app/lib/api";

interface LoginResponse {
  token: string;
  user: User;
}

//login
export async function login(email: string, password: string) {
  const response = await loginRequest(email, password);

  const data = response.data as LoginResponse;

  return {
    token: data.token,
    user: data.user,
  };
}

// logout
export async function logout(): Promise<boolean> {
  try {
    await logoutRequest();
  } catch {
    // backend logout may fail, frontend must still clear state
  }

  return true;
}

//Return authenticated user after refresh
export async function me(): Promise<User> {
  const res = await api.get("/me");
  return res.data;
}
