import Link from "next/link";
import { sget } from "@/lib/server";

export const metadata = { title: "Berita & Artikel" };
export default async function Articles() {
  const a = await sget<any[]>("/public/articles", []);
  return (
    <div className="mx-auto max-w-3xl px-4 py-6">
      <h1 className="mb-4 text-2xl font-bold">Berita & Artikel</h1>
      <div className="space-y-3">{a.map(x => (
        <Link key={x.slug} href={`/articles/${x.slug}`} className="card block hover:border-brand-500"><h2 className="font-semibold">{x.title}</h2><p className="text-sm text-gray-600">{x.excerpt}</p></Link>))}</div>
    </div>
  );
}
