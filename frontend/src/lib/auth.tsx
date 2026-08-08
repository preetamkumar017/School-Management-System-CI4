import { createContext, useContext, useEffect, useState, type ReactNode } from "react";
import { api, apiErrorMessage, clearTokens, getAccessToken, getRefreshToken, setTokens } from "./api";

interface JwtPayload {
  user_id: number;
  role_id: number;
  permission_set: string[];
  exp: number;
}

interface AuthUser {
  userId: number;
  roleId: number;
  permissionSet: string[];
}

interface AuthContextValue {
  user: AuthUser | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (username: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

// The access token's payload is decoded client-side purely for display
// (current user id / role / permissions) — never trusted as proof of
// anything; the backend re-validates the signature on every request.
function decodeAccessToken(token: string): AuthUser | null {
  try {
    const payload = token.split(".")[1];
    const decoded = JSON.parse(atob(payload.replace(/-/g, "+").replace(/_/g, "/"))) as JwtPayload;
    return { userId: decoded.user_id, roleId: decoded.role_id, permissionSet: decoded.permission_set ?? [] };
  } catch {
    return null;
  }
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const token = getAccessToken();
    if (token) {
      setUser(decodeAccessToken(token));
    }
    setIsLoading(false);
  }, []);

  async function login(username: string, password: string): Promise<void> {
    const response = await api.post<{
      success: boolean;
      data: { access_token: string; refresh_token: string } | null;
      error: { message: string } | null;
    }>("/auth/login", { username, password });

    const data = response.data.data;
    if (!data) {
      throw new Error(response.data.error?.message ?? "Login failed");
    }

    setTokens(data.access_token, data.refresh_token);
    setUser(decodeAccessToken(data.access_token));
  }

  async function logout(): Promise<void> {
    const refreshToken = getRefreshToken();
    try {
      if (refreshToken) {
        await api.post("/auth/logout", { refresh_token: refreshToken });
      }
    } catch {
      // Best-effort: the session gets revoked server-side when possible,
      // but the client always clears its own tokens regardless.
    } finally {
      clearTokens();
      setUser(null);
    }
  }

  return (
    <AuthContext.Provider value={{ user, isAuthenticated: user !== null, isLoading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error("useAuth must be used within an AuthProvider");
  }
  return context;
}

export { apiErrorMessage };
