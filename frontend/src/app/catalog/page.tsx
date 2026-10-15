import Link from "next/link";
import type { Metadata } from "next";
import { sget } from "@/lib/server";
import { idr } from "@/lib/api";

export const metadata: Metadata = { title: "Katalog Komoditas", description: "Cari hasil bumi segar berdasarkan wilayah, kategori, dan grade." };

export default async function Catalog({ searchParams }: { searchParams: Record<string, string | undefined> }) {
  const qs = new URLSearchParams(Object.entries(searchParams).filter(([, v]) => v) as [string, string][]).toString();
  const [meta, data] = await Promise.all([
    sget<any>("/public/meta", { regions: [], categories: [] }, 300),
    sget<any>(`/public/catalog?${qs}`, { items: [] }, 30),
  ]);
  return (
    <div className="mx-auto max-w-6xl px-4 py-6">
      <h1 className="mb-4 text-2xl font-bold">Katalog Komoditas</h1>
      {/* GET form => shareable, crawlable filter URLs, no JS needed */}
      <form className="card mb-6 grid gap-3 sm:grid-cols-5">
        <input name="q" defaultValue={searchParams.q} placeholder="Cari komoditas…" className="input sm:col-span-2" />
        <select name="regionId" defaultValue={searchParams.regionId ?? ""} className="input">
          <option value="">Semua wilayah</option>{meta.regions.map((r: any) => <option key={r.id} value={r.id}>{r.name}</option>)}
        </select>
        <select name="categoryId" defaultValue={searchParams.categoryId ?? ""} className="input">
          <option value="">Semua kategori</option>{meta.categories.map((c: any) => <option key={c.id} value={c.id}>{c.name}</option>)}
        </select>
        <select name="grade" defaultValue={searchParams.grade ?? ""} className="input">
          <option value="">Semua grade</option>{["A", "B", "C"].map(g => <option key={g}>{g}</option>)}
        </select>
        <button className="btn sm:col-span-5">Terapkan Filter</button>
      </form>
      {data.items.length === 0 && <p className="text-gray-500">Tidak ada komoditas ditemukan.</p>}
      <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
        {data.items.map((c: any) => (
          <Link key={c.id} href={`/catalog/${c.slug}`} className="card transition hover:border-brand-500">
            <div className="mb-2 flex aspect-square items-center justify-center rounded-lg bg-brand-50 text-4xl">
              {c.image_url ? /* eslint-disable-next-line @next/next/no-img-element */ <img src={c.image_url} alt={c.name} className="h-full w-full rounded-lg object-cover" /> : "🥬"}
            </div>
            <h3 className="font-semibold leading-tight">{c.name}</h3>
            <p className="text-xs text-gray-500">{c.region} · Grade {c.grades.join("/")}</p>
            <p className="mt-1 font-bold text-brand-700">mulai {idr(c.price_from)}</p>
            <p className="text-xs text-gray-500">Stok {Number(c.available)} kg</p>
          </Link>))}
      </div>
    </div>
  );
}
