export type Role = "super_admin" | "admin_tengkulak" | "petani" | "pembeli" | "guest";

/** Home dashboard per role (post-login redirect target). */
export const ROLE_HOME: Record<Role, string> = {
  super_admin: "/super",
  admin_tengkulak: "/tengkulak",
  petani: "/petani",
  pembeli: "/buyer",
  guest: "/",
};

/** Route-prefix => roles allowed. Anything not listed is public (Guest-readable). Used by middleware.ts AND <RoleGuard>. */
export const PROTECTED: { prefix: string; roles: Role[] }[] = [
  { prefix: "/super", roles: ["super_admin"] },
  { prefix: "/tengkulak", roles: ["admin_tengkulak", "super_admin"] },
  { prefix: "/petani", roles: ["petani"] },
  { prefix: "/buyer", roles: ["pembeli"] },
];

export function allowedRoles(pathname: string): Role[] | null {
  const hit = PROTECTED.find(p => pathname === p.prefix || pathname.startsWith(p.prefix + "/"));
  return hit ? hit.roles : null;
}
