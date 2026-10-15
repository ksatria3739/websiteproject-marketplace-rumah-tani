"use client";
import Link from "next/link";
import { useState } from "react";
import { api, idr, useAuth } from "@/lib/api";
import { ErrorNote } from "@/components/ui";

/** Guest => login CTA only (read-only). B2B => cart + price negotiation. B2C => cart. Other roles => none. */
export function BuyPanel({ variant }: { variant: { id: string; price: number; minOrder: number; available: number } }) {
  const { user, ready } = useAuth();
  const [qty, setQty] = useState(variant.minOrder);
  const [offer, setOffer] = useState(Math.round(variant.price * 0.92));
  const [msg, setMsg] = useState<string | null>(null);
  const [err, setErr] = useState<string | null>(null);
  const [showNego, setShowNego] = useState(false);

  if (!ready) return null;
  if (!user) return <Link href="/login" className="btn mt-3 w-full">Masuk untuk membeli</Link>;
  if (user.role !== "pembeli") return null;

  const run = async (fn: () => Promise<any>, ok: string) => {
    setErr(null); setMsg(null);
    try { await fn(); setMsg(ok); } catch (e: any) { setErr(e.message); }
  };
  return (
    <div className="mt-3 space-y-2">
      <div className="flex items-center gap-2">
        <input type="number" min={variant.minOrder} max={variant.available} value={qty} onChange={e => setQty(Number(e.target.value))} className="input w-28" />
        <button className="btn flex-1" onClick={() => run(() => api("/cart/items", { method: "PUT", body: { variantId: variant.id, qty } }), "Ditambahkan ke keranjang")}>
          + Keranjang ({idr(qty * variant.price)})
        </button>
        {user.buyerType === "b2b" && <button className="btn-outline" onClick={() => setShowNego(s => !s)}>Nego</button>}
      </div>
      {showNego && (
        <div className="flex items-center gap-2 rounded-lg bg-amber-50 p-2">
          <input type="number" value={offer} onChange={e => setOffer(Number(e.target.value))} className="input w-32" />
          <span className="text-xs text-gray-600">/unit untuk {qty}</span>
          <button className="btn ml-auto" onClick={() => run(() => api("/negotiations", { method: "POST", body: { variantId: variant.id, qty, unitPrice: offer } }), "Penawaran terkirim")}>Ajukan</button>
        </div>)}
      {msg && <p className="text-sm text-green-700">{msg}</p>}<ErrorNote msg={err} />
    </div>
  );
}
