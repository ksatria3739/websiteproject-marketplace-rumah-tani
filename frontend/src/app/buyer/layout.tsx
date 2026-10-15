"use client";
import { RoleGuard } from "@/components/Guard";
import { DashShell } from "@/components/ui";

export default function Layout({ children }: { children: React.ReactNode }) {
  return (
    <RoleGuard>
      <DashShell title="Workspace Pembeli" items={[
        { href: "/buyer", label: "Pesanan" }, { href: "/buyer/cart", label: "Keranjang" },
        { href: "/buyer/negotiations", label: "Negosiasi" }, { href: "/catalog", label: "Katalog" }]}>
        {children}
      </DashShell>
    </RoleGuard>
  );
}
