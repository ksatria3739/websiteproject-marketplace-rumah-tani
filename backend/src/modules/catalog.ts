import { Router } from "express";
import { z } from "zod";
import { query, tx } from "../config/db";
import { HttpError, requireKyc, requireRole, validate, wrap } from "../middleware/security";

// ============ PUBLIC (Guest-accessible, read-only) ============
export const publicRouter = Router();

const catalogQ = z.object({
  q: z.string().optional(), regionId: z.coerce.number().optional(), categoryId: z.coerce.number().optional(),
  grade: z.enum(["A", "B", "C"]).optional(),
  page: z.coerce.number().min(1).default(1), limit: z.coerce.number().min(1).max(60).default(20),
});

publicRouter.get("/catalog", validate(catalogQ, "query"), wrap(async (req, res) => {
  const f = req.query as any;
  const p: any[] = []; const w = ["c.curation='approved'", "c.is_active"];
  if (f.q) { p.push(`%${f.q.toLowerCase()}%`); w.push(`lower(c.name) LIKE $${p.length}`); }
  if (f.regionId) { p.push(f.regionId); w.push(`c.region_id=$${p.length}`); }
  if (f.categoryId) { p.push(f.categoryId); w.push(`c.category_id=$${p.length}`); }
  if (f.grade) { p.push(f.grade); w.push(`EXISTS (SELECT 1 FROM variants v WHERE v.commodity_id=c.id AND v.grade=$${p.length})`); }
  p.push(f.limit, (f.page - 1) * f.limit);
  const rows = await query(
    `SELECT c.id,c.name,c.slug,c.image_url,r.name region,cat.name category,
       MIN(v.price) price_from, SUM(v.stock_qty - v.reserved_qty) available,
       array_agg(DISTINCT v.grade) grades
     FROM commodities c JOIN regions r ON r.id=c.region_id JOIN categories cat ON cat.id=c.category_id
     JOIN variants v ON v.commodity_id=c.id
     WHERE ${w.join(" AND ")} GROUP BY c.id,r.name,cat.name
     ORDER BY c.created_at DESC LIMIT $${p.length - 1} OFFSET $${p.length}`, p);
  res.json({ page: f.page, items: rows });
}));

publicRouter.get("/catalog/:slug", wrap(async (req, res) => {
  const [c] = await query(
    `SELECT c.*, r.name region, cat.name category, u.full_name farmer_name FROM commodities c
      JOIN regions r ON r.id=c.region_id JOIN categories cat ON cat.id=c.category_id JOIN users u ON u.id=c.farmer_id
      WHERE c.slug=$1 AND c.curation='approved' AND c.is_active`, [req.params.slug]);
  if (!c) throw new HttpError(404, "Not found");
  const variants = await query(
    `SELECT id,variant_name,grade,unit,price,(stock_qty-reserved_qty) available,min_order,weight_per_unit_kg
       FROM variants WHERE commodity_id=$1 ORDER BY grade,variant_name`, [c.id]);
  res.json({ ...c, farmer_id: undefined, variants });
}));

publicRouter.get("/meta", wrap(async (_req, res) => {
  res.json({
    regions: await query("SELECT id,name,province FROM regions ORDER BY name"),
    categories: await query("SELECT id,name,slug FROM categories ORDER BY name"),
  });
}));
publicRouter.get("/articles", wrap(async (_req, res) =>
  res.json(await query("SELECT slug,title,excerpt,cover_url,published_at FROM articles WHERE published ORDER BY published_at DESC LIMIT 50"))));
publicRouter.get("/articles/:slug", wrap(async (req, res) => {
  const [a] = await query("SELECT slug,title,body,cover_url,published_at FROM articles WHERE slug=$1 AND published", [req.params.slug]);
  if (!a) throw new HttpError(404, "Not found");
  res.json(a);
}));

// ============ INVENTORY (Petani + Tengkulak) ============
export const inventoryRouter = Router();
inventoryRouter.use(requireRole("petani", "admin_tengkulak", "super_admin"));

const slugify = (s: string) => s.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "") + "-" + Math.random().toString(36).slice(2, 6);

