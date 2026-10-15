"use client";
import { Suspense, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { api, homeFor, useAuth } from "@/lib/api";
import { ErrorNote } from "@/components/ui";

function Form() {
  const [email, setEmail] = useState(""); const [password, setPassword] = useState("");
  const [err, setErr] = useState<string | null>(null); const [busy, setBusy] = useState(false);
  const setSession = useAuth(s => s.setSession); const router = useRouter(); const next = useSearchParams().get("next");
  const submit = async (e: React.FormEvent) => {
    e.preventDefault(); setBusy(true); setErr(null);
    try {
      const r = await api("/auth/login", { method: "POST", body: { email, password } });
      setSession(r.user, r.accessToken, r.refreshToken);
      router.replace(next && next.startsWith("/") ? next : homeFor(r.user.role));
    } catch (e: any) { setErr(e.message); } finally { setBusy(false); }
  };
  return (
    <form onSubmit={submit} className="card mx-auto mt-10 max-w-sm space-y-3">
      <h1 className="text-xl font-bold">Masuk</h1>
      <ErrorNote msg={err} />
      <div><label className="label">Email</label><input className="input" type="email" required value={email} onChange={e => setEmail(e.target.value)} /></div>
      <div><label className="label">Kata sandi</label><input className="input" type="password" required value={password} onChange={e => setPassword(e.target.value)} /></div>
      <button className="btn w-full" disabled={busy}>{busy ? "Memproses…" : "Masuk"}</button>
      <p className="text-center text-xs text-gray-500">Demo: petani@rumahtani.id / Password123</p>
    </form>
  );
}
export default function Login() { return <Suspense><Form /></Suspense>; }
