"use client";
import { useState } from "react";
import { useRouter } from "next/navigation";
import { api, idr } from "@/lib/api";
import { useFetch } from "@/lib/hooks";
import { ErrorNote } from "@/components/ui";

const METHODS = [
  ["va_bca", "VA BCA"], ["va_bni", "VA BNI"], ["va_bri", "VA BRI"], ["va_mandiri", "VA Mandiri"],
  ["gopay", "GoPay"], ["ovo", "OVO"], ["bank_transfer", "Transfer Bank"],
];

/** Multi-vendor cart: grouped per seller with weight, shipping, tax, admin fee; single payment for all groups. */
export default function CartPage() {
  const { data: meta } = useFetch<any>("/public/meta");
  const [dest, setDest] = useState("");
  const [address, setAddress] = useState("");
  const [method, setMethod] = useState("va_bca");
  const { data: cart, reload, error } = useFetch<any>(`/cart${dest ? `?destRegionId=${dest}` : ""}`);
  const [err, setErr] = useState<string | null>(null); const [busy, setBusy] = useState(false);
  const router = useRouter();

  const remove = async (id: string) => { await api(`/cart/items/${id}`, { method: "DELETE" }); reload(); };
  const checkout = async () => {
    setBusy(true); setErr(null);
    try {
      const r = await api("/checkout", { method: "POST", body: { destRegionId: Number(dest), shipAddress: address, method } });
      router.push(`/buyer/orders/${r.orders[0].id}`);
    } catch (e: any) { setErr(e.message); } finally { setBusy(false); }
  };
  const groups: any[] = cart?.groups ?? [];
  const rawItems: any[] = cart?.items ?? [];
  return (
    <div className="space-y-4">
      <h1 className="text-xl font-bold">Keranjang & Checkout</h1><ErrorNote msg={error ?? err} />
      <div className="card grid gap-3 sm:grid-cols-2">
        <select className="input" value={dest} onChange={e => setDest(e.target.value)}>
          <option value="">Pilih wilayah tujuan (untuk ongkir)</option>{meta?.regions.map((r: any) => <option key={r.id} value={r.id}>{r.name}</option>)}</select>
        <input className="input" placeholder="Alamat pengiriman lengkap" value={address} onChange={e => setAddress(e.target.value)} />
      </div>
      {rawItems.map(i => (
        <div key={i.variantId} className="card flex justify-between"><span>{i.name} (Grade {i.grade}) × {i.qty}</span>
          <button className="text-sm text-red-600" onClick={() => remove(i.variantId)}>Hapus</button></div>))}
      {groups.map(g => (
        <div key={g.sellerId} className="card">
          <div className="flex justify-between border-b pb-2"><b>{g.sellerName}</b><span className="text-xs text-gray-500">{g.totalWeightKg} kg · {g.distanceKm} km</span></div>
          {g.items.map((i: any) => (
            <div key={i.variantId} className="flex items-center justify-between py-2 text-sm">
              <span>{i.name} (G{i.grade}) × {i.qty} @ {idr(i.unitPrice)}{i.negotiationId && <span className="ml-1 rounded bg-amber-100 px-1 text-xs">harga nego</span>}</span>
              <span>{idr(i.qty * i.unitPrice)} <button className="ml-2 text-red-600" onClick={() => remove(i.variantId)}>✕</button></span>
            </div>))}
          <dl className="mt-2 space-y-1 border-t pt-2 text-sm">
            {[["Subtotal", g.subtotal], ["Ongkir", g.shipping], ["PPN", g.tax], ["Biaya admin", g.adminFee]].map(([k, v]) => (
              <div key={k as string} className="flex justify-between"><dt className="text-gray-600">{k}</dt><dd>{idr(v as number)}</dd></div>))}
            <div className="flex justify-between font-bold"><dt>Total vendor</dt><dd>{idr(g.total)}</dd></div>
          </dl>
        </div>))}
      {groups.length === 0 && rawItems.length === 0 && <p className="text-gray-500">Keranjang kosong.</p>}
      {groups.length > 0 && (
        <div className="card space-y-3">
          <p className="font-semibold">Metode Pembayaran</p>
          <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
            {METHODS.map(([v, l]) => <button key={v} type="button" onClick={() => setMethod(v)} className={method === v ? "btn" : "btn-outline"}>{l}</button>)}
          </div>
          <div className="flex items-center justify-between text-lg font-bold"><span>Total Bayar</span><span className="text-brand-700">{idr(cart.grandTotal)}</span></div>
          <button className="btn w-full py-3" disabled={busy || !address || !dest} onClick={checkout}>{busy ? "Memproses…" : "Bayar Sekarang"}</button>
        </div>)}
    </div>
  );
}
