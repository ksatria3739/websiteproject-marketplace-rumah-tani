import { Router } from "express";
import crypto from "crypto";
import { z } from "zod";
import { getSettings, pool, query, tx } from "../config/db";
import { HttpError, requireRole, validate, wrap } from "../middleware/security";
import { regionDistanceKm, shippingCost } from "../lib/pricing";

export const logisticsRouter = Router();

// Public estimator (guests may preview shipping costs)
logisticsRouter.get("/estimate", validate(z.object({
  weightKg: z.coerce.number().positive(), originRegionId: z.coerce.number(), destRegionId: z.coerce.number(),
}), "query"), wrap(async (req, res) => {
  const q = req.query as any;
  const s = await getSettings() as any;
  const km = await regionDistanceKm(pool, q.originRegionId, q.destRegionId);
  const [route] = await query("SELECT base_fee,fee_per_kg_km FROM routes WHERE origin_region_id=$1 AND dest_region_id=$2 LIMIT 1", [q.originRegionId, q.destRegionId]);
  res.json({ distanceKm: Math.round(km), cost: shippingCost(q.weightKg, km, s, route && { base_fee: +route.base_fee, fee_per_kg_km: +route.fee_per_kg_km }) });
}));

// Tracking by code (public, code is unguessable)
logisticsRouter.get("/track/:code", wrap(async (req, res) => {
  const [s] = await query(
    `SELECT s.id,s.status,s.tracking_code,s.last_lat,s.last_lng,s.updated_at,v.plate,v.driver_name,
            ro.name origin,rd.name destination,ro.lat o_lat,ro.lng o_lng,rd.lat d_lat,rd.lng d_lng
       FROM shipments s JOIN orders o ON o.id=s.order_id LEFT JOIN vehicles v ON v.id=s.vehicle_id
       JOIN regions ro ON ro.id=o.region_id JOIN regions rd ON rd.id=o.dest_region_id WHERE s.tracking_code=$1`, [req.params.code]);
  if (!s) throw new HttpError(404, "Tracking code not found");
  const events = await query("SELECT status,note,lat,lng,created_at FROM shipment_events WHERE shipment_id=$1 ORDER BY id", [s.id]);
  res.json({ ...s, events });
}));

const ops = requireRole("admin_tengkulak", "super_admin");

// ---- Fleet ----
logisticsRouter.get("/vehicles", ops, wrap(async (req, res) =>
  res.json(await query("SELECT * FROM vehicles WHERE ($1='super_admin' OR owner_id=$2) ORDER BY plate", [req.user!.role, req.user!.id]))));

logisticsRouter.post("/vehicles", ops, validate(z.object({
  plate: z.string().min(3), type: z.string(), capacityKg: z.number().positive(), driverName: z.string().optional(),
})), wrap(async (req, res) => {
  const b = req.body;
  const [v] = await query("INSERT INTO vehicles(owner_id,plate,type,capacity_kg,driver_name) VALUES ($1,$2,$3,$4,$5) RETURNING *",
    [req.user!.id, b.plate, b.type, b.capacityKg, b.driverName ?? null]);
  res.status(201).json(v);
}));

// ---- Routes ----
logisticsRouter.get("/routes", ops, wrap(async (req, res) =>
  res.json(await query(
    `SELECT r.*, a.name origin, b.name destination FROM routes r JOIN regions a ON a.id=r.origin_region_id JOIN regions b ON b.id=r.dest_region_id
      WHERE ($1='super_admin' OR r.owner_id=$2)`, [req.user!.role, req.user!.id]))));

logisticsRouter.put("/routes", ops, validate(z.object({
  originRegionId: z.number().int(), destRegionId: z.number().int(), baseFee: z.number().min(0).default(0), feePerKgKm: z.number().min(0).default(0),
})), wrap(async (req, res) => {
  const b = req.body;
  const [r] = await query(
    `INSERT INTO routes(owner_id,origin_region_id,dest_region_id,base_fee,fee_per_kg_km) VALUES ($1,$2,$3,$4,$5)
     ON CONFLICT (owner_id,origin_region_id,dest_region_id) DO UPDATE SET base_fee=EXCLUDED.base_fee, fee_per_kg_km=EXCLUDED.fee_per_kg_km RETURNING *`,
    [req.user!.id, b.originRegionId, b.destRegionId, b.baseFee, b.feePerKgKm]);
  res.json(r);
}));

// ---- Shipments ----
const FLOW = ["preparing", "picked_up", "in_transit", "arrived_hub", "out_for_delivery", "delivered"] as const;

