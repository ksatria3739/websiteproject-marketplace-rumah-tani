"use client";
import { useEffect } from "react";
import { useRouter, usePathname } from "next/navigation";
import { useAuth, homeFor } from "@/lib/api";
import { allowedRoles } from "@/lib/roles";

export function Providers({ children }: { children: React.ReactNode }) {
  const hydrate = useAuth(s => s.hydrate);
  useEffect(() => { hydrate(); }, [hydrate]);
  return <>{children}</>;
}

/** Client-side guard (second layer after middleware.ts): handles expired sessions & role switches. */
export function RoleGuard({ children }: { children: React.ReactNode }) {
  const { user, ready } = useAuth();
  const router = useRouter(); const path = usePathname();
  const allowed = allowedRoles(path);
  useEffect(() => {
    if (!ready || !allowed) return;
    if (!user) router.replace(`/login?next=${encodeURIComponent(path)}`);
    else if (!allowed.includes(user.role)) router.replace(homeFor(user.role));
  }, [ready, user, allowed, path, router]);
  if (!ready || !user || (allowed && !allowed.includes(user.role))) return <div className="p-8 text-center text-gray-500">Memuat…</div>;
  return <>{children}</>;
}
