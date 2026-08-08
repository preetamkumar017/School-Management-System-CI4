import axios, { type AxiosError, type InternalAxiosRequestConfig } from "axios";

export interface ApiErrorBody {
  category: string;
  code: string;
  message: string;
  fields: Record<string, string> | null;
}

export interface ApiEnvelope<T> {
  success: boolean;
  data: T | null;
  error: ApiErrorBody | null;
  meta: Record<string, unknown>;
}

const ACCESS_TOKEN_KEY = "school_erp.access_token";
const REFRESH_TOKEN_KEY = "school_erp.refresh_token";

export function getAccessToken(): string | null {
  return localStorage.getItem(ACCESS_TOKEN_KEY);
}

export function getRefreshToken(): string | null {
  return localStorage.getItem(REFRESH_TOKEN_KEY);
}

export function setTokens(accessToken: string, refreshToken: string): void {
  localStorage.setItem(ACCESS_TOKEN_KEY, accessToken);
  localStorage.setItem(REFRESH_TOKEN_KEY, refreshToken);
}

export function clearTokens(): void {
  localStorage.removeItem(ACCESS_TOKEN_KEY);
  localStorage.removeItem(REFRESH_TOKEN_KEY);
}

export const api = axios.create({
  baseURL: "/api/v1",
});

api.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = getAccessToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Refresh-and-retry: on a 401 from an expired access token, use the
// refresh token once to get a new access token, then replay the
// original request exactly once. Multiple concurrent 401s share a
// single in-flight refresh instead of each firing their own.
let refreshPromise: Promise<string> | null = null;

async function performRefresh(): Promise<string> {
  const refreshToken = getRefreshToken();
  if (!refreshToken) {
    throw new Error("No refresh token available");
  }

  const response = await axios.post<ApiEnvelope<{ access_token: string; access_token_expires_at: string }>>(
    "/api/v1/auth/refresh",
    { refresh_token: refreshToken },
  );

  const accessToken = response.data.data?.access_token;
  if (!accessToken) {
    throw new Error("Refresh did not return an access token");
  }

  localStorage.setItem(ACCESS_TOKEN_KEY, accessToken);
  return accessToken;
}

api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const original = error.config as (InternalAxiosRequestConfig & { _retried?: boolean }) | undefined;

    if (error.response?.status === 401 && original && !original._retried && !original.url?.includes("/auth/")) {
      original._retried = true;

      try {
        refreshPromise ??= performRefresh().finally(() => {
          refreshPromise = null;
        });
        const newAccessToken = await refreshPromise;

        original.headers = original.headers ?? {};
        original.headers.Authorization = `Bearer ${newAccessToken}`;
        return api(original);
      } catch {
        clearTokens();
        window.location.href = "/login";
        return Promise.reject(error);
      }
    }

    return Promise.reject(error);
  },
);

export function apiErrorMessage(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const envelope = error.response?.data as ApiEnvelope<unknown> | undefined;
    if (envelope?.error?.message) {
      return envelope.error.message;
    }
  }
  return "Something went wrong. Please try again.";
}
