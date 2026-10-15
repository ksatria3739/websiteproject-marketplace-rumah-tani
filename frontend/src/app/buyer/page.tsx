"use client";
import Link from "next/link";
import { useState } from "react";
import { api, idr } from "@/lib/api";
import { useFetch } from "@/lib/hooks";
import { Badge, ErrorNote, statusTone } from "@/components/ui";
import { KycBanner } from "@/components/shared";

export default function BuyerHome() {
  const { data, reload, error } = useFetch<any[]>("/orders");
  const [err, setErr] = useState<string | null>(null);
  return (
    <div className="space-y-3">
      <KycBanner />
      <h1 className="text-xl font-bold">Pesanan Saya</h1><ErrorNote msg={error ?? err} />
      {data?.length === 0 && <p className="text-gray-500">Belum ada pesanan. <Link href="/catalog" className="text-brand-700 underline">Belanja sekarang</Link></p>}
      {data?.map(o => (
        <div key={o.id} className="card flex flex-wrap items-center justify-between gap-2">
          <div><p className="font-mono text-xs text-gray-500">{o.code}</p><p className="font-semibold">{o.seller}</p><p className="text-sm">{idr(o.total)} · {Number(o.total_weight_kg)} kg</p></div>
          <div className="flex items-center gap-2">
            <Badge tone={statusTone(o.status)}>{o.status}</Badge>
            {o.tracking_code && <Link href={`/track/${o.tracking_code}`} className="btn-outline">Lacak</Link>}
            {o.status === "delivered" && <button className="btn" onClick={async () => { try { await api(`/orders/${o.id}/confirm-received`, { method: "POST" }); reload(); } catch (e: any) { setErr(e.message); } }}>Terima</button>}
            {o.status === "pending_payment" && <Link href={`/buyer/orders/${o.id}`} className="btn">Bayar</Link>}
          </div>
        </div>))}
    </div>
  );
}
