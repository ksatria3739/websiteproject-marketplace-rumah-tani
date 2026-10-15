"use client";
import { RoleGuard } from "@/components/Guard";
import { DashShell } from "@/components/ui";

export default function Layout({ children }: { children: React.ReactNode }) {
  return (
    <RoleGuard>
      <DashShell title="Super Admin" items={[
        { href: "/super", label: "Analitik" }, { href: "/super/users", label: "Pengguna" },
        { href: "/super/kyc", label: "KYC" }, { href: "/super/settings", label: "Konfigurasi" }]}>
        {children}
      </DashShell>
    </RoleGuard>
  );
}
