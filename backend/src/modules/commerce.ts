import { Router } from "express";
import { PoolClient } from "pg";
import crypto from "crypto";
import { z } from "zod";
import { env } from "../config/env";
import { getSettings, pool, query, tx } from "../config/db";
import { HttpError, requireKyc, requireRole, validate, wrap } from "../middleware/security";
import { regionDistanceKm, shippingCost, totals } from "../lib/pricing";
import { createCharge, verifyWebhook } from "../lib/gateway";

// ================= CART =================
export const cartRouter = Router();
cartRouter.use(requireRole("pembeli"));

cartRouter.put("/items", validate(z.object({ variantId: z.string().uuid(), qty: z.number().positive(), negotiationId: z.string().uuid().optional() })),
  wrap(async (req, res) => {
    const { variantId, qty, negotiationId } = req.body;
    const [v] = await query(
      `SELECT v.min_order, v.stock_qty - v.reserved_qty avail FROM variants v JOIN commodities c ON c.id=v.commodity_id
        WHERE v.id=$1 AND c.curation='approved' AND c.is_active`, [variantId]);
    if (!v) throw new HttpError(404, "Product unavailable");
    if (qty < +v.min_order) throw new HttpError(422, `Minimum order ${v.min_order}`);
    if (qty > +v.avail) throw new HttpError(409, `Only ${v.avail} available`);
    if (negotiationId) {
      const [n] = await query("SELECT 1 FROM negotiations WHERE id=$1 AND buyer_id=$2 AND variant_id=$3 AND status='accepted' AND expires_at>now() AND qty=$4",
        [negotiationId, req.user!.id, variantId, qty]);
      if (!n) throw new HttpError(422, "Negotiation not valid for this item/quantity");
    }
    await query(
      `INSERT INTO cart_items(user_id,variant_id,qty,negotiation_id) VALUES ($1,$2,$3,$4)
       ON CONFLICT (user_id,variant_id) DO UPDATE SET qty=EXCLUDED.qty, negotiation_id=EXCLUDED.negotiation_id`,
      [req.user!.id, variantId, qty, negotiationId ?? null]);
    res.json({ ok: true });
  }));

cartRouter.delete("/items/:variantId", wrap(async (req, res) => {
  await query("DELETE FROM cart_items WHERE user_id=$1 AND variant_id=$2", [req.user!.id, req.params.variantId]);
  res.json({ ok: true });
}));

interface Line { variantId: string; commodityId: string; name: string; grade: string; qty: number; unitPrice: number; weightKg: number; sellerId: string; sellerName: string; regionId: number; negotiationId: string | null }

/** Build cart lines, grouped per vendor. When lock=true rows are locked (FOR UPDATE) in id order => deadlock-free. */
async function loadLines(c: PoolClient, userId: string, lock: boolean): Promise<Line[]> {
  const ids = (await c.query("SELECT variant_id FROM cart_items WHERE user_id=$1 ORDER BY variant_id", [userId])).rows.map(r => r.variant_id);
  if (!ids.length) return [];
  if (lock) await c.query("SELECT id FROM variants WHERE id = ANY($1) ORDER BY id FOR UPDATE", [ids]);
  const rows = (await c.query(
    `SELECT ci.variant_id, ci.qty, ci.negotiation_id, v.grade, v.variant_name, v.price, v.min_order,
            v.weight_per_unit_kg, v.stock_qty - v.reserved_qty avail,
            c.id commodity_id, c.region_id, c.farmer_id, c.curation, c.is_active, u.full_name seller_name,
            n.agreed_price, n.status nego_status, n.expires_at nego_exp
       FROM cart_items ci JOIN variants v ON v.id=ci.variant_id JOIN commodities c ON c.id=v.commodity_id
       JOIN users u ON u.id=c.farmer_id LEFT JOIN negotiations n ON n.id=ci.negotiation_id
      WHERE ci.user_id=$1 ORDER BY ci.variant_id`, [userId])).rows;
  return rows.map(r => {
    if (r.curation !== "approved" || !r.is_active) throw new HttpError(409, `${r.variant_name} is no longer available`);
    if (+r.qty > +r.avail) throw new HttpError(409, `Insufficient stock for ${r.variant_name} (available ${r.avail})`);
    if (+r.qty < +r.min_order) throw new HttpError(409, `Minimum order for ${r.variant_name} is ${r.min_order}`);
    const dealOk = r.negotiation_id && r.nego_status === "accepted" && new Date(r.nego_exp) > new Date();
    if (r.negotiation_id && !dealOk) throw new HttpError(409, `Negotiated price for ${r.variant_name} expired`);
    return {
      variantId: r.variant_id, commodityId: r.commodity_id, name: r.variant_name, grade: r.grade, qty: +r.qty,
      unitPrice: dealOk ? +r.agreed_price : +r.price, weightKg: +r.qty * +r.weight_per_unit_kg,
      sellerId: r.farmer_id, sellerName: r.seller_name, regionId: r.region_id, negotiationId: r.negotiation_id,
    };
  });
}

