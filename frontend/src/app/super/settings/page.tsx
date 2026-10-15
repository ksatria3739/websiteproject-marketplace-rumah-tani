"use client";
import { useState } from "react";
import { api } from "@/lib/api";
import { useFetch } from "@/lib/hooks";
import { ErrorNote } from "@/components/ui";

const HINT: Record<string, string> = {
  tax_rate: "PPN (0.11 = 11%)", admin_fee_rate: "Biaya admin (0.02 = 2%)", admin_fee_min: "Biaya admin minimum (Rp)",
  ship_base_fee: "Ongkir dasar (Rp)", ship_fee_per_kg_km: "Tarif per kg·km (Rp)", payment_ttl_minutes: "Batas waktu bayar (menit)",
};

export default function Settings() {
  const { data, reload, error } = useFetch<any[]>("/admin/settings");
  const [vals, setVals] = useState<Record<string, string>>({}); const [err, setErr] = useState<string | null>(null);
  const save = async (key: string) => { try { await api(`/admin/settings/${key}`, { method: "PUT", body: { value: vals[key] } }); setErr(null); reload(); } catch (e: any) { setErr(e.message); } };
  return (
    <div className="space-y-3"><h1 className="text-xl font-bold">Konfigurasi Sistem Global</h1><ErrorNote msg={error ?? err} />
      {data?.map(s => (
        <div key={s.key} className="card flex flex-wrap items-center gap-2">
          <div className="mr-auto"><p className="font-mono text-sm">{s.key}</p><p className="text-xs text-gray-500">{HINT[s.key]}</p></div>
          <input className="input w-36" defaultValue={s.value} onChange={e => setVals({ ...vals, [s.key]: e.target.value })} />
          <button className="btn" disabled={vals[s.key] === undefined} onClick={() => save(s.key)}>Simpan</button>
        </div>))}
    </div>
  );
}
