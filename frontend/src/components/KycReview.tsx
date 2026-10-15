"use client";
import { useState } from "react";
import { API, api, useAuth } from "@/lib/api";
import { useFetch } from "@/lib/hooks";
import { ErrorNote } from "@/components/ui";

/** Shared KYC review table (tengkulak: farmers in region; super admin: everyone). */
export function KycReview() {
  const { data, reload, error } = useFetch<any[]>("/kyc/pending");
  const token = useAuth(s => s.accessToken);
  const [err, setErr] = useState<string | null>(null);
  const review = async (id: string, approve: boolean) => {
    const note = approve ? undefined : prompt("Alasan penolakan?") ?? undefined;
    try { await api(`/kyc/${id}/review`, { method: "POST", body: { approve, note } }); reload(); } catch (e: any) { setErr(e.message); }
  };
  // KYC files are protected: fetch with the bearer token, open as blob
  const open = async (file: string) => {
    const r = await fetch(`${API}/kyc/file/${file}`, { headers: { Authorization: `Bearer ${token}` } });
    if (r.ok) window.open(URL.createObjectURL(await r.blob()), "_blank"); else setErr("Gagal membuka dokumen");
  };
  return (
    <div className="space-y-3">
      <ErrorNote msg={error ?? err} />
      {data?.length === 0 && <p className="text-gray-500">Tidak ada pengajuan KYC.</p>}
      {data?.map(u => (
        <div key={u.id} className="card flex flex-wrap items-center justify-between gap-2">
          <div><b>{u.full_name}</b> <span className="text-xs text-gray-500">{u.role}{u.buyer_type && ` Â· ${u.buyer_type}`} Â· {u.email}</span>
            <div className="mt-1 flex gap-2">{u.docs?.map((d: any, i: number) => <button key={i} className="text-sm text-brand-700 underline" onClick={() => open(d.file)}>{d.type}</button>)}</div></div>
          <div className="flex gap-2"><button className="btn" onClick={() => review(u.id, true)}>Setujui</button><button className="btn-outline" onClick={() => review(u.id, false)}>Tolak</button></div>
        </div>))}
    </div>
  );
}

