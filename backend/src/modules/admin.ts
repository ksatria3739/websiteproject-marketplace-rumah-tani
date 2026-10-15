import { Router } from "express";
import bcrypt from "bcryptjs";
import ExcelJS from "exceljs";
import PDFDocument from "pdfkit";
import { z } from "zod";
import { query } from "../config/db";
import { HttpError, requireRole, validate, wrap } from "../middleware/security";

export const adminRouter = Router();
adminRouter.use(requireRole("super_admin"));

// ---- Analytics (all aggregate queries hit indexed columns; only paid-or-later orders count) ----
const PAID = "o.status IN ('paid','processing','shipped','delivered','completed')";
const range = z.object({ from: z.string().optional(), to: z.string().optional(), groupBy: z.enum(["day", "week", "month"]).default("day") });

async function analytics(from?: string, to?: string, groupBy = "day") {
  const p = [from ?? "1970-01-01", to ?? "2999-01-01"];
  const trend = await query(
    `SELECT date_trunc('${groupBy}', o.created_at)::date period, COUNT(*)::int orders, SUM(o.total) gmv, SUM(o.admin_fee) revenue
       FROM orders o WHERE ${PAID} AND o.created_at BETWEEN $1 AND $2 GROUP BY 1 ORDER BY 1`, p);
  const top = await query(
    `SELECT c.name, SUM(oi.qty) qty, SUM(oi.line_total) sales FROM order_items oi JOIN orders o ON o.id=oi.order_id
       JOIN variants v ON v.id=oi.variant_id JOIN commodities c ON c.id=v.commodity_id
      WHERE ${PAID} AND o.created_at BETWEEN $1 AND $2 GROUP BY c.name ORDER BY qty DESC LIMIT 10`, p);
  const [pl] = await query(
    `SELECT COALESCE(SUM(subtotal),0) gross_sales, COALESCE(SUM(admin_fee),0) admin_fee_revenue, COALESCE(SUM(tax),0) tax_collected,
            COALESCE(SUM(shipping_cost),0) shipping_collected, COUNT(*)::int orders
       FROM orders o WHERE ${PAID} AND o.created_at BETWEEN $1 AND $2`, p);
  // Operating P&L: platform revenue = admin fee; shipping passes through to logistics; tax is liability.
  const revenue = +pl.admin_fee_revenue, shippingCost = +pl.shipping_collected;
  const logistics = shippingCost * 0.85;          // assumed carrier share; make configurable in production
  const shippingMargin = shippingCost - logistics;
  const profit = { ...pl, revenue: revenue + shippingMargin, shipping_margin: shippingMargin, operating_profit: revenue + shippingMargin };
  return { trend, top, profit };
}

adminRouter.get("/analytics", validate(range, "query"), wrap(async (req, res) => {
  const q = req.query as any; res.json(await analytics(q.from, q.to, q.groupBy));
}));