logisticsRouter.get("/shipments", ops, wrap(async (req, res) => {
  const u = req.user!;
  res.json(await query(
    `SELECT s.id,s.status,s.tracking_code,s.updated_at,o.code order_code,o.total_weight_kg,v.plate,v.driver_name,ro.name origin,rd.name destination
       FROM shipments s JOIN orders o ON o.id=s.order_id LEFT JOIN vehicles v ON v.id=s.vehicle_id
       JOIN regions ro ON ro.id=o.region_id JOIN regions rd ON rd.id=o.dest_region_id
      WHERE $1='super_admin' OR o.region_id=$2 ORDER BY s.updated_at DESC LIMIT 200`, [u.role, u.regionId]));
}));

/** Allocate a vehicle to a paid/processing order, enforcing fleet capacity. */
logisticsRouter.post("/shipments", ops, validate(z.object({
  orderId: z.string().uuid(), vehicleId: z.string().uuid(), routeId: z.string().uuid().optional(),
})), wrap(async (req, res) => {
  const b = req.body, u = req.user!;
  const out = await tx(async c => {
    const o = (await c.query("SELECT id,status,region_id,total_weight_kg FROM orders WHERE id=$1 FOR UPDATE", [b.orderId])).rows[0];
    if (!o) throw new HttpError(404, "Order not found");
    if (u.role === "admin_tengkulak" && o.region_id !== u.regionId) throw new HttpError(403, "Order outside your region");
    if (!["paid", "processing"].includes(o.status)) throw new HttpError(409, "Order must be paid first");
    const v = (await c.query("SELECT * FROM vehicles WHERE id=$1 FOR UPDATE", [b.vehicleId])).rows[0];
    if (!v || (u.role !== "super_admin" && v.owner_id !== u.id)) throw new HttpError(404, "Vehicle not found");
    const load = (await c.query(
      `SELECT COALESCE(SUM(o.total_weight_kg),0) w FROM shipments s JOIN orders o ON o.id=s.order_id WHERE s.vehicle_id=$1 AND s.status<>'delivered'`, [v.id])).rows[0].w;
    if (+load + +o.total_weight_kg > +v.capacity_kg)
      throw new HttpError(409, `Vehicle capacity exceeded (${load}+${o.total_weight_kg} > ${v.capacity_kg} kg)`);
    const code = "TRK" + crypto.randomBytes(5).toString("hex").toUpperCase();
    const s = (await c.query("INSERT INTO shipments(order_id,vehicle_id,route_id,tracking_code) VALUES ($1,$2,$3,$4) RETURNING *",
      [o.id, v.id, b.routeId ?? null, code])).rows[0];
    await c.query("INSERT INTO shipment_events(shipment_id,status,note) VALUES ($1,'preparing','Vehicle allocated')", [s.id]);
    await c.query("UPDATE orders SET status='processing' WHERE id=$1", [o.id]);
    return s;
  });
  res.status(201).json(out);
}));

/** Step-by-step status; no skipping backwards. Delivered => order delivered. */
logisticsRouter.patch("/shipments/:id/status", ops, validate(z.object({
  status: z.enum(FLOW), note: z.string().optional(), lat: z.number().optional(), lng: z.number().optional(),
})), wrap(async (req, res) => {
  const b = req.body, u = req.user!;
  await tx(async c => {
    const s = (await c.query(
      `SELECT s.*, o.region_id FROM shipments s JOIN orders o ON o.id=s.order_id WHERE s.id=$1 FOR UPDATE OF s`, [req.params.id])).rows[0];
    if (!s) throw new HttpError(404, "Shipment not found");
    if (u.role === "admin_tengkulak" && s.region_id !== u.regionId) throw new HttpError(403, "Outside your region");
    if (FLOW.indexOf(b.status) !== FLOW.indexOf(s.status) + 1) throw new HttpError(409, `Next status must be '${FLOW[FLOW.indexOf(s.status) + 1] ?? "none"}'`);
    await c.query("UPDATE shipments SET status=$2,last_lat=COALESCE($3,last_lat),last_lng=COALESCE($4,last_lng),updated_at=now() WHERE id=$1",
      [s.id, b.status, b.lat ?? null, b.lng ?? null]);
    await c.query("INSERT INTO shipment_events(shipment_id,status,note,lat,lng) VALUES ($1,$2,$3,$4,$5)", [s.id, b.status, b.note ?? null, b.lat ?? null, b.lng ?? null]);
    if (b.status === "picked_up") await c.query("UPDATE orders SET status='shipped' WHERE id=$1", [s.order_id]);
    if (b.status === "delivered") await c.query("UPDATE orders SET status='delivered' WHERE id=$1", [s.order_id]);
  });
  res.json({ ok: true });
}));
