import "./globals.css";
import type { Metadata } from "next";
import { Providers } from "@/components/Guard";
import { Navbar } from "@/components/ui";

export const metadata: Metadata = {
  title: { default: "Rumah Tani - Marketplace Pertanian", template: "%s | Rumah Tani" },
  description: "Platform digital rantai pasok pertanian: hubungkan petani, tengkulak, dan pembeli dengan stok real-time dan harga transparan.",
  openGraph: { title: "Rumah Tani", description: "Marketplace pertanian B2B/B2C", type: "website" },
};
export const viewport = { width: "device-width", initialScale: 1 };

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="id">
      <body>
        <Providers>
          <Navbar />
          {children}
          <footer className="mt-12 border-t bg-white py-6 text-center text-sm text-gray-500">© Rumah Tani — dari petani, untuk Indonesia</footer>
        </Providers>
      </body>
    </html>
  );
}
