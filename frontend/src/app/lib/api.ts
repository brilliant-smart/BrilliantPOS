import axios from "axios";
import { tokenStorage } from "@/app/auth/token";

//create axios
export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL,
  timeout: 30000,
  headers: {
    Accept: "application/json",
  },
});

//Request Interceptor (attach token to every request)
api.interceptors.request.use((config) => {
  const token = tokenStorage.get();

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

//Response Interceptor (i.e Error Normalization)
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // token expired or invalid
      tokenStorage.clear();
      window.location.href = "/login";
    }

    return Promise.reject(error);
  },
);
