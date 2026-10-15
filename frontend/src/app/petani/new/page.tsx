"use client";
import { useState } from "react";
import { useRouter } from "next/navigation";
import { api, useAuth } from "@/lib/api";
import { useFetch } from "@/lib/hooks";
import { ErrorNote } from "@/components/ui";

interface V { variantName: string; grade: "A" | "B" | "C"; price: string; stockQty: string; minOrder: string }
const blank = (g: V["grade"] = "A"): V => ({ variantName: "", grade: g, price: "", stockQty: "", minOrder: "1" });

export default function NewHarvest() {
  const { data: meta } = useFetch<any>("/public/meta");
  const user = useAuth(s => s.user)!;
  const router = useRouter();
  const [name, setName] = useState(""); const [categoryId, setCat] = useState(""); const [desc, setDesc] = useState("");
  const [regionId, setRegion] = useState(user.regionId ? String(user.regionId) : "");
  const [imageUrl, setImage] = useState("");
  const [variants, setVariants] = useState<V[]>([blank()]);
  const [err, setErr] = useState<string | null>(null); const [busy, setBusy] = useState(false);
  const upd = (i: number, k: keyof V, v: string) => setVariants(vs => vs.map((x, j) => (j === i ? { ...x, [k]: v } : x)));

  const submit = async (e: React.FormEvent) => {
    e.preventDefault(); setBusy(true); setErr(null);
    try {
      await api("/inventory/commodities", { method: "POST", body: {
        name, categoryId: Number(categoryId), regionId: regionId ? Number(regionId) : undefined, description: desc || undefined, imageUrl: imageUrl || undefined,
        variants: variants.map(v => ({ variantName: v.variantName || name, grade: v.grade, price: Number(v.price), stockQty: Number(v.stockQty || 0), minOrder: Number(v.minOrder || 1) })),
      } });
      router.push(user.role === "petani" ? "/petani" : "/tengkulak");
    } catch (e: any) { setErr(e.message); } finally { setBusy(false); }
  };
  return (
    <form onSubmit={submit} className="space-y-4">
      <h1 className="text-xl font-bold">Input Hasil Panen</h1><ErrorNote msg={err} />
      <div className="card space-y-3">
        <input className="input" placeholder="Nama komoditas (mis. Cabai Rawit Merah)" required value={name} onChange={e => setName(e.target.value)} />
        <select className="input" required value={categoryId} onChange={e => setCat(e.target.value)}>
          <option value="">Kategori</option>{meta?.categories.map((c: any) => <option key={c.id} value={c.id}>{c.name}</option>)}</select>
        {!user.regionId && <select className="input" required value={regionId} onChange={e => setRegion(e.target.value)}>
          <option value="">Wilayah</option>{meta?.regions.map((r: any) => <option key={r.id} value={r.id}>{r.name}</option>)}</select>}
        <input className="input" placeholder="URL foto hasil panen (https://…)" value={imageUrl} onChange={e => setImage(e.target.value)} />
        <textarea className="input" rows={2} placeholder="Deskripsi" value={desc} onChange={e => setDesc(e.target.value)} />
      </div>
      {variants.map((v, i) => (
        <div key={i} className="card space-y-2">
          <div className="grid grid-cols-3 gap-2">
            {(["A", "B", "C"] as const).map(g => <button type="button" key={g} onClick={() => upd(i, "grade", g)} className={v.grade === g ? "btn" : "btn-outline"}>Grade {g}</button>)}
          </div>
          <input className="input" placeholder="Nama varian (opsional)" value={v.variantName} onChange={e => upd(i, "variantName", e.target.value)} />
          <div className="grid grid-cols-3 gap-2">
            <input className="input" type="number" inputMode="decimal" placeholder="Harga/kg" required value={v.price} onChange={e => upd(i, "price", e.target.value)} />
            <input className="input" type="number" inputMode="decimal" placeholder="Stok (kg)" value={v.stockQty} onChange={e => upd(i, "stockQty", e.target.value)} />
            <input className="input" type="number" inputMode="decimal" placeholder="Min. order" value={v.minOrder} onChange={e => upd(i, "minOrder", e.target.value)} />
          </div>
        </div>))}
      <button type="button" className="btn-outline w-full" onClick={() => setVariants([...variants, blank("B")])}>+ Tambah varian/grade</button>
      <button className="btn w-full py-3" disabled={busy}>{busy ? "Menyimpan…" : "Simpan & Ajukan Kurasi"}</button>
    </form>
  );
}
