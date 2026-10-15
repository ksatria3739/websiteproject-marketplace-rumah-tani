"use client";
import { useState } from "react";
import { api } from "@/lib/api";
import { useFetch } from "@/lib/hooks";
import { Badge, ErrorNote, statusTone } from "@/components/ui";

const NEXT: Record<string, string> = { preparing: "picked_up", picked_up: "in_transit", in_transit: "arrived_hub", arrived_hub: "out_for_delivery", out_for_delivery: "delivered" };

export default function Logistics() {
  const { data: meta } = useFetch<any>("/public/meta");
  const { data: vehicles, reload: rv } = useFetch<any[]>("/logistics/vehicles");
  const { data: shipments, reload: rs } = useFetch<any[]>("/logistics/shipments");
  const { data: orders, reload: ro } = useFetch<any[]>("/orders");
  const { data: routes, reload: rr } = useFetch<any[]>("/logistics/routes");
  const [err, setErr] = useState<string | null>(null);
  const [veh, setVeh] = useState({ plate: "", type: "Pickup", capacityKg: "1000", driverName: "" });
  const [route, setRoute] = useState({ originRegionId: "", destRegionId: "", baseFee: "", feePerKgKm: "" });
  const [pick, setPick] = useState<Record<string, string>>({});
  const run = async (fn: () => Promise<any>, after: (() => void)[]) => { try { await fn(); setErr(null); after.forEach(f => f()); } catch (e: any) { setErr(e.message); } };
  const shippedIds = new Set(shipments?.map(s => s.order_code));
  const pending = orders?.filter(o => ["paid", "processing"].includes(o.status) && !shippedIds.has(o.code)) ?? [];

  return (
    <div className="space-y-6">
      <h1 className="text-xl font-bold">Logistik & Armada Wilayah</h1><ErrorNote msg={err} />

      <section className="card">
        <h2 className="mb-2 font-semibold">Pesanan siap dikirim</h2>
        {pending.length === 0 && <p className="text-sm text-gray-500">Tidak ada.</p>}
        {pending.map(o => (
          <div key={o.id} className="flex flex-wrap items-center gap-2 border-t py-2">
            <span className="font-mono text-xs">{o.code}</span><span className="text-sm">{Number(o.total_weight_kg)} kg</span>
            <select className="input ml-auto w-48" value={pick[o.id] ?? ""} onChange={e => setPick({ ...pick, [o.id]: e.target.value })}>
              <option value="">Pilih armada</option>{vehicles?.map(v => <option key={v.id} value={v.id}>{v.plate} ({Number(v.capacity_kg)}kg)</option>)}</select>
            <button className="btn" disabled={!pick[o.id]} onClick={() => run(() => api("/logistics/shipments", { method: "POST", body: { orderId: o.id, vehicleId: pick[o.id] } }), [rs, ro])}>Alokasikan</button>
          </div>))}
      </section>

      <section className="card overflow-x-auto p-0">
        <h2 className="p-3 font-semibold">Pengiriman aktif</h2>
        <table className="w-full"><thead><tr><th className="th">Order</th><th className="th">Rute</th><th className="th">Armada</th><th className="th">Status</th><th className="th" /></tr></thead>
          <tbody className="divide-y">{shipments?.map(s => (
            <tr key={s.id}><td className="td font-mono text-xs">{s.order_code}</td><td className="td">{s.origin} → {s.destination}</td><td className="td">{s.plate}</td>
              <td className="td"><Badge tone={statusTone(s.status)}>{s.status}</Badge></td>
              <td className="td">{NEXT[s.status] && <button className="btn" onClick={() => run(() => api(`/logistics/shipments/${s.id}/status`, { method: "PATCH", body: { status: NEXT[s.status] } }), [rs, ro])}>→ {NEXT[s.status]}</button>}</td></tr>))}
          </tbody></table>
      </section>

      <div className="grid gap-4 md:grid-cols-2">
        <section className="card space-y-2">
          <h2 className="font-semibold">Armada ({vehicles?.length ?? 0})</h2>
          {vehicles?.map(v => <p key={v.id} className="text-sm">{v.plate} · {v.type} · {Number(v.capacity_kg)}kg · {v.driver_name}</p>)}
          <input className="input" placeholder="Plat nomor" value={veh.plate} onChange={e => setVeh({ ...veh, plate: e.target.value })} />
          <div className="grid grid-cols-3 gap-2">
            <input className="input" placeholder="Tipe" value={veh.type} onChange={e => setVeh({ ...veh, type: e.target.value })} />
            <input className="input" type="number" placeholder="Kapasitas kg" value={veh.capacityKg} onChange={e => setVeh({ ...veh, capacityKg: e.target.value })} />
            <input className="input" placeholder="Pengemudi" value={veh.driverName} onChange={e => setVeh({ ...veh, driverName: e.target.value })} />
          </div>
          <button className="btn" onClick={() => run(() => api("/logistics/vehicles", { method: "POST", body: { ...veh, capacityKg: Number(veh.capacityKg), driverName: veh.driverName || undefined } }), [rv])}>Tambah armada</button>
        </section>
        <section className="card space-y-2">
          <h2 className="font-semibold">Rute & tarif wilayah</h2>
          {routes?.map(r => <p key={r.id} className="text-sm">{r.origin} → {r.destination}: dasar {Number(r.base_fee)} · {Number(r.fee_per_kg_km)}/kg·km</p>)}
          <div className="grid grid-cols-2 gap-2">
            {(["originRegionId", "destRegionId"] as const).map(k => (
              <select key={k} className="input" value={route[k]} onChange={e => setRoute({ ...route, [k]: e.target.value })}>
                <option value="">{k === "originRegionId" ? "Asal" : "Tujuan"}</option>{meta?.regions.map((r: any) => <option key={r.id} value={r.id}>{r.name}</option>)}</select>))}
            <input className="input" type="number" placeholder="Biaya dasar" value={route.baseFee} onChange={e => setRoute({ ...route, baseFee: e.target.value })} />
            <input className="input" type="number" step="0.01" placeholder="Tarif/kg·km" value={route.feePerKgKm} onChange={e => setRoute({ ...route, feePerKgKm: e.target.value })} />
          </div>
          <button className="btn" onClick={() => run(() => api("/logistics/routes", { method: "PUT", body: { originRegionId: Number(route.originRegionId), destRegionId: Number(route.destRegionId), baseFee: Number(route.baseFee || 0), feePerKgKm: Number(route.feePerKgKm || 0) } }), [rr])}>Simpan rute</button>
        </section>
      </div>
    </div>
  );
}
