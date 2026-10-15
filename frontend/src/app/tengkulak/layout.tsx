"use client";
import { RoleGuard } from "@/components/Guard";
import { DashShell } from "@/components/ui";

export default function Layout({ children }: { children: React.ReactNode }) {
  return (
    <RoleGuard>
      <DashShell title="Tengkulak / Admin Wilayah" items={[
        { href: "/tengkulak", label: "Kurasi" }, { href: "/tengkulak/logistics", label: "Logistik" },
        { href: "/tengkulak/orders", label: "Pesanan" }, { href: "/tengkulak/negotiations", label: "Nego" }, { href: "/tengkulak/kyc", label: "KYC" }]}>
        {children}
      </DashShell>
    </RoleGuard>
  );
}
