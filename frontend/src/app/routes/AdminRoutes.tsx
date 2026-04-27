import { Routes, Route } from "react-router-dom";
import ProtectedRoute from "./ProtectedRoute";
import AdminLayout from "@/app/layouts/AdminLayout";

import Dashboard from "@/pages/admin/Dashboard";
import ProductList from "@/pages/admin/products/ProductList";
import ProductCreate from "@/pages/admin/products/ProductCreate";
import ProductEdit from "@/pages/admin/products/ProductEdit";

import UserList from "@/pages/admin/users/UserList";
import UserCreate from "@/pages/admin/users/UserCreate";
import UserEdit from "@/pages/admin/users/UserEdit";

import InventoryAnalytics from "@/pages/admin/InventoryAnalytics";

import Profile from "@/pages/admin/Profile";

import SupplierList from "@/pages/admin/suppliers/SupplierList";
import SupplierCreate from "@/pages/admin/suppliers/SupplierCreate";
import SupplierEdit from "@/pages/admin/suppliers/SupplierEdit";
import PurchaseOrderList from "@/pages/admin/purchase-orders/PurchaseOrderList";
import PurchaseOrderCreate from "@/pages/admin/purchase-orders/PurchaseOrderCreate";
import PurchaseOrderEdit from "@/pages/admin/purchase-orders/PurchaseOrderEdit";
import PurchaseOrderDetail from "@/pages/admin/purchase-orders/PurchaseOrderDetail";
import BatchList from "@/pages/admin/batches/BatchList";
import SalesList from "@/pages/admin/sales/SalesList";
import SaleCreate from "@/pages/admin/sales/SaleCreate";
import SalesAnalytics from "@/pages/admin/sales/SalesAnalytics";
import POSTerminal from "@/pages/admin/pos/POSTerminal";
import FinancialReports from "@/pages/admin/reports/FinancialReports";
import PriceHistoryDashboard from "@/pages/admin/reports/PriceHistoryDashboard";
import SupplierPriceComparison from "@/pages/admin/reports/SupplierPriceComparison";
import BackupRestore from "@/pages/admin/system/BackupRestore";
import AuditLogs from "@/pages/admin/system/AuditLogs";
import Settings from "@/pages/admin/system/Settings";

import ExpenseList from "@/pages/admin/expenses/ExpenseList";
import ExpenseCreate from "@/pages/admin/expenses/ExpenseCreate";
import ExpenseEdit from "@/pages/admin/expenses/ExpenseEdit";
import ExpenseAnalytics from "@/pages/admin/expenses/ExpenseAnalytics";

import RoleProtectedRoute from "./RoleProtectedRoute";

