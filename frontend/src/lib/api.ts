"use client";
import { create } from "zustand";
import { Role, ROLE_HOME } from "./roles";

export const API = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:4000/api";

export interface SessionUser { id: string; email: string; fullName?: string; role: Role; kycStatus?: string; buyerType?: string | null; regionId?: number | null }

interface AuthState {
  user: SessionUser | null; accessToken: string | null; refreshToken: string | null; ready: boolean;
  setSession: (u: SessionUser, a: string, r: string) => void; clear: () => void; hydrate: () => void;
}

const setRoleCookie = (role: string | null) => {
  document.cookie = role ? `rt_role=${role}; path=/; max-age=1209600; SameSite=Lax` : "rt_role=; path=/; max-age=0";
};

export const useAuth = create<AuthState>((set) => ({
  user: null, accessToken: null, refreshToken: null, ready: false,
  setSession: (user, accessToken, refreshToken) => {
    localStorage.setItem("rt_session", JSON.stringify({ user, accessToken, refreshToken }));
    setRoleCookie(user.role);
    set({ user, accessToken, refreshToken, ready: true });
  },
  clear: () => { localStorage.removeItem("rt_session"); setRoleCookie(null); set({ user: null, accessToken: null, refreshToken: null, ready: true }); },
  hydrate: () => {
    try {
      const s = JSON.parse(localStorage.getItem("rt_session") ?? "null");
      if (s) { setRoleCookie(s.user.role); set({ ...s, ready: true }); return; }
    } catch { /* ignore corrupt storage */ }
    setRoleCookie(null);
    set({ ready: true });
  },
}));

let refreshing: Promise<boolean> | null = null;
async function refresh(): Promise<boolean> {
  const { refreshToken, user, setSession, clear } = useAuth.getState();
  if (!refreshToken || !user) return false;
  const r = await fetch(`${API}/auth/refresh`, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ refreshToken }) });
  if (!r.ok) { clear(); return false; }
  const j = await r.json(); setSession(user, j.accessToken, j.refreshToken); return true;
}

export class ApiError extends Error { constructor(public status: number, message: string, public details?: any) { super(message); } }

/** fetch wrapper: attaches JWT, transparently refreshes once on 401, throws ApiError. */
export async function api<T = any>(path: string, opts: { method?: string; body?: any; form?: FormData } = {}): Promise<T> {
  const run = () => {
    const h: Record<string, string> = {};
    const t = useAuth.getState().accessToken; if (t) h.Authorization = `Bearer ${t}`;
    if (!opts.form) h["Content-Type"] = "application/json";
    return fetch(`${API}${path}`, { method: opts.method ?? "GET", headers: h, body: opts.form ?? (opts.body ? JSON.stringify(opts.body) : undefined) });
  };
  let r = await run();
  if (r.status === 401 && useAuth.getState().refreshToken) {
    refreshing ??= refresh().finally(() => { refreshing = null; });
    if (await refreshing) r = await run();
  }
  const j = await r.json().catch(() => ({}));
  if (!r.ok) throw new ApiError(r.status, j.error ?? "Request failed", j.details);
  return j;
}

export const homeFor = (role: Role) => ROLE_HOME[role] ?? "/";
export const idr = (n: number | string) => new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(Number(n));
