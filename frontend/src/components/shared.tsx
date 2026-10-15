"use client";
import { useState } from "react";
import { api, idr, useAuth } from "@/lib/api";
import { useFetch } from "@/lib/hooks";
import { Badge, ErrorNote, statusTone } from "./ui";

/** KYC banner + upload form (petani, tengkulak, B2B buyers). */
export function KycBanner() {
  const user = useAuth(s => s.user)!;
  const { data: me, reload } = useFetch<any>("/auth/me");
  const [files, setFiles] = useState<FileList | null>(null);
  const [err, setErr] = useState<string | null>(null);
  const needs = user.role === "petani" || user.role === "admin_tengkulak" || (user.role === "pembeli" && user.buyerType === "b2b");
  if (!needs || !me || me.kyc_status === "approved") return null;
  const upload = async () => {
    if (!files?.length) return;
    const fd = new FormData();
    [...files].forEach((f, i) => { fd.append("files", f); fd.append("docTypes", i === 0 ? "ktp" : "pendukung"); });
    try { await api("/kyc/documents", { method: "POST", form: fd }); setErr(null); reload(); } catch (e: any) { setErr(e.message); }
  };
  return (
    <div className="card mb-4 border-amber-300 bg-amber-50">
      <div className="flex items-center gap-2"><b>Verifikasi akun (KYC)</b><Badge tone={statusTone(me.kyc_status)}>{me.kyc_status}</Badge></div>
      {me.kyc_note && <p className="text-sm text-red-700">Catatan: {me.kyc_note}</p>}
      {me.kyc_status === "pending" ? <p className="text-sm text-gray-600">Dokumen sedang ditinjau.</p> : (
        <div className="mt-2 flex flex-col gap-2 sm:flex-row">
          <input type="file" multiple accept="image/*,application/pdf" onChange={e => setFiles(e.target.files)} className="text-sm" />
          <button className="btn" onClick={upload}>Unggah KTP / NIB / dokumen</button>
        </div>)}
      <ErrorNote msg={err} />
    </div>
  );
}

/** Negotiation inbox — same component for buyer (B2B), petani, tengkulak. Each side counters or accepts. */
export function NegotiationList() {
  const { user } = useAuth();
  const { data, reload, error } = useFetch<any[]>("/negotiations");
  const [price, setPrice] = useState<Record<string, string>>({});
  const [err, setErr] = useState<string | null>(null);
  const act = async (fn: () => Promise<any>) => { try { await fn(); setErr(null); reload(); } catch (e: any) { setErr(e.message); } };
  return (
    <div className="space-y-3">
      <ErrorNote msg={error ?? err} />
      {data?.length === 0 && <p className="text-gray-500">Belum ada negosiasi.</p>}
      {data?.map(n => {
        const last = n.offers[n.offers.length - 1];
        const myTurn = n.status === "open" && last.from !== user!.id;
        return (
          <div key={n.id} className="card">
            <div className="flex items-center justify-between">
              <b>{n.variant_name} (Grade {n.grade}) × {Number(n.qty)}</b><Badge tone={statusTone(n.status)}>{n.status}</Badge>
            </div>
            <p className="text-xs text-gray-500">Harga list {idr(n.list_price)}{n.agreed_price && ` · Disepakati ${idr(n.agreed_price)}`}</p>
            <ul className="mt-2 space-y-1 text-sm">{n.offers.map((o: any, i: number) => (
              <li key={i} className={o.from === user!.id ? "text-right text-brand-700" : ""}>{idr(o.price)} {o.msg && `— ${o.msg}`}</li>))}</ul>
            {n.status === "open" && (
              <div className="mt-3 flex flex-wrap gap-2">
                {myTurn && <button className="btn" onClick={() => act(() => api(`/negotiations/${n.id}/respond`, { method: "POST", body: { accept: true } }))}>Setujui {idr(last.price)}</button>}
                {myTurn && <>
                  <input className="input w-32" placeholder="Harga tandingan" value={price[n.id] ?? ""} onChange={e => setPrice({ ...price, [n.id]: e.target.value })} />
                  <button className="btn-outline" onClick={() => act(() => api(`/negotiations/${n.id}/offers`, { method: "POST", body: { unitPrice: Number(price[n.id]) } }))}>Kirim tawaran</button>
                </>}
                {!myTurn && <span className="text-sm text-gray-500">Menunggu respon pihak lain…</span>}
                <button className="btn-outline" onClick={() => act(() => api(`/negotiations/${n.id}/respond`, { method: "POST", body: { accept: false } }))}>Tolak / Batal</button>
              </div>)}
            {n.status === "accepted" && user!.role === "pembeli" && (
              <button className="btn mt-3" onClick={() => act(() => api("/cart/items", { method: "PUT", body: { variantId: n.variant_id, qty: Number(n.qty), negotiationId: n.id } }))}>Masukkan ke keranjang (harga nego)</button>)}
          </div>);
      })}
    </div>
  );
}

export function OrdersTable({ onAccept }: { onAccept?: boolean }) {
  const { data, reload, error } = useFetch<any[]>("/orders");
  const [err, setErr] = useState<string | null>(null);
  return (
    <div className="card overflow-x-auto p-0">
      <ErrorNote msg={error ?? err} />
      <table className="w-full"><thead><tr><th className="th">Kode</th><th className="th">Pembeli</th><th className="th">Total</th><th className="th">Status</th><th className="th" /></tr></thead>
        <tbody className="divide-y">{data?.map(o => (
          <tr key={o.id}><td className="td font-mono text-xs">{o.code}</td><td className="td">{o.buyer}</td><td className="td">{idr(o.total)}</td>
            <td className="td"><Badge tone={statusTone(o.status)}>{o.status}</Badge></td>
            <td className="td">{onAccept && o.status === "paid" && <button className="btn" onClick={async () => { try { await api(`/orders/${o.id}/accept`, { method: "POST" }); reload(); } catch (e: any) { setErr(e.message); } }}>Proses</button>}</td></tr>))}
        </tbody></table>
    </div>
  );
}
