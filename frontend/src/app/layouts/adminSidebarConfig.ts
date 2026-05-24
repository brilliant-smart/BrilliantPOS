import type { Role } from "@/app/auth/types";

export interface SidebarItem {
  label: string;
  path: string;
  roles: Role[];
  badge?: {
    fetchUrl: string;
  };
}

export const sidebarItems: SidebarItem[] = [
  {
    label: "Dashboard",
    path: "/admin/dashboard",
    roles: ["owner", "manager", "cashier"],
  },
  {
    label: "POS Terminal",
    path: "/admin/pos",
    roles: ["owner", "manager", "cashier"],
  },
  {
    label: "Products",
    path: "/admin/products",
    roles: ["owner", "manager"],
  },
  {
    label: "Sales",
    path: "/admin/sales",
    roles: ["owner", "manager", "cashier"],
  },
  {
    label: "Credit",
    path: "/admin/credit",
    roles: ["owner", "manager"],
    badge: {
      fetchUrl: "/sales/overdue-count",
    },
  },
  {
    label: "Expenses",
    path: "/admin/expenses",
    roles: ["owner", "manager", "cashier"],
  },
  {
    label: "Suppliers",
    path: "/admin/suppliers",
    roles: ["owner", "manager"],
  },
  {
    label: "Purchase Orders",
    path: "/admin/purchase-orders",
    roles: ["owner", "manager"],
  },
  {
    label: "Batch Tracking",
    path: "/admin/batches",
    roles: ["owner", "manager"],
  },
  {
    label: "Financial Reports",
    path: "/admin/reports",
    roles: ["owner", "manager"],
  },
  {
    label: "Price History",
    path: "/admin/reports/price-history",
    roles: ["owner"],
  },
  {
    label: "Supplier Comparison",
    path: "/admin/reports/supplier-comparison",
    roles: ["owner"],
  },
  {
    label: "Analytics",
    path: "/admin/analytics",
    roles: ["owner", "manager"],
  },
  {
    label: "Users",
    path: "/admin/users",
    roles: ["owner"],
  },
  {
    label: "Backup & Restore",
    path: "/admin/system/backups",
    roles: ["owner", "manager"],
  },
  {
    label: "Audit Logs",
    path: "/admin/system/audit-logs",
    roles: ["owner"],
  },
  {
    label: "Settings",
    path: "/admin/system/settings",
    roles: ["owner"],
  },
];