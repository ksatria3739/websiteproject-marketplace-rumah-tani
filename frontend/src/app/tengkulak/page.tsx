"use client";
import { useState } from "react";
import { api, idr } from "@/lib/api";
import { useFetch } from "@/lib/hooks";
import { Badge, ErrorNote } from "@/components/ui";

/** Curation panel: approve/reject farmer submissions in the tengkulak's region; override grade if inspection differs. */
export default function Curation() {
  const { data, reload, error } = useFetch<any[]>("/curation/queue");
  const [err, setErr] = useState<string | null>(null);
  const [note, setNote] = useState<Record<string, string>>({});
  const decide = async (id: string, approve: boolean) => {
    try { await api(`/curation/${id}/decision`, { method: "POST", body: { approve, note: note[id] || undefined } }); setErr(null); reload(); }
    catch (e: any) { setErr(e.message); }
  };
  return (
    <div className="space-y-3">
      <h1 className="text-xl font-bold">Antrian Kurasi Komoditas</h1><ErrorNote msg={error ?? err} />
      {data?.length === 0 && <p className="text-gray-500">Tidak ada komoditas menunggu kurasi.</p>}
      <div className="card overflow-x-auto p-0">
        <table className="w-full">
          <thead><tr><th className="th">Komoditas</th><th className="th">Petani</th><th className="th">Varian</th><th className="th">Catatan</th><th className="th" /></tr></thead>
          <tbody className="divide-y">{data?.map(c => (
            <tr key={c.id}>
              <td className="td font-medium">{c.name}<p className="text-xs text-gray-500">{c.region}</p></td>
              <td className="td">{c.farmer}</td>
              <td className="td">{c.variants?.map((v: any, i: number) => <div key={i}><Badge tone="blue">G{v.grade}</Badge> {idr(v.price)} · {Number(v.stock)}kg</div>)}</td>
              <td className="td"><input className="input" placeholder="Alasan (opsional)" value={note[c.id] ?? ""} onChange={e => setNote({ ...note, [c.id]: e.target.value })} /></td>
              <td className="td whitespace-nowrap"><button className="btn mr-1" onClick={() => decide(c.id, true)}>Setujui</button><button className="btn-outline" onClick={() => decide(c.id, false)}>Tolak</button></td>
            </tr>))}</tbody>
        </table>
      </div>
    </div>
  );
}
