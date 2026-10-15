"use client";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { api, homeFor, useAuth } from "@/lib/api";

export function Navbar() {
  const { user, refreshToken, clear } = useAuth();
  const router = useRouter();
  const logout = async () => {
    await api("/auth/logout", { method: "POST", body: { refreshToken } }).catch(() => {});
    clear(); router.push("/");
  };
  return (
    <header className="sticky top-0 z-20 border-b bg-white/90 backdrop-blur">
      <div className="mx-auto flex max-w-6xl items-center gap-3 px-4 py-3">
        <Link href="/" className="text-lg font-bold text-brand-700">🌾 Rumah Tani</Link>
        <nav className="ml-4 hidden gap-4 text-sm text-gray-600 sm:flex">
          <Link href="/catalog" className="hover:text-brand-700">Katalog</Link>
          <Link href="/articles" className="hover:text-brand-700">Berita</Link>
        </nav>
        <div className="ml-auto flex items-center gap-2">
          {user ? (<>
            <Link href={homeFor(user.role)} className="btn-outline">Dashboard</Link>
            <button onClick={logout} className="btn-outline">Keluar</button>
          </>) : (<>
            <Link href="/login" className="btn-outline">Masuk</Link>
            <Link href="/register" className="btn">Daftar</Link>
          </>)}
        </div>
      </div>
    </header>
  );
}

export interface NavItem { href: string; label: string }
/** Sidebar on desktop, bottom tab bar on mobile (thumb-friendly for farmers in the field). */
export function DashShell({ title, items, children }: { title: string; items: NavItem[]; children: React.ReactNode }) {
  return (
    <div className="mx-auto max-w-6xl px-4 pb-24 pt-4 md:grid md:grid-cols-[200px_1fr] md:gap-6 md:pb-8">
      <aside className="hidden md:block">
        <h2 className="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">{title}</h2>
        <nav className="flex flex-col gap-1">
          {items.map(i => <Link key={i.href} href={i.href} className="rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-brand-50">{i.label}</Link>)}
        </nav>
      </aside>
      <main>{children}</main>
      <nav className="fixed inset-x-0 bottom-0 z-20 flex border-t bg-white md:hidden">
        {items.slice(0, 5).map(i => <Link key={i.href} href={i.href} className="flex-1 py-3 text-center text-xs font-medium text-gray-700">{i.label}</Link>)}
      </nav>
    </div>
  );
}

export function Badge({ children, tone = "gray" }: { children: React.ReactNode; tone?: "gray" | "green" | "amber" | "red" | "blue" }) {
  const c = { gray: "bg-gray-100 text-gray-700", green: "bg-green-100 text-green-800", amber: "bg-amber-100 text-amber-800", red: "bg-red-100 text-red-800", blue: "bg-blue-100 text-blue-800" }[tone];
  return <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${c}`}>{children}</span>;
}

export const statusTone = (s: string) =>
  (["approved", "paid", "delivered", "completed", "accepted"].includes(s) ? "green" : ["pending", "open", "pending_payment", "processing", "shipped"].includes(s) ? "amber" : ["rejected", "cancelled", "expired", "failed"].includes(s) ? "red" : "gray") as any;

export function ErrorNote({ msg }: { msg: string | null }) {
  return msg ? <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{msg}</p> : null;
}