export default function AdminRoutes() {
  return (
    <ProtectedRoute>
      <AdminLayout>
        <Routes>
          <Route
            path="dashboard"
            element={
              <RoleProtectedRoute allowedRoles={["owner", "manager", "cashier"]}>
                <Dashboard />
              </RoleProtectedRoute>
            }
          />

          <Route
            path="products"
            element={
              <RoleProtectedRoute allowedRoles={["owner", "manager"]}>
                <ProductList />
              </RoleProtectedRoute>
            }
          >
            <Route
              path="create"
              element={
                <RoleProtectedRoute allowedRoles={["owner", "manager"]}>
                  <ProductCreate />
                </RoleProtectedRoute>
              }
            />
            <Route
              path=":id/edit"
              element={
                <RoleProtectedRoute allowedRoles={["owner", "manager"]}>
                  <ProductEdit />
                </RoleProtectedRoute>
              }
            />
          </Route>

          <Route
            path="users"
            element={
              <RoleProtectedRoute allowedRoles={["owner"]}>
                <UserList />
              </RoleProtectedRoute>
            }
          >
            <Route
              path="create"
              element={
                <RoleProtectedRoute allowedRoles={["owner"]}>
                  <UserCreate />
                </RoleProtectedRoute>
              }
            />
            <Route
              path=":id/edit"
              element={
                <RoleProtectedRoute allowedRoles={["owner"]}>
                  <UserEdit />
                </RoleProtectedRoute>
              }
            />
          </Route>

          {/* Suppliers Routes */}
          <Route
            path="suppliers"
            element={
              <RoleProtectedRoute allowedRoles={["owner", "manager"]}>
                <SupplierList />
              </RoleProtectedRoute>
            }
          />
          <Route
            path="suppliers/create"
            element={
              <RoleProtectedRoute allowedRoles={["owner", "manager"]}>
                <SupplierCreate />
              </RoleProtectedRoute>
            }
          />
          <Route
            path="suppliers/:id/edit"
            element={
              <RoleProtectedRoute allowedRoles={["owner", "manager"]}>
                <SupplierEdit />
              </RoleProtectedRoute>
            }
          />

          {/* Purchase Orders Routes */}
          <Route
            path="purchase-orders"
            element={
              <RoleProtectedRoute allowedRoles={["owner", "manager"]}>
                <PurchaseOrderList />
              </RoleProtectedRoute>
            }
          />
          <Route
            path="purchase-orders/create"
            element={
              <RoleProtectedRoute allowedRoles={["owner", "manager"]}>
                <PurchaseOrderCreate />
              </RoleProtectedRoute>
            }
          />
          <Route
            path="purchase-orders/:id/edit"
            element={
              <RoleProtectedRoute allowedRoles={["owner", "manager"]}>
                <PurchaseOrderEdit />
              </RoleProtectedRoute>
            }
          />
          <Route
            path="purchase-orders/:id"
            element={
              <RoleProtectedRoute allowedRoles={["owner", "manager"]}>
                <PurchaseOrderDetail />
              </RoleProtectedRoute>
            }
          />

          {/* Batch Tracking Routes */}
          <Route
            path="batches"
            element={
              <RoleProtectedRoute allowedRoles={["owner", "manager"]}>
                <BatchList />
              </RoleProtectedRoute>
            }
          />

          {/* POS Terminal */}
          <Route
            path="pos"
            element={
              <RoleProtectedRoute allowedRoles={["owner", "manager", "cashier"]}>
                <POSTerminal />
              </RoleProtectedRoute>
            }
          />

          {/* Sales Routes */}
          <Route
            path="sales"
            element={
              <RoleProtectedRoute allowedRoles={["owner", "manager", "cashier"]}>
                <SalesList />
              </RoleProtectedRoute>
            }
          />
          <Route
            path="sales/create"
            element={
              <RoleProtectedRoute allowedRoles={["owner", "manager", "cashier"]}>
                <SaleCreate />
              </RoleProtectedRoute>
            }
          />
          <Route
            path="sales/analytics"
            element={
              <RoleProtectedRoute allowedRoles={["owner", "manager"]}>
                <SalesAnalytics />
              </RoleProtectedRoute>
            }
          />

          {/* Expenses Routes */}
          <Route
            path="expenses"
            element={
              <RoleProtectedRoute allowedRoles={["owner", "manager", "cashier"]}>
                <ExpenseList />
              </RoleProtectedRoute>
            }
          />
          <Route
            path="expenses/create"
            element={
              <RoleProtectedRoute allowedRoles={["owner", "manager", "cashier"]}>
                <ExpenseCreate />
              </RoleProtectedRoute>
            }
          />
          <Route
            path="expenses/:id/edit"
            element={
              <RoleProtectedRoute allowedRoles={["owner", "manager", "cashier"]}>
                <ExpenseEdit />
              </RoleProtectedRoute>
            }
          />
          <Route
            path="expenses/analytics"
            element={
              <RoleProtectedRoute allowedRoles={["owner", "manager"]}>
                <ExpenseAnalytics />
              </RoleProtectedRoute>
            }
          />

          {/* Financial Reports Route */}
          <Route
            path="reports"
            element={
              <RoleProtectedRoute allowedRoles={["owner", "manager"]}>
                <FinancialReports />
              </RoleProtectedRoute>
            }
          />
          <Route
            path="reports/price-history"
            element={
              <RoleProtectedRoute allowedRoles={["owner"]}>
                <PriceHistoryDashboard />
              </RoleProtectedRoute>
            }
          />
          <Route
            path="reports/supplier-comparison"
            element={
              <RoleProtectedRoute allowedRoles={["owner"]}>
                <SupplierPriceComparison />
              </RoleProtectedRoute>
            }
          />

          {/* Analytics Route */}
          <Route
            path="analytics"
            element={
              <RoleProtectedRoute allowedRoles={["owner", "manager"]}>
                <InventoryAnalytics />
              </RoleProtectedRoute>
            }
          />

          {/* Profile Route */}
          <Route path="profile" element={<Profile />} />

          {/* Backup & Restore */}
          <Route
            path="system/backups"
            element={
              <RoleProtectedRoute allowedRoles={["owner", "manager"]}>
                <BackupRestore />
              </RoleProtectedRoute>
            }
          />

          {/* Audit Logs (Owner Only) */}
          <Route
            path="system/audit-logs"
            element={
              <RoleProtectedRoute allowedRoles={["owner"]}>
                <AuditLogs />
              </RoleProtectedRoute>
            }
          />

          {/* Settings (Owner Only) */}
          <Route
            path="system/settings"
            element={
              <RoleProtectedRoute allowedRoles={["owner"]}>
                <Settings />
              </RoleProtectedRoute>
            }
          />
        </Routes>
      </AdminLayout>
    </ProtectedRoute>
  );
}