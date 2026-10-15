"use client";
import { RoleGuard } from "@/components/Guard";
import { DashShell } from "@/components/ui";

export default function Layout({ children }: { children: React.ReactNode }) {
  return (
    <RoleGuard>
      <DashShell title="Petani" items={[
        { href: "/petani", label: "Stok" }, { href: "/petani/new", label: "+ Panen" },
        { href: "/petani/orders", label: "Pesanan" }, { href: "/petani/negotiations", label: "Nego" }]}>
        {children}
      </DashShell>
    </RoleGuard>
  );
}
