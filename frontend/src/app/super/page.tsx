"use client";
import { useState } from "react";
import { Bar, BarChart, CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import { API, idr, useAuth } from "@/lib/api";
import { useFetch } from "@/lib/hooks";
import { ErrorNote } from "@/components/ui";

export default function Analytics() {
  const [groupBy, setGroupBy] = useState("day");
  const [from, setFrom] = useState(""); const [to, setTo] = useState("");
  const qs = new URLSearchParams({ groupBy, ...(from && { from }), ...(to && { to }) }).toString();
  const { data, error } = useFetch<any>(`/admin/analytics?${qs}`);
  const token = useAuth(s => s.accessToken);
  const [err, setErr] = useState<string | null>(null);

  // Authenticated file download => blob => temporary link
  const download = async (format: "pdf" | "xlsx") => {
    const r = await fetch(`${API}/admin/reports/export?format=${format}&${qs}`, { headers: { Authorization: `Bearer ${token}` } });
    if (!r.ok) return setErr("Gagal mengekspor laporan");
    const a = document.createElement("a"); a.href = URL.createObjectURL(await r.blob()); a.download = `rumah-tani-report.${format}`; a.click();
  };
  const p = data?.profit;
  const trend = data?.trend.map((t: any) => ({ ...t, gmv: Number(t.gmv), revenue: Number(t.revenue), period: String(t.period).slice(0, 10) })) ?? [];
  const top = data?.top.map((t: any) => ({ ...t, qty: Number(t.qty) })) ?? [];
  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-2">
        <h1 className="mr-auto text-xl font-bold">Executive Analytics</h1>
        <input type="date" className="input w-40" value={from} onChange={e => setFrom(e.target.value)} />
        <input type="date" className="input w-40" value={to} onChange={e => setTo(e.target.value)} />
        <select className="input w-28" value={groupBy} onChange={e => setGroupBy(e.target.value)}><option value="day">Harian</option><option value="week">Mingguan</option><option value="month">Bulanan</option></select>
        <button className="btn" onClick={() => download("xlsx")}>⬇ Excel</button><button className="btn" onClick={() => download("pdf")}>⬇ PDF</button>
      </div>
      <ErrorNote msg={error ?? err} />
      {p && <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
        {[["Penjualan kotor", p.gross_sales], ["Pendapatan platform", p.revenue], ["PPN terkumpul", p.tax_collected], ["Laba operasional", p.operating_profit]].map(([k, v]) => (
          <div key={k as string} className="card"><p className="text-xs text-gray-500">{k}</p><p className="text-lg font-bold">{idr(v as number)}</p></div>))}
      </div>}
      <div className="card"><h2 className="mb-2 font-semibold">Tren Transaksi (GMV)</h2>
        <div className="h-64"><ResponsiveContainer><LineChart data={trend}><CartesianGrid strokeDasharray="3 3" /><XAxis dataKey="period" fontSize={11} /><YAxis fontSize={11} tickFormatter={v => `${v / 1e6}jt`} /><Tooltip formatter={(v: number) => idr(v)} />
          <Line dataKey="gmv" stroke="#178a47" strokeWidth={2} /><Line dataKey="revenue" stroke="#f59e0b" strokeWidth={2} /></LineChart></ResponsiveContainer></div></div>
      <div className="card"><h2 className="mb-2 font-semibold">Komoditas Perputaran Tertinggi (kg)</h2>
        <div className="h-64"><ResponsiveContainer><BarChart data={top}><CartesianGrid strokeDasharray="3 3" /><XAxis dataKey="name" fontSize={11} /><YAxis fontSize={11} /><Tooltip /><Bar dataKey="qty" fill="#22a559" /></BarChart></ResponsiveContainer></div></div>
    </div>
  );
}
