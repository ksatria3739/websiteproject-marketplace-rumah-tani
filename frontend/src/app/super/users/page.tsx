"use client";
import { useState } from "react";
import { api } from "@/lib/api";
import { useFetch } from "@/lib/hooks";
import { Badge, ErrorNote, statusTone } from "@/components/ui";

export default function Users() {
  const { data: meta } = useFetch<any>("/public/meta");
  const { data, reload, error } = useFetch<any[]>("/admin/users");
  const [err, setErr] = useState<string | null>(null);
  const [f, setF] = useState({ email: "", password: "", fullName: "", regionId: "" });
  const toggle = async (id: string, isActive: boolean) => { try { await api(`/admin/users/${id}`, { method: "PATCH", body: { isActive } }); reload(); } catch (e: any) { setErr(e.message); } };
  const create = async () => {
    try { await api("/admin/users/tengkulak", { method: "POST", body: { ...f, regionId: Number(f.regionId) } }); setF({ email: "", password: "", fullName: "", regionId: "" }); reload(); setErr(null); }
    catch (e: any) { setErr(e.message); }
  };
  return (
    <div className="space-y-4">
      <h1 className="text-xl font-bold">Manajemen Pengguna</h1><ErrorNote msg={error ?? err} />
      <div className="card grid gap-2 sm:grid-cols-5">
        <input className="input" placeholder="Nama tengkulak" value={f.fullName} onChange={e => setF({ ...f, fullName: e.target.value })} />
        <input className="input" placeholder="Email" value={f.email} onChange={e => setF({ ...f, email: e.target.value })} />
        <input className="input" type="password" placeholder="Sandi awal" value={f.password} onChange={e => setF({ ...f, password: e.target.value })} />
        <select className="input" value={f.regionId} onChange={e => setF({ ...f, regionId: e.target.value })}><option value="">Wilayah</option>{meta?.regions.map((r: any) => <option key={r.id} value={r.id}>{r.name}</option>)}</select>
        <button className="btn" onClick={create}>+ Tengkulak</button>
      </div>
      <div className="card overflow-x-auto p-0"><table className="w-full"><thead><tr><th className="th">Nama</th><th className="th">Role</th><th className="th">KYC</th><th className="th">Aktif</th><th className="th" /></tr></thead>
        <tbody className="divide-y">{data?.map(u => (
          <tr key={u.id}><td className="td">{u.full_name}<p className="text-xs text-gray-500">{u.email}</p></td><td className="td">{u.role}{u.buyer_type && ` (${u.buyer_type})`}</td>
            <td className="td"><Badge tone={statusTone(u.kyc_status)}>{u.kyc_status}</Badge></td><td className="td">{u.is_active ? "Ya" : "Nonaktif"}</td>
            <td className="td"><button className="btn-outline" onClick={() => toggle(u.id, !u.is_active)}>{u.is_active ? "Nonaktifkan" : "Aktifkan"}</button></td></tr>))}</tbody></table></div>
    </div>
  );
}
