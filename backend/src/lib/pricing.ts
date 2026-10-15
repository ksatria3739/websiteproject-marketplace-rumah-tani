import { PoolClient } from "pg";

export interface Settings { tax_rate: number; admin_fee_rate: number; admin_fee_min: number; ship_base_fee: number; ship_fee_per_kg_km: number; payment_ttl_minutes: number }

const R = 6371;
export function haversineKm(a: { lat: number; lng: number }, b: { lat: number; lng: number }) {
  const rad = (d: number) => (d * Math.PI) / 180;
  const dLat = rad(b.lat - a.lat), dLng = rad(b.lng - a.lng);
  const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLng / 2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(h));
}
const round = (n: number) => Math.round(n * 100) / 100;

/**
 * Shipping = base + weightKg * distanceKm * perKgKm, rounded up to the nearest Rp500.
 * Road distance is approximated as 1.3x straight line. A tengkulak route can override the rates.
 */
export function shippingCost(weightKg: number, distanceKm: number, s: Pick<Settings, "ship_base_fee" | "ship_fee_per_kg_km">,
  route?: { base_fee: number; fee_per_kg_km: number } | null) {
  const base = route && route.base_fee > 0 ? route.base_fee : s.ship_base_fee;
  const rate = route && route.fee_per_kg_km > 0 ? route.fee_per_kg_km : s.ship_fee_per_kg_km;
  const raw = base + weightKg * distanceKm * 1.3 * rate;
  return Math.ceil(raw / 500) * 500;
}

export function totals(subtotal: number, shipping: number, s: Settings) {
  const tax = round(subtotal * s.tax_rate);
  const adminFee = round(Math.max(subtotal * s.admin_fee_rate, s.admin_fee_min));
  return { subtotal: round(subtotal), tax, adminFee, shipping, total: round(subtotal + tax + adminFee + shipping) };
}

export async function regionDistanceKm(c: { query: PoolClient["query"] }, a: number, b: number) {
  if (a === b) return 20; // intra-region delivery
  const r = (await c.query("SELECT id,lat,lng FROM regions WHERE id = ANY($1)", [[a, b]])).rows;
  const A = r.find((x: any) => x.id === a), B = r.find((x: any) => x.id === b);
  if (!A || !B) throw new Error("Unknown region");
  return haversineKm({ lat: +A.lat, lng: +A.lng }, { lat: +B.lat, lng: +B.lng });
}
