"use client";
import { useState } from "react";
import { api, idr } from "@/lib/api";
import { useFetch } from "@/lib/hooks";
import { Badge, ErrorNote, statusTone } from "@/components/ui";
import { KycBanner } from "@/components/shared";

export default function PetaniHome() {
  const { data: sum } = useFetch<any>("/dashboard/summary");
  const { data: items, reload } = useFetch<any[]>("/inventory/commodities");
  const [err, setErr] = useState<string | null>(null);

  // Delta-based stock update: safe under concurrent sales (server uses row lock)
  const adjust = async (variantId: string, delta: number) => {
    try { await api(`/inventory/variants/${variantId}/stock`, { method: "POST", body: { delta, reason: "adjust" } }); setErr(null); reload(); }
    catch (e: any) { setErr(e.message); }
  };
  return (
    <div className="space-y-4">
      <KycBanner />
      <div className="grid grid-cols-3 gap-3">
        <div className="card"><p className="text-xs text-gray-500">Perlu diproses</p><p className="text-2xl font-bold">{sum?.to_process ?? "–"}</p></div>
        <div className="card"><p className="text-xs text-gray-500">Penjualan</p><p className="text-lg font-bold">{sum ? idr(sum.sales) : "–"}</p></div>
        <div className="card"><p className="text-xs text-gray-500">Stok menipis</p><p className="text-2xl font-bold text-amber-600">{sum?.lowStockVariants ?? "–"}</p></div>
      </div>
      <ErrorNote msg={err} />
      {items?.map(c => (
        <div key={c.id} className="card">
          <div className="flex items-center justify-between"><b>{c.name}</b><Badge tone={statusTone(c.curation)}>{c.curation}</Badge></div>
          {c.curation_note && <p className="text-xs text-red-600">{c.curation_note}</p>}
          <div className="mt-2 divide-y">
            {c.variants?.map((v: any) => (
              <div key={v.id} className="flex items-center justify-between gap-2 py-2">
                <div><span className="font-medium">Grade {v.grade}</span> <span className="text-xs text-gray-500">{idr(v.price)}/{v.unit}</span>
                  <p className="text-xs text-gray-500">Stok {Number(v.stock)} · Dipesan {Number(v.reserved)}</p></div>
                <div className="flex gap-1">
                  {[-10, -1, 1, 10].map(d => <button key={d} className="btn-outline px-3" onClick={() => adjust(v.id, d)}>{d > 0 ? `+${d}` : d}</button>)}
                </div>
              </div>))}
          </div>
        </div>))}
    </div>
  );
}
