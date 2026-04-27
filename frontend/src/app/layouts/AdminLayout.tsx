import AdminSidebar from "./AdminSidebar";
import AdminHeader from "@/components/AdminHeader";

export default function AdminLayout({ children }: { children: JSX.Element }) {
  return (
    <div className="flex min-h-screen overflow-x-hidden">
      <AdminSidebar />
      <div className="flex-1 flex flex-col min-w-0 overflow-x-hidden">
        <AdminHeader />
        <main className="flex-1 bg-background overflow-x-hidden w-full">{children}</main>
      </div>
    </div>
  );
}
