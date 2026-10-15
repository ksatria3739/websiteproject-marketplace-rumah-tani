"use client";
import { useState } from "react";
import { useRouter } from "next/navigation";
import { api, homeFor, useAuth } from "@/lib/api";
import { useFetch } from "@/lib/hooks";
import { ErrorNote } from "@/components/ui";

export default function Register() {
  const { data: meta } = useFetch<any>("/public/meta");
  const [f, setF] = useState<any>({ role: "pembeli", buyerType: "b2c", email: "", password: "", fullName: "", phone: "", companyName: "", regionId: "" });
  const [err, setErr] = useState<string | null>(null);
  const setSession = useAuth(s => s.setSession); const router = useRouter();
  const set = (k: string) => (e: any) => setF({ ...f, [k]: e.target.value });
  const submit = async (e: React.FormEvent) => {
    e.preventDefault(); setErr(null);
    const body: any = { ...f, regionId: f.regionId ? Number(f.regionId) : undefined, companyName: f.companyName || undefined, phone: f.phone || undefined };
    if (f.role === "petani") delete body.buyerType;
    try {
      const r = await api("/auth/register", { method: "POST", body });
      setSession({ ...r.user, fullName: f.fullName }, r.accessToken, r.refreshToken);
      router.replace(homeFor(r.user.role));
    } catch (e: any) { setErr(e.details ? "Periksa kembali isian (kata sandi min. 8 karakter + angka)" : e.message); }
  };
  return (
    <form onSubmit={submit} className="card mx-auto mt-8 max-w-md space-y-3">
      <h1 className="text-xl font-bold">Daftar Akun</h1><ErrorNote msg={err} />
      <div className="grid grid-cols-2 gap-2">
        {[["pembeli", "Pembeli"], ["petani", "Petani"]].map(([v, l]) => (
          <button type="button" key={v} onClick={() => setF({ ...f, role: v })} className={f.role === v ? "btn" : "btn-outline"}>{l}</button>))}
      </div>
      {f.role === "pembeli" && (
        <select className="input" value={f.buyerType} onChange={set("buyerType")}><option value="b2c">Umum (B2C)</option><option value="b2b">Korporasi / Agen (B2B)</option></select>)}
      <input className="input" placeholder="Nama lengkap" required value={f.fullName} onChange={set("fullName")} />
      {f.role === "pembeli" && f.buyerType === "b2b" && <input className="input" placeholder="Nama perusahaan" value={f.companyName} onChange={set("companyName")} />}
      <input className="input" type="email" placeholder="Email" required value={f.email} onChange={set("email")} />
      <input className="input" placeholder="No. HP" value={f.phone} onChange={set("phone")} />
      {f.role === "petani" && (
        <select className="input" required value={f.regionId} onChange={set("regionId")}>
          <option value="">Pilih wilayah</option>{meta?.regions.map((r: any) => <option key={r.id} value={r.id}>{r.name}</option>)}</select>)}
      <input className="input" type="password" placeholder="Kata sandi (min. 8, huruf + angka)" required value={f.password} onChange={set("password")} />
      <button className="btn w-full">Daftar</button>
      <p className="text-xs text-gray-500">Petani & pembeli B2B perlu verifikasi KYC sebelum bertransaksi.</p>
    </form>
  );
}
