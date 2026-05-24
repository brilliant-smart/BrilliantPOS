import { useAuth } from "@/app/auth/AuthContext";
import UserProfileMenu from "./UserProfileMenu";
import { useTheme } from "next-themes";
import { Moon, Sun } from "lucide-react";
import { Button } from "@/components/ui/button";
import navbarLogo from "@/assets/NavbarLogo.png";

export default function AdminHeader() {
  const { user } = useAuth();
  const { theme, setTheme } = useTheme();

  if (!user) return null;

  const roleLabel = user.role === "owner"
    ? "Owner"
    : user.role === "manager"
    ? "Manager"
    : "Cashier";

  return (
    <header className="sticky top-0 z-40 w-full border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
      <div className="flex h-14 items-center justify-between px-6">
        <img src={navbarLogo} alt="Brilliant POS" className="h-24 w-auto" />

        <div className="flex items-center gap-3">
          <span className="text-xs text-muted-foreground dark:text-muted-foreground/80">{roleLabel}</span>
          <Button
            variant="ghost"
            size="icon"
            onClick={() => setTheme(theme === "dark" ? "light" : "dark")}
            aria-label="Toggle theme"
          >
            <Sun className="h-4 w-4 rotate-0 scale-100 transition-all dark:-rotate-90 dark:scale-0" />
            <Moon className="absolute h-4 w-4 rotate-90 scale-0 transition-all dark:rotate-0 dark:scale-100" />
          </Button>
          <UserProfileMenu />
        </div>
      </div>
    </header>
  );
}