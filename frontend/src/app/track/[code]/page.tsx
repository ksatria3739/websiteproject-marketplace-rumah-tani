"use client";
import { useParams } from "next/navigation";
import { useFetch } from "@/lib/hooks";
import { ErrorNote } from "@/components/ui";

const STEPS = ["preparing", "picked_up", "in_transit", "arrived_hub", "out_for_delivery", "delivered"];
const LABEL: Record<string, string> = { preparing: "Disiapkan", picked_up: "Diambil kurir", in_transit: "Dalam perjalanan", arrived_hub: "Tiba di hub", out_for_delivery: "Menuju alamat", delivered: "Diterima" };

/** Visual tracking: progress stepper + simple route map (origin → truck → destination). Public by tracking code. */
export default function Track() {
  const { code } = useParams<{ code: string }>();
  const { data: t, error } = useFetch<any>(`/logistics/track/${code}`);
  if (!t) return <div className="mx-auto max-w-xl p-6"><ErrorNote msg={error} /></div>;
  const idx = STEPS.indexOf(t.status);
  const pct = t.last_lat && t.last_lng ? progress(t) : idx / (STEPS.length - 1);
  return (
    <div className="mx-auto max-w-xl space-y-4 px-4 py-6">
      <h1 className="text-xl font-bold">Lacak {t.tracking_code}</h1>
      <div className="card">
        <div className="mb-2 flex justify-between text-sm font-medium"><span>{t.origin}</span><span>{t.destination}</span></div>
        <div className="relative h-2 rounded-full bg-gray-200">
          <div className="h-2 rounded-full bg-brand-500 transition-all" style={{ width: `${Math.min(100, pct * 100)}%` }} />
          <span className="absolute -top-3 text-2xl transition-all" style={{ left: `calc(${Math.min(100, pct * 100)}% - 14px)` }}>🚚</span>
        </div>
        {t.plate && <p className="mt-4 text-xs text-gray-500">Armada {t.plate} · Pengemudi {t.driver_name ?? "-"}</p>}
      </div>
      <ol className="card space-y-3">
        {STEPS.map((s, i) => {
          const ev = t.events.find((e: any) => e.status === s);
          return (<li key={s} className={`flex gap-3 ${i <= idx ? "" : "opacity-40"}`}>
            <span className={`mt-1 h-3 w-3 rounded-full ${i <= idx ? "bg-brand-500" : "bg-gray-300"}`} />
            <div><p className="font-medium">{LABEL[s]}</p>{ev && <p className="text-xs text-gray-500">{new Date(ev.created_at).toLocaleString("id-ID")} {ev.note}</p>}</div></li>);
        })}
      </ol>
    </div>
  );
}

function progress(t: any) {
  const d = (a: number, b: number, c: number, e: number) => Math.hypot(a - c, b - e);
  const total = d(+t.o_lat, +t.o_lng, +t.d_lat, +t.d_lng) || 1;
  return Math.min(1, d(+t.o_lat, +t.o_lng, +t.last_lat, +t.last_lng) / total);
}
