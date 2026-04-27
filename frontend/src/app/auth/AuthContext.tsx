import { createContext, useContext, useEffect, useState } from "react";
import { AuthState, User } from "./types";
import { tokenStorage } from "./token";
import * as authService from "./authService";

interface AuthContextValue extends AuthState {
  login: (email: string, password: string) => Promise<User>;
  logout: () => Promise<void>;
  loading: boolean;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [state, setState] = useState<AuthState>({
    user: null,
    token: tokenStorage.get(),
    isAuthenticated: false,
  });

  const [loading, setLoading] = useState(true);

  // 🔁 Restore auth on refresh
  useEffect(() => {
    const restoreAuth = async () => {
      const token = tokenStorage.get();

      if (!token) {
        setLoading(false);
        return;
      }

      try {
        const user: User = await authService.me();

        setState({
          user,
          token,
          isAuthenticated: true,
        });
      } catch {
        tokenStorage.clear();
        setState({
          user: null,
          token: null,
          isAuthenticated: false,
        });
      } finally {
        setLoading(false);
      }
    };

    restoreAuth();
  }, []);

  const login = async (email: string, password: string): Promise<User> => {
    const { token, user } = await authService.login(email, password);

    tokenStorage.set(token);

    setState({
      user,
      token,
      isAuthenticated: true,
    });

    return user;
  };

  const logout = async () => {
    await authService.logout();
    tokenStorage.clear();

    setState({
      user: null,
      token: null,
      isAuthenticated: false,
    });
  };

  return (
    <AuthContext.Provider value={{ ...state, login, logout, loading }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error("useAuth must be used inside AuthProvider");
  }
  return ctx;
}