adminRouter.get("/reports/export", wrap(async (req, res) => {
  const format = req.query.format === "pdf" ? "pdf" : "xlsx";
  const a = await analytics(req.query.from as string, req.query.to as string);
  if (format === "xlsx") {
    const wb = new ExcelJS.Workbook();
    const s1 = wb.addWorksheet("Trend");
    s1.columns = [{ header: "Period", key: "period", width: 14 }, { header: "Orders", key: "orders", width: 10 }, { header: "GMV", key: "gmv", width: 18 }, { header: "Revenue", key: "revenue", width: 18 }];
    a.trend.forEach(r => s1.addRow({ ...r, gmv: +r.gmv, revenue: +r.revenue }));
    const s2 = wb.addWorksheet("Top Commodities");
    s2.columns = [{ header: "Commodity", key: "name", width: 28 }, { header: "Qty", key: "qty", width: 12 }, { header: "Sales", key: "sales", width: 18 }];
    a.top.forEach(r => s2.addRow({ ...r, qty: +r.qty, sales: +r.sales }));
    const s3 = wb.addWorksheet("P&L");
    s3.columns = [{ header: "Item", key: "k", width: 28 }, { header: "Amount (IDR)", key: "v", width: 20 }];
    Object.entries(a.profit).forEach(([k, v]) => s3.addRow({ k, v: +(v as any) }));
    [s1, s2, s3].forEach(s => s.getRow(1).font = { bold: true });
    res.setHeader("Content-Type", "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    res.setHeader("Content-Disposition", "attachment; filename=rumah-tani-report.xlsx");
    await wb.xlsx.write(res); return res.end();
  }
  res.setHeader("Content-Type", "application/pdf");
  res.setHeader("Content-Disposition", "attachment; filename=rumah-tani-report.pdf");
  const doc = new PDFDocument({ margin: 40 }); doc.pipe(res);
  doc.fontSize(18).text("Rumah Tani - Operational Report"); doc.moveDown();
  doc.fontSize(13).text("Profit & Loss");
  Object.entries(a.profit).forEach(([k, v]) => doc.fontSize(10).text(`${k}: ${Number(v).toLocaleString("id-ID")}`));
  doc.moveDown().fontSize(13).text("Top Commodities");
  a.top.forEach((r, i) => doc.fontSize(10).text(`${i + 1}. ${r.name} - qty ${r.qty}, sales ${Number(r.sales).toLocaleString("id-ID")}`));
  doc.moveDown().fontSize(13).text("Transaction Trend");
  a.trend.forEach(r => doc.fontSize(10).text(`${r.period} - ${r.orders} orders, GMV ${Number(r.gmv).toLocaleString("id-ID")}`));
  doc.end();
}));

// ---- Users / master data / settings ----
adminRouter.get("/users", wrap(async (req, res) =>
  res.json(await query("SELECT id,email,full_name,role,buyer_type,company_name,region_id,kyc_status,is_active,created_at FROM users ORDER BY created_at DESC LIMIT 500"))));

adminRouter.post("/users/tengkulak", validate(z.object({
  email: z.string().email(), password: z.string().min(8), fullName: z.string(), regionId: z.number().int(),
})), wrap(async (req, res) => {
  const b = req.body;
  const [u] = await query(
    "INSERT INTO users(email,password_hash,full_name,role,region_id,kyc_status) VALUES ($1,$2,$3,'admin_tengkulak',$4,'approved') RETURNING id,email",
    [b.email, await bcrypt.hash(b.password, 12), b.fullName, b.regionId]);
  res.status(201).json(u);
}));

adminRouter.patch("/users/:id", validate(z.object({ isActive: z.boolean() })), wrap(async (req, res) => {
  if (req.params.id === req.user!.id) throw new HttpError(409, "Cannot deactivate yourself");
  // bump token_version to kill active sessions instantly
  await query("UPDATE users SET is_active=$2, token_version=token_version+1 WHERE id=$1", [req.params.id, req.body.isActive]);
  res.json({ ok: true });
}));

adminRouter.get("/settings", wrap(async (_req, res) => res.json(await query("SELECT key,value,updated_at FROM system_settings ORDER BY key"))));
adminRouter.put("/settings/:key", validate(z.object({ value: z.string() })), wrap(async (req, res) => {
  const r = await query("UPDATE system_settings SET value=$2, updated_by=$3, updated_at=now() WHERE key=$1 RETURNING key", [req.params.key, req.body.value, req.user!.id]);
  if (!r.length) throw new HttpError(404, "Unknown setting");
  await query("INSERT INTO audit_logs(actor_id,action,entity,entity_id,meta) VALUES ($1,'setting.update','setting',$2,$3)", [req.user!.id, req.params.key, JSON.stringify(req.body)]);
  res.json({ ok: true });
}));

adminRouter.post("/categories", validate(z.object({ name: z.string(), slug: z.string() })), wrap(async (req, res) =>
  res.status(201).json((await query("INSERT INTO categories(name,slug) VALUES ($1,$2) RETURNING *", [req.body.name, req.body.slug]))[0])));
adminRouter.post("/regions", validate(z.object({ name: z.string(), province: z.string(), lat: z.number(), lng: z.number() })), wrap(async (req, res) =>
  res.status(201).json((await query("INSERT INTO regions(name,province,lat,lng) VALUES ($1,$2,$3,$4) RETURNING *", [req.body.name, req.body.province, req.body.lat, req.body.lng]))[0])));
adminRouter.post("/articles", validate(z.object({ slug: z.string(), title: z.string(), excerpt: z.string().optional(), body: z.string(), published: z.boolean().default(true) })),
  wrap(async (req, res) => res.status(201).json((await query(
    "INSERT INTO articles(slug,title,excerpt,body,author_id,published,published_at) VALUES ($1,$2,$3,$4,$5,$6,CASE WHEN $6 THEN now() END) RETURNING id",
    [req.body.slug, req.body.title, req.body.excerpt ?? null, req.body.body, req.user!.id, req.body.published]))[0])));

adminRouter.get("/transactions", wrap(async (_req, res) =>
  res.json(await query(
    `SELECT o.code,o.status,o.total,o.created_at,b.full_name buyer,s.full_name seller FROM orders o
       JOIN users b ON b.id=o.buyer_id JOIN users s ON s.id=o.seller_id ORDER BY o.created_at DESC LIMIT 300`))));

// ---- Dashboards for other roles (lightweight) ----
export const dashRouter = Router();
dashRouter.get("/summary", requireRole("petani", "admin_tengkulak"), wrap(async (req, res) => {
  const u = req.user!;
  const sellerScope = u.role === "petani" ? "o.seller_id=$1" : "(o.region_id=$2 OR o.seller_id=$1)";
  const [s] = await query(
    `SELECT COUNT(*) FILTER (WHERE o.status='paid')::int to_process, COALESCE(SUM(o.subtotal) FILTER (WHERE ${PAID}),0) sales
       FROM orders o WHERE ${sellerScope}`, u.role === "petani" ? [u.id] : [u.id, u.regionId]);
  const [lowStock] = await query(
    `SELECT COUNT(*)::int c FROM variants v JOIN commodities c ON c.id=v.commodity_id WHERE (c.farmer_id=$1 OR ($2::int IS NOT NULL AND c.region_id=$2)) AND v.stock_qty-v.reserved_qty<10`,
    [u.id, u.role === "admin_tengkulak" ? u.regionId : null]);
  res.json({ ...s, lowStockVariants: lowStock.c });
}));
