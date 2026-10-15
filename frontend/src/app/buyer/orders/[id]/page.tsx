"use client";
import Link from "next/link";
import { useState } from "react";
import { api, idr } from "@/lib/api";
import { useFetch } from "@/lib/hooks";
import { Badge, ErrorNote, statusTone } from "@/components/ui";

export default function OrderDetail({ params }: { params: { id: string } }) {
  const { data: o, reload, error } = useFetch<any>(`/orders/${params.id}`);
  const [err, setErr] = useState<string | null>(null);
  if (!o) return <ErrorNote msg={error} />;
  const p = o.payment;
  // Sandbox only (PAYMENT_PROVIDER=mock): simulates the gateway callback
  const simulate = async () => { try { await api(`/webhooks/mock-settle`, { method: "POST", body: { orderId: params.id } }); reload(); } catch (e: any) { setErr(e.message); } };
  return (
    <div className="space-y-4">
      <h1 className="text-xl font-bold">Pesanan {o.code} <Badge tone={statusTone(o.status)}>{o.status}</Badge></h1><ErrorNote msg={err} />
      <div className="card">
        {o.items.map((i: any, k: number) => <div key={k} className="flex justify-between text-sm"><span>{i.name} (G{i.grade}) × {Number(i.qty)}</span><span>{idr(i.line_total)}</span></div>)}
        <div className="mt-2 border-t pt-2 text-right font-bold">{idr(o.total)}</div>
      </div>
      {o.status === "pending_payment" && (
        <div className="card space-y-2 border-amber-300 bg-amber-50">
          <p className="font-semibold">Instruksi Pembayaran ({p.method})</p>
          <p className="font-mono text-lg">{p.pay_code}</p>
          <p className="text-xs text-gray-600">Bayar sebelum {new Date(p.expires_at).toLocaleString("id-ID")}</p>
          <button className="btn-outline" onClick={simulate}>Simulasikan pembayaran (sandbox)</button>
        </div>)}
      <Link href="/buyer" className="btn-outline">Kembali</Link>
    </div>
  );
}
