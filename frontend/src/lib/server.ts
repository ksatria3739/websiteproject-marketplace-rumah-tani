const API = process.env.API_INTERNAL_URL ?? process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:4000/api";

/** Server-side fetch with ISR caching for SEO pages. Never throws: returns fallback if API is down. */
export async function sget<T>(path: string, fallback: T, revalidate = 60): Promise<T> {
  try {
    const r = await fetch(`${API}${path}`, { next: { revalidate } });
    return r.ok ? ((await r.json()) as T) : fallback;
  } catch { return fallback; }
}