async function priceGroups(c: PoolClient, lines: Line[], destRegionId: number) {
  const s = await getSettings(c) as any;
  const bySeller = new Map<string, Line[]>();
  for (const l of lines) bySeller.set(l.sellerId, [...(bySeller.get(l.sellerId) ?? []), l]);
  const groups = [];
  for (const [sellerId, ls] of bySeller) {
    const weight = ls.reduce((a, l) => a + l.weightKg, 0);
    const subtotal = ls.reduce((a, l) => a + l.qty * l.unitPrice, 0);
    const km = await regionDistanceKm(c, ls[0].regionId, destRegionId);
    const route = (await c.query("SELECT base_fee,fee_per_kg_km FROM routes WHERE origin_region_id=$1 AND dest_region_id=$2 LIMIT 1",
      [ls[0].regionId, destRegionId])).rows[0];
    const ship = shippingCost(weight, km, s, route && { base_fee: +route.base_fee, fee_per_kg_km: +route.fee_per_kg_km });
    groups.push({ sellerId, sellerName: ls[0].sellerName, originRegionId: ls[0].regionId, items: ls, totalWeightKg: weight, distanceKm: Math.round(km), ...totals(subtotal, ship, s) });
  }
  return { groups, grandTotal: Math.round(groups.reduce((a, g) => a + g.total, 0) * 100) / 100, settings: s };
}

cartRouter.get("/", wrap(async (req, res) => {
  const destRegionId = Number(req.query.destRegionId);
  const c = await pool.connect();
  try {
    const lines = await loadLines(c, req.user!.id, false);
    if (!lines.length) return res.json({ groups: [], grandTotal: 0 });
    if (!destRegionId) return res.json({ items: lines });   // shipping needs destination
    const { groups, grandTotal } = await priceGroups(c, lines, destRegionId);
    res.json({ groups, grandTotal });
  } finally { c.release(); }
}));

// ================= CHECKOUT =================
export const checkoutRouter = Router();
checkoutRouter.use(requireRole("pembeli"), requireKyc);

const METHODS = ["va_bca", "va_bni", "va_bri", "va_mandiri", "gopay", "ovo", "bank_transfer"] as const;

checkoutRouter.post("/", validate(z.object({
  destRegionId: z.number().int(), shipAddress: z.string().min(5), method: z.enum(METHODS),
})), wrap(async (req, res) => {
  const user = req.user!, b = req.body;
  const result = await tx(async c => {
    // 1) lock all variants in the cart (ordered) => concurrent checkouts serialize per-variant, never oversell
    const lines = await loadLines(c, user.id, true);
    if (!lines.length) throw new HttpError(400, "Cart is empty");
    const { groups, grandTotal, settings } = await priceGroups(c, lines, b.destRegionId);
    const ref = "RT-" + crypto.randomBytes(5).toString("hex").toUpperCase();
    const email = (await c.query("SELECT email FROM users WHERE id=$1", [user.id])).rows[0].email;
    const charge = await createCharge({ orderRef: ref, amount: grandTotal, method: b.method, customerEmail: email });
    const pay = (await c.query(
      `INSERT INTO payments(buyer_id,gateway_ref,method,amount,pay_code,expires_at)
       VALUES ($1,$2,$3,$4,$5, now() + ($6 || ' minutes')::interval) RETURNING id,expires_at`,
      [user.id, charge.gatewayRef, b.method, grandTotal, charge.payCode, String(settings.payment_ttl_minutes)])).rows[0];
    const orders: { id: string; code: string; total: number; sellerName: string }[] = [];
    for (const g of groups) {
      const code = ref + "-" + (orders.length + 1);
      const o = (await c.query(
        `INSERT INTO orders(code,payment_id,buyer_id,seller_id,region_id,dest_region_id,ship_address,subtotal,tax,admin_fee,shipping_cost,total,total_weight_kg)
         VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13) RETURNING id`,
        [code, pay.id, user.id, g.sellerId, g.originRegionId, b.destRegionId, b.shipAddress, g.subtotal, g.tax, g.adminFee, g.shipping, g.total, g.totalWeightKg])).rows[0];
      for (const l of g.items) {
        await c.query(
          `INSERT INTO order_items(order_id,variant_id,name_snapshot,grade,qty,unit_price,line_total) VALUES ($1,$2,$3,$4,$5,$6,$7)`,
          [o.id, l.variantId, l.name, l.grade, l.qty, l.unitPrice, l.qty * l.unitPrice]);
        // 2) reserve stock; CHECK (reserved_qty <= stock_qty) is the last line of defence
        await c.query("UPDATE variants SET reserved_qty=reserved_qty+$2, version=version+1 WHERE id=$1", [l.variantId, l.qty]);
        await c.query("INSERT INTO stock_movements(variant_id,delta,reason,ref_id,actor_id) VALUES ($1,$2,'reserve',$3,$4)", [l.variantId, -l.qty, o.id, user.id]);
        if (l.negotiationId) await c.query("UPDATE negotiations SET status='ordered' WHERE id=$1", [l.negotiationId]);
      }
      orders.push({ id: o.id, code, total: g.total, sellerName: g.sellerName });
    }
    await c.query("DELETE FROM cart_items WHERE user_id=$1", [user.id]);
    return { paymentId: pay.id, gatewayRef: ref, method: b.method, payCode: charge.payCode, amount: grandTotal, expiresAt: pay.expires_at, orders };
  });
  res.status(201).json(result);
}));

// ================= ORDERS =================
export const orderRouter = Router();
orderRouter.use(requireRole("pembeli", "petani", "admin_tengkulak", "super_admin"));

orderRouter.get("/", wrap(async (req, res) => {
  const u = req.user!;
  const scope = { pembeli: "o.buyer_id=$1", petani: "o.seller_id=$1", admin_tengkulak: "(o.region_id=$1 OR o.seller_id=$2)", super_admin: "TRUE" }[u.role];
  const p = u.role === "admin_tengkulak" ? [u.regionId, u.id] : u.role === "super_admin" ? [] : [u.id];
  res.json(await query(
    `SELECT o.id,o.code,o.status,o.total,o.total_weight_kg,o.created_at,b.full_name buyer,s.full_name seller,sh.tracking_code
       FROM orders o JOIN users b ON b.id=o.buyer_id JOIN users s ON s.id=o.seller_id LEFT JOIN shipments sh ON sh.order_id=o.id
      WHERE ${scope} ORDER BY o.created_at DESC LIMIT 200`, p));
}));

orderRouter.get("/:id", wrap(async (req, res) => {
  const u = req.user!;
  const [o] = await query("SELECT * FROM orders WHERE id=$1", [req.params.id]);
  if (!o) throw new HttpError(404, "Order not found");
  const allowed = u.role === "super_admin" || o.buyer_id === u.id || o.seller_id === u.id || (u.role === "admin_tengkulak" && o.region_id === u.regionId);
  if (!allowed) throw new HttpError(403, "Forbidden");
  const items = await query("SELECT name_snapshot name,grade,qty,unit_price,line_total FROM order_items WHERE order_id=$1", [o.id]);
  const [pay] = await query("SELECT method,status,pay_code,expires_at,paid_at FROM payments WHERE id=$1", [o.payment_id]);
  res.json({ ...o, items, payment: pay });
}));

// Seller moves a paid order into processing
orderRouter.post("/:id/accept", requireRole("petani", "admin_tengkulak"), wrap(async (req, res) => {
  const r = await query(
    `UPDATE orders SET status='processing' WHERE id=$1 AND status='paid' AND (seller_id=$2 OR ($3::int IS NOT NULL AND region_id=$3)) RETURNING id`,
    [req.params.id, req.user!.id, req.user!.role === "admin_tengkulak" ? req.user!.regionId : null]);
  if (!r.length) throw new HttpError(409, "Order not in a state to accept / not yours");
  res.json({ ok: true });
}));

orderRouter.post("/:id/confirm-received", requireRole("pembeli"), wrap(async (req, res) => {
  const r = await query("UPDATE orders SET status='completed' WHERE id=$1 AND buyer_id=$2 AND status='delivered' RETURNING id", [req.params.id, req.user!.id]);
  if (!r.length) throw new HttpError(409, "Order not delivered yet");
  res.json({ ok: true });
}));

// ================= PAYMENT WEBHOOK (public, signature-verified, idempotent) =================
export const webhookRouter = Router();

webhookRouter.post("/payment", wrap(async (req, res) => {
  const body = req.body;
  if (!verifyWebhook(body, req.headers["x-signature"] as string)) throw new HttpError(401, "Bad signature");
  const raw = String(body.transaction_status ?? body.status);
  const status = ["settlement", "capture", "paid"].includes(raw) ? "paid" : ["expire", "expired"].includes(raw) ? "expired" : ["deny", "cancel", "failed"].includes(raw) ? "failed" : "pending";
  if (status === "pending") return res.json({ ok: true });

  await settlePayment(body, status as "paid" | "expired" | "failed");
  res.json({ ok: true });
}));

/** Sandbox helper (PAYMENT_PROVIDER=mock only): buyer simulates the gateway "paid" callback for their own order. */
webhookRouter.post("/mock-settle", requireRole("pembeli"), wrap(async (req, res) => {
  if (env.paymentProvider !== "mock") throw new HttpError(404, "Not found");
  const [row] = await query(
    `SELECT p.gateway_ref, p.amount FROM payments p JOIN orders o ON o.payment_id=p.id WHERE o.id=$1 AND p.buyer_id=$2`, [req.body.orderId, req.user!.id]);
  if (!row) throw new HttpError(404, "Order not found");
  await settlePayment({ order_id: row.gateway_ref, amount: row.amount, status: "paid" }, "paid");
  res.json({ ok: true });
}));

async function settlePayment(body: any, status: "paid" | "expired" | "failed") {
  await tx(async c => {
    const p = (await c.query("SELECT * FROM payments WHERE gateway_ref=$1 FOR UPDATE", [body.order_id])).rows[0];
    if (!p) throw new HttpError(404, "Unknown payment");
    if (p.status !== "pending") return;                          // idempotent: replay is a no-op
    if (status === "paid" && Math.round(+p.amount) !== Math.round(+(body.gross_amount ?? body.amount)))
      throw new HttpError(422, "Amount mismatch");               // never trust gateway callback blindly
    await c.query("UPDATE payments SET status=$2, paid_at=CASE WHEN $2='paid' THEN now() END, raw_webhook=$3 WHERE id=$1", [p.id, status, body]);
    const items = (await c.query(
      "SELECT oi.variant_id, oi.qty, o.id order_id FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.payment_id=$1 ORDER BY oi.variant_id", [p.id])).rows;
    if (status === "paid") {
      await c.query("UPDATE orders SET status='paid' WHERE payment_id=$1", [p.id]);
      for (const i of items) {  // reserved -> sold
        await c.query("UPDATE variants SET stock_qty=stock_qty-$2, reserved_qty=reserved_qty-$2 WHERE id=$1", [i.variant_id, i.qty]);
        await c.query("INSERT INTO stock_movements(variant_id,delta,reason,ref_id) VALUES ($1,$2,'sale',$3)", [i.variant_id, -i.qty, i.order_id]);
      }
    } else {
      await releaseReservations(c, p.id, status === "expired" ? "expired" : "cancelled");
    }
  });
}

async function releaseReservations(c: PoolClient, paymentId: string, orderStatus: "expired" | "cancelled") {
  await c.query("UPDATE orders SET status=$2 WHERE payment_id=$1", [paymentId, orderStatus]);
  const items = (await c.query(
    "SELECT oi.variant_id, oi.qty, o.id order_id FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.payment_id=$1 ORDER BY oi.variant_id", [paymentId])).rows;
  for (const i of items) {
    await c.query("UPDATE variants SET reserved_qty=reserved_qty-$2 WHERE id=$1", [i.variant_id, i.qty]);
    await c.query("INSERT INTO stock_movements(variant_id,delta,reason,ref_id) VALUES ($1,$2,'release',$3)", [i.variant_id, i.qty, i.order_id]);
  }
}

/** Periodic job: expire unpaid payments and release reserved stock. */
export async function expireUnpaid() {
  const ids = (await query("SELECT id FROM payments WHERE status='pending' AND expires_at<now()")).map(r => r.id);
  for (const id of ids) await tx(async c => {
    const r = await c.query("UPDATE payments SET status='expired' WHERE id=$1 AND status='pending' RETURNING id", [id]);
    if (r.rowCount) await releaseReservations(c, id, "expired");
  });
}

// ================= NEGOTIATION =================
export const negoRouter = Router();
negoRouter.use(requireRole("pembeli", "petani", "admin_tengkulak"));

negoRouter.post("/", requireRole("pembeli"), requireKyc, validate(z.object({
  variantId: z.string().uuid(), qty: z.number().positive(), unitPrice: z.number().positive(), message: z.string().max(500).optional(),
})), wrap(async (req, res) => {
  if (req.user!.buyerType !== "b2b") throw new HttpError(403, "Negotiation is available for B2B buyers only");
  const b = req.body;
  const [v] = await query(
    `SELECT v.price, v.min_order, v.stock_qty-v.reserved_qty avail, c.farmer_id FROM variants v JOIN commodities c ON c.id=v.commodity_id
      WHERE v.id=$1 AND c.curation='approved' AND c.is_active`, [b.variantId]);
  if (!v) throw new HttpError(404, "Product unavailable");
  if (b.qty > +v.avail || b.qty < +v.min_order) throw new HttpError(422, "Quantity out of range");
  if (b.unitPrice < +v.price * 0.5) throw new HttpError(422, "Offer too low (min 50% of list price)");
  const id = await tx(async c => {
    const n = (await c.query(
      `INSERT INTO negotiations(buyer_id,seller_id,variant_id,qty,expires_at) VALUES ($1,$2,$3,$4, now() + interval '3 days') RETURNING id`,
      [req.user!.id, v.farmer_id, b.variantId, b.qty])).rows[0];
    await c.query("INSERT INTO negotiation_offers(negotiation_id,from_user,unit_price,message) VALUES ($1,$2,$3,$4)", [n.id, req.user!.id, b.unitPrice, b.message ?? null]);
    return n.id;
  });
  res.status(201).json({ id });
}));

