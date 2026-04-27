import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "@/app/auth/AuthContext";
import { useTheme } from "next-themes";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { AlertCircle, Loader2 } from "lucide-react";
import logo from "@/assets/logo.png";

const Login = () => {
  const navigate = useNavigate();
  const { login } = useAuth();
  const { theme, setTheme } = useTheme();

  // Apply dark auth theme on login page, restore on unmount
  useEffect(() => {
    const prevTheme = theme;
    setTheme("dark");
    const root = document.documentElement;
    root.classList.add("theme-auth");

    return () => {
      root.classList.remove("theme-auth");
      setTheme(prevTheme === "dark" ? "dark" : prevTheme || "light");
    };
  }, []);

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    // Client-side validation
    if (!email.trim()) {
      setError("Email is required");
      return;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      setError("Please enter a valid email address");
      return;
    }

    if (!password) {
      setError("Password is required");
      return;
    }

    if (password.length < 6) {
      setError("Password must be at least 6 characters");
      return;
    }

    setIsLoading(true);

    try {
      const user = await login(email.trim(), password);
      // Cashiers go to POS terminal, owners/managers go to dashboard
      const destination = user.role === "cashier" ? "/admin/pos" : "/admin/dashboard";
      navigate(destination, { replace: true });
    } catch (err: any) {
      const message =
        err?.response?.data?.message ||
        "Login failed. Please check your credentials.";
      setError(message);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-background px-4">
      <div className="w-full max-w-md -mt-32">
        {/* Logo Section */}
        <img
          src={logo}
          alt="Brilliant POS"
          className="h-128 w-auto mx-auto block"
        />

        {/* Login Card */}
        <div className="bg-card rounded-lg border border-border px-8 pt-2 pb-5 shadow-card -mt-2">
          <h1 className="text-2xl font-semibold text-center text-foreground mb-2 font-display">
            Brilliant POS
          </h1>
          <p className="text-sm text-center text-muted-foreground mb-6">
            Smart Retail & Inventory System
          </p>

          {/* Error Alert */}
          {error && (
            <Alert variant="destructive" className="mb-6">
              <AlertCircle className="h-4 w-4" />
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          )}

          <form onSubmit={handleSubmit} className="space-y-5">
            {/* Email Input */}
            <div className="space-y-2">
              <Label htmlFor="email" className="text-foreground font-body">
                Email
              </Label>
              <Input
                id="email"
                type="email"
                placeholder="Enter your email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="bg-background border-input text-foreground placeholder:text-muted-foreground focus:ring-ring"
                disabled={isLoading}
              />
            </div>

            {/* Password Input */}
            <div className="space-y-2">
              <Label htmlFor="password" className="text-foreground font-body">
                Password
              </Label>
              <Input
                id="password"
                type="password"
                placeholder="Enter your password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="bg-background border-input text-foreground placeholder:text-muted-foreground focus:ring-ring"
                disabled={isLoading}
              />
            </div>

            {/* Login Button */}
            <Button
              type="submit"
              className="w-full bg-background text-white border border-border hover:bg-gradient-to-r hover:from-emerald-600 hover:to-teal-500 hover:border-transparent"
              disabled={isLoading}
            >
              {isLoading ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Logging in...
                </>
              ) : (
                "Login"
              )}
            </Button>
          </form>
        </div>

        {/* Footer */}
        <p className="text-center text-muted-foreground text-xs mt-6 font-body">
          &copy; 2025 - 2026 Brilliant Smart. All rights reserved | +234 803 462 5258 | Powered by Digital Technologies
        </p>
      </div>
    </div>
  );
};

export default Login;