const variantIn = z.object({
  variantName: z.string().min(1), grade: z.enum(["A", "B", "C"]), unit: z.string().default("kg"),
  price: z.number().positive(), stockQty: z.number().min(0).default(0),
  minOrder: z.number().positive().default(1), weightPerUnitKg: z.number().positive().default(1),
});
const commodityIn = z.object({
  name: z.string().min(2), categoryId: z.number().int(), regionId: z.number().int().optional(),
  description: z.string().optional(), imageUrl: z.string().url().optional(),
  variants: z.array(variantIn).min(1),
});

/** Checks the caller may manage the commodity: owner, regional tengkulak, or super admin. */
async function assertCanManage(user: Express.Request["user"], commodityId: string) {
  const [c] = await query("SELECT farmer_id, region_id FROM commodities WHERE id=$1", [commodityId]);
  if (!c) throw new HttpError(404, "Commodity not found");
  const u = user!;
  const ok = u.role === "super_admin" || c.farmer_id === u.id || (u.role === "admin_tengkulak" && c.region_id === u.regionId);
  if (!ok) throw new HttpError(403, "Not your commodity");
  return c;
}

inventoryRouter.post("/commodities", requireKyc, validate(commodityIn), wrap(async (req, res) => {
  const u = req.user!, b = req.body;
  const regionId = b.regionId ?? u.regionId;
  if (!regionId) throw new HttpError(422, "regionId required");
  // tengkulak's own listings skip curation; petani's go to pending queue
  const curation = u.role === "petani" ? "pending" : "approved";
  const id = await tx(async c => {
    const r = await c.query(
      `INSERT INTO commodities(farmer_id,region_id,category_id,name,slug,description,image_url,curation,curated_by)
       VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9) RETURNING id`,
      [u.id, regionId, b.categoryId, b.name, slugify(b.name), b.description ?? null, b.imageUrl ?? null, curation,
       curation === "approved" ? u.id : null]);
    for (const v of b.variants) {
      const vr = await c.query(
        `INSERT INTO variants(commodity_id,variant_name,grade,unit,price,stock_qty,min_order,weight_per_unit_kg)
         VALUES ($1,$2,$3,$4,$5,$6,$7,$8) RETURNING id`,
        [r.rows[0].id, v.variantName, v.grade, v.unit, v.price, v.stockQty, v.minOrder, v.weightPerUnitKg]);
      if (v.stockQty > 0)
        await c.query("INSERT INTO stock_movements(variant_id,delta,reason,actor_id) VALUES ($1,$2,'harvest',$3)", [vr.rows[0].id, v.stockQty, u.id]);
    }
    return r.rows[0].id;
  });
  res.status(201).json({ id, curation });
}));

inventoryRouter.get("/commodities", wrap(async (req, res) => {
  const u = req.user!;
  const scope = u.role === "super_admin" ? "TRUE" : u.role === "admin_tengkulak" ? "c.region_id=$1" : "c.farmer_id=$1";
  const p = u.role === "super_admin" ? [] : [u.role === "admin_tengkulak" ? u.regionId : u.id];
  res.json(await query(
    `SELECT c.id,c.name,c.curation,c.curation_note,c.is_active,c.region_id,
       (SELECT json_agg(json_build_object('id',v.id,'name',v.variant_name,'grade',v.grade,'price',v.price,
          'stock',v.stock_qty,'reserved',v.reserved_qty,'unit',v.unit)) FROM variants v WHERE v.commodity_id=c.id) variants
     FROM commodities c WHERE ${scope} ORDER BY c.created_at DESC`, p));
}));

inventoryRouter.patch("/commodities/:id", validate(commodityIn.partial().omit({ variants: true })), wrap(async (req, res) => {
  const c = await assertCanManage(req.user, req.params.id);
  const b = req.body;
  // Editing by a petani sends it back to curation
  await query(
    `UPDATE commodities SET name=COALESCE($2,name), description=COALESCE($3,description), image_url=COALESCE($4,image_url),
       curation = CASE WHEN $5 THEN 'pending'::curation_status ELSE curation END WHERE id=$1`,
    [req.params.id, b.name ?? null, b.description ?? null, b.imageUrl ?? null, req.user!.role === "petani"]);
  res.json({ ok: true, regionId: c.region_id });
}));

