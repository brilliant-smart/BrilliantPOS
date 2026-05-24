import { useState, useEffect } from "react";
import { NavLink, useLocation } from "react-router-dom";
import { useAuth } from "@/app/auth/AuthContext";
import { sidebarItems } from "./adminSidebarConfig";
import { Menu, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { api } from "@/app/lib/api";

export default function AdminSidebar() {
  const { user } = useAuth();
  const location = useLocation();
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const [badgeCounts, setBadgeCounts] = useState<Record<string, number>>({});

  if (!user) return null;

  const visibleItems = sidebarItems.filter((item) =>
    item.roles.includes(user.role),
  );

  useEffect(() => {
    const fetchBadges = async () => {
      const badgeItems = visibleItems.filter((item) => item.badge);
      const counts: Record<string, number> = {};
      for (const item of badgeItems) {
        try {
          const response = await api.get(item.badge!.fetchUrl);
          const data = response.data;
          counts[item.path] = data.count ?? 0;
        } catch {
          counts[item.path] = 0;
        }
      }
      setBadgeCounts(counts);
    };
    fetchBadges();
  }, [user?.role]);

  return (
    <>
      {/* Mobile Menu Button */}
      <Button
        variant="ghost"
        size="icon"
        className="fixed top-4 left-4 z-50 md:hidden"
        onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
      >
        {isMobileMenuOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
      </Button>

      {/* Overlay for mobile */}
      {isMobileMenuOpen && (
        <div
          className="fixed inset-0 bg-black/50 z-40 md:hidden"
          onClick={() => setIsMobileMenuOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside
        className={`
          fixed md:sticky top-0 left-0 h-screen z-40
          w-64 border-r border-border bg-background p-3
          transition-transform duration-300 ease-in-out
          md:translate-x-0
          ${isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full'}
          overflow-y-auto
        `}
      >
        <div className="h-16 md:h-0" /> {/* Spacer for mobile menu button */}
        <nav className="space-y-1">
          {visibleItems.map((item) => {
            const isActive = location.pathname === item.path || (location.pathname.startsWith(item.path + '/') && !visibleItems.some(other => other !== item && location.pathname === other.path));

            return (
              <NavLink
                key={item.path}
                to={item.path}
                onClick={() => setIsMobileMenuOpen(false)}
                className={`flex items-center justify-between rounded-md px-3 py-2 text-sm transition ${
                  isActive
                    ? "bg-primary text-primary-foreground"
                    : "text-muted-foreground dark:text-muted-foreground/80 hover:bg-muted"
                }`}
              >
                <span>{item.label}</span>
                {item.badge && (badgeCounts[item.path] ?? 0) > 0 && (
                  <Badge variant="destructive" className="ml-auto text-xs">
                    {badgeCounts[item.path]}
                  </Badge>
                )}
              </NavLink>
            );
          })}
        </nav>
      </aside>
    </>
  );
}