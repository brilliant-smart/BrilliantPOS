import { Routes, Route, Navigate } from "react-router-dom";

import AdminRoutes from "./AdminRoutes";

import Login from "@/pages/auth/Login";
import NotFound from "@/pages/errors/NotFound";
import Unauthorized from "@/app/pages/Unauthorized";

export default function AppRoutes() {
  return (
    <Routes>
      {/* Redirect root to admin dashboard */}
      <Route path="/" element={<Navigate to="/admin/dashboard" replace />} />

      {/* Auth */}
      <Route path="/login" element={<Login />} />

      {/* Admin */}
      <Route path="/admin/*" element={<AdminRoutes />} />

      {/* System */}
      <Route path="/unauthorized" element={<Unauthorized />} />
      <Route path="*" element={<NotFound />} />
    </Routes>
  );
}