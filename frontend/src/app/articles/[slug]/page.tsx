import { notFound } from "next/navigation";
import { sget } from "@/lib/server";

export default async function Article({ params }: { params: { slug: string } }) {
  const a = await sget<any>(`/public/articles/${params.slug}`, null);
  if (!a) notFound();
  return (
    <article className="mx-auto max-w-3xl px-4 py-6">
      <h1 className="text-3xl font-bold">{a.title}</h1>
      <p className="mt-4 whitespace-pre-line text-gray-800">{a.body}</p>
    </article>
  );
}