inventoryRouter.patch("/variants/:id", validate(z.object({
  price: z.number().positive().optional(), minOrder: z.number().positive().optional(),
})), wrap(async (req, res) => {
  const [v] = await query("SELECT commodity_id FROM variants WHERE id=$1", [req.params.id]);
  if (!v) throw new HttpError(404, "Variant not found");
  await assertCanManage(req.user, v.commodity_id);
  await query("UPDATE variants SET price=COALESCE($2,price), min_order=COALESCE($3,min_order), updated_at=now() WHERE id=$1",
    [req.params.id, req.body.price ?? null, req.body.minOrder ?? null]);
  res.json({ ok: true });
}));

/**
 * Stock adjustment. Delta-based (not absolute) so concurrent updates never overwrite each other.
 * Row lock + CHECK constraints keep stock >= reserved >= 0.
 */
inventoryRouter.post("/variants/:id/stock", validate(z.object({
  delta: z.number().refine(n => n !== 0), reason: z.enum(["harvest", "adjust"]).default("adjust"),
})), wrap(async (req, res) => {
  const out = await tx(async c => {
    const v = (await c.query("SELECT id, commodity_id FROM variants WHERE id=$1 FOR UPDATE", [req.params.id])).rows[0];
    if (!v) throw new HttpError(404, "Variant not found");
    await assertCanManage(req.user, v.commodity_id);
    let r;
    try {
      r = await c.query(
        "UPDATE variants SET stock_qty=stock_qty+$2, version=version+1, updated_at=now() WHERE id=$1 RETURNING stock_qty,reserved_qty",
        [v.id, req.body.delta]);
    } catch (e: any) {
      if (e.code === "23514") throw new HttpError(409, "Stock cannot drop below reserved quantity or zero");
      throw e;
    }
    await c.query("INSERT INTO stock_movements(variant_id,delta,reason,actor_id) VALUES ($1,$2,$3,$4)",
      [v.id, req.body.delta, req.body.reason, req.user!.id]);
    return r.rows[0];
  });
  res.json(out);
}));

inventoryRouter.post("/commodities/:id/variants", requireKyc, validate(variantIn), wrap(async (req, res) => {
  await assertCanManage(req.user, req.params.id);
  const v = req.body;
  const [r] = await query(
    `INSERT INTO variants(commodity_id,variant_name,grade,unit,price,stock_qty,min_order,weight_per_unit_kg)
     VALUES ($1,$2,$3,$4,$5,$6,$7,$8) RETURNING id`,
    [req.params.id, v.variantName, v.grade, v.unit, v.price, v.stockQty, v.minOrder, v.weightPerUnitKg]);
  res.status(201).json(r);
}));

// ============ CURATION (Tengkulak in region / Super Admin) ============
export const curationRouter = Router();
curationRouter.use(requireRole("admin_tengkulak", "super_admin"));

curationRouter.get("/queue", wrap(async (req, res) => {
  const u = req.user!;
  res.json(await query(
    `SELECT c.id,c.name,c.created_at,u.full_name farmer,r.name region,
       (SELECT json_agg(json_build_object('name',v.variant_name,'grade',v.grade,'price',v.price,'stock',v.stock_qty)) FROM variants v WHERE v.commodity_id=c.id) variants
     FROM commodities c JOIN users u ON u.id=c.farmer_id JOIN regions r ON r.id=c.region_id
     WHERE c.curation='pending' AND ($1='super_admin' OR c.region_id=$2) ORDER BY c.created_at`, [u.role, u.regionId]));
}));

curationRouter.post("/:id/decision", validate(z.object({
  approve: z.boolean(), note: z.string().optional(),
  gradeOverrides: z.array(z.object({ variantId: z.string().uuid(), grade: z.enum(["A", "B", "C"]) })).optional(),
})), wrap(async (req, res) => {
  await assertCanManage(req.user, req.params.id);
  await tx(async c => {
    for (const g of req.body.gradeOverrides ?? [])
      await c.query("UPDATE variants SET grade=$1 WHERE id=$2 AND commodity_id=$3", [g.grade, g.variantId, req.params.id]);
    await c.query("UPDATE commodities SET curation=$1, curated_by=$2, curation_note=$3 WHERE id=$4",
      [req.body.approve ? "approved" : "rejected", req.user!.id, req.body.note ?? null, req.params.id]);
  });
  res.json({ ok: true });
}));
