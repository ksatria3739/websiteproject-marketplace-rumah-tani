import { notFound } from "next/navigation";
import type { Metadata } from "next";
import { sget } from "@/lib/server";
import { idr } from "@/lib/api";
import { BuyPanel } from "./BuyPanel";

type P = { params: { slug: string } };
const load = (slug: string) => sget<any>(`/public/catalog/${slug}`, null, 30);

export async function generateMetadata({ params }: P): Promise<Metadata> {
  const c = await load(params.slug);
  return c ? { title: c.name, description: `${c.name} dari ${c.region}. ${c.description ?? ""}`.slice(0, 160) } : {};
}

export default async function Detail({ params }: P) {
  const c = await load(params.slug);
  if (!c) notFound();
  const jsonLd = { "@context": "https://schema.org", "@type": "Product", name: c.name, description: c.description,
    offers: c.variants.map((v: any) => ({ "@type": "Offer", priceCurrency: "IDR", price: v.price, availability: Number(v.available) > 0 ? "InStock" : "OutOfStock" })) };
  return (
    <div className="mx-auto max-w-4xl px-4 py-6">
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <h1 className="text-2xl font-bold">{c.name}</h1>
      <p className="text-sm text-gray-500">{c.category} · {c.region} · Petani: {c.farmer_name}</p>
      <p className="mt-3 text-gray-700">{c.description}</p>
      <h2 className="mb-2 mt-6 font-semibold">Varian & Grade</h2>
      <div className="grid gap-3">
        {c.variants.map((v: any) => (
          <div key={v.id} className="card">
            <div className="flex items-center justify-between">
              <div><b>{v.variant_name}</b> <span className="rounded bg-brand-100 px-2 text-xs font-bold text-brand-700">Grade {v.grade}</span></div>
              <div className="text-lg font-bold text-brand-700">{idr(v.price)}<span className="text-xs text-gray-500">/{v.unit}</span></div>
            </div>
            <p className="text-xs text-gray-500">Tersedia {Number(v.available)} {v.unit} · Min. order {Number(v.min_order)}</p>
            <BuyPanel variant={{ id: v.id, price: Number(v.price), minOrder: Number(v.min_order), available: Number(v.available) }} />
          </div>))}
      </div>
    </div>
  );
}
