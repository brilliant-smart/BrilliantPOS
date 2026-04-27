import { api } from "@/app/lib/api";

export const getProfile = () => {
  return api.get("/profile");
};

export const updateProfile = (data: FormData) => {
  return api.post("/profile", data, {
    headers: {
      "Content-Type": "multipart/form-data",
    },
  });
};

export const updatePassword = (data: {
  current_password: string;
  password: string;
  password_confirmation: string;
}) => {
  return api.put("/profile/password", data);
};
