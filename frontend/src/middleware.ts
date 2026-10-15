import { NextRequest, NextResponse } from "next/server";
import { allowedRoles, ROLE_HOME, Role } from "@/lib/roles";

/**
 * Edge route guard. Reads the non-sensitive `rt_role` hint cookie set at login.
 * It is only UX-level protection (fast redirects, no flash of forbidden pages);
 * the API enforces the real authorization on every request via JWT + role middleware.
 */
export function middleware(req: NextRequest) {
  const { pathname } = req.nextUrl;
  const allowed = allowedRoles(pathname);
  if (!allowed) return NextResponse.next();

  const role = (req.cookies.get("rt_role")?.value ?? "guest") as Role;
  if (role === "guest") {
    const url = new URL("/login", req.url);
    url.searchParams.set("next", pathname);
    return NextResponse.redirect(url);
  }
  if (!allowed.includes(role)) return NextResponse.redirect(new URL(ROLE_HOME[role] ?? "/", req.url));
  return NextResponse.next();
}

export const config = { matcher: ["/super/:path*", "/tengkulak/:path*", "/petani/:path*", "/buyer/:path*"] };