/** Participants: the buyer, the owner, and the regional tengkulak. */
async function loadNego(id: string, u: NonNullable<Express.Request["user"]>, c?: PoolClient) {
  const q = (c ?? pool).query.bind(c ?? pool);
  const n = (await q(
    `SELECT n.*, co.region_id FROM negotiations n JOIN variants v ON v.id=n.variant_id JOIN commodities co ON co.id=v.commodity_id WHERE n.id=$1 ${c ? "FOR UPDATE OF n" : ""}`, [id])).rows[0];
  if (!n) throw new HttpError(404, "Negotiation not found");
  const seller = n.seller_id === u.id || (u.role === "admin_tengkulak" && n.region_id === u.regionId);
  if (n.buyer_id !== u.id && !seller) throw new HttpError(403, "Not a participant");
  return { n, isBuyer: n.buyer_id === u.id };
}

negoRouter.get("/", wrap(async (req, res) => {
  const u = req.user!;
  const scope = u.role === "pembeli" ? "n.buyer_id=$1" : u.role === "petani" ? "n.seller_id=$1" : "(n.seller_id=$2 OR co.region_id=$1)";
  const p = u.role === "admin_tengkulak" ? [u.regionId, u.id] : [u.id];
  res.json(await query(
    `SELECT n.id,n.status,n.qty,n.agreed_price,n.expires_at,v.variant_name,v.grade,v.price list_price,
        (SELECT json_agg(json_build_object('from',o.from_user,'price',o.unit_price,'msg',o.message,'at',o.created_at) ORDER BY o.id) FROM negotiation_offers o WHERE o.negotiation_id=n.id) offers
       FROM negotiations n JOIN variants v ON v.id=n.variant_id JOIN commodities co ON co.id=v.commodity_id
      WHERE ${scope} ORDER BY n.created_at DESC`, p));
}));

negoRouter.post("/:id/offers", validate(z.object({ unitPrice: z.number().positive(), message: z.string().max(500).optional() })), wrap(async (req, res) => {
  await tx(async c => {
    const { n } = await loadNego(req.params.id, req.user!, c);
    if (n.status !== "open" || new Date(n.expires_at) < new Date()) throw new HttpError(409, "Negotiation closed");
    const last = (await c.query("SELECT from_user FROM negotiation_offers WHERE negotiation_id=$1 ORDER BY id DESC LIMIT 1", [n.id])).rows[0];
    if (last.from_user === req.user!.id) throw new HttpError(409, "Waiting for the other party");
    await c.query("INSERT INTO negotiation_offers(negotiation_id,from_user,unit_price,message) VALUES ($1,$2,$3,$4)", [n.id, req.user!.id, req.body.unitPrice, req.body.message ?? null]);
  });
  res.status(201).json({ ok: true });
}));

negoRouter.post("/:id/respond", validate(z.object({ accept: z.boolean() })), wrap(async (req, res) => {
  await tx(async c => {
    const { n, isBuyer } = await loadNego(req.params.id, req.user!, c);
    if (n.status !== "open" || new Date(n.expires_at) < new Date()) throw new HttpError(409, "Negotiation closed");
    const last = (await c.query("SELECT from_user, unit_price FROM negotiation_offers WHERE negotiation_id=$1 ORDER BY id DESC LIMIT 1", [n.id])).rows[0];
    if (!req.body.accept) {
      await c.query("UPDATE negotiations SET status=$2 WHERE id=$1", [n.id, isBuyer ? "cancelled" : "rejected"]);
      return;
    }
    if (last.from_user === req.user!.id) throw new HttpError(409, "You cannot accept your own offer");
    await c.query("UPDATE negotiations SET status='accepted', agreed_price=$2, expires_at=now()+interval '2 days' WHERE id=$1", [n.id, last.unit_price]);
  });
  res.json({ ok: true });
}));
