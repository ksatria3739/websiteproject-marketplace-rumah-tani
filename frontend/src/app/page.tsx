import Link from "next/link";
import { sget } from "@/lib/server";

export default async function Home() {
  const articles = await sget<any[]>("/public/articles", []);
  return (
    <>
      <section className="bg-gradient-to-br from-brand-700 to-brand-500 text-white">
        <div className="mx-auto max-w-6xl px-4 py-16 sm:py-24">
          <h1 className="max-w-2xl text-3xl font-extrabold sm:text-5xl">Dari ladang langsung ke meja — rantai pasok pertanian yang transparan.</h1>
          <p className="mt-4 max-w-xl text-brand-100">Rumah Tani menghubungkan petani, tengkulak wilayah, dan pembeli korporasi maupun umum dengan stok real-time dan harga jelas.</p>
          <div className="mt-8 flex gap-3">
            <Link href="/catalog" className="rounded-lg bg-white px-5 py-3 font-semibold text-brand-700">Lihat Katalog</Link>
            <Link href="/register" className="rounded-lg border border-white/60 px-5 py-3 font-semibold">Gabung Sekarang</Link>
          </div>
        </div>
      </section>
      <section className="mx-auto grid max-w-6xl gap-4 px-4 py-10 sm:grid-cols-3">
        {[["🧑‍🌾 Petani", "Input hasil panen, tentukan grade & harga, kelola stok langsung dari ponsel."],
          ["🚚 Tengkulak", "Kurasi komoditas wilayah, atur logistik dan distribusi ke pembeli besar."],
          ["🏨 Pembeli", "Belanja massal multi-vendor, negosiasi harga B2B, lacak pengiriman."]].map(([t, d]) => (
          <div key={t} className="card"><h3 className="font-bold">{t}</h3><p className="mt-1 text-sm text-gray-600">{d}</p></div>))}
      </section>
      <section className="mx-auto max-w-6xl px-4">
        <h2 className="mb-4 text-xl font-bold">Berita & Artikel Pertanian</h2>
        {articles.length === 0 && <p className="text-gray-500">Belum ada artikel.</p>}
        <div className="grid gap-4 sm:grid-cols-3">
          {articles.slice(0, 3).map(a => (
            <Link key={a.slug} href={`/articles/${a.slug}`} className="card hover:border-brand-500">
              <h3 className="font-semibold">{a.title}</h3><p className="mt-1 text-sm text-gray-600">{a.excerpt}</p>
            </Link>))}
        </div>
      </section>
    </>
  );
}
