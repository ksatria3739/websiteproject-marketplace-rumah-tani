import { Router } from "express";
import bcrypt from "bcryptjs";
import crypto from "crypto";
import jwt from "jsonwebtoken";
import multer from "multer";
import fs from "fs";
import path from "path";
import { z } from "zod";
import { env } from "../config/env";
import { query, tx } from "../config/db";
import { HttpError, requireRole, validate, wrap } from "../middleware/security";

export const authRouter = Router();
const sha = (s: string) => crypto.createHash("sha256").update(s).digest("hex");

async function issueTokens(u: { id: string; token_version: number }) {
  const access = jwt.sign({ sub: u.id, tv: u.token_version }, env.accessSecret, { expiresIn: "15m" });
  const refresh = crypto.randomBytes(48).toString("hex");
  await query("INSERT INTO refresh_tokens(user_id,token_hash,expires_at) VALUES ($1,$2, now() + interval '14 days')",
    [u.id, sha(refresh)]);
  return { accessToken: access, refreshToken: refresh };
}

const registerSchema = z.object({
  email: z.string().email(),
  password: z.string().min(8).regex(/[A-Za-z]/).regex(/\d/),
  fullName: z.string().min(2),
  phone: z.string().optional(),
  // Self-registration allowed only for petani, pembeli. Tengkulak is created by Super Admin.
  role: z.enum(["petani", "pembeli"]),
  buyerType: z.enum(["b2b", "b2c"]).optional(),
  companyName: z.string().optional(),
  regionId: z.number().int().optional(),
  address: z.string().optional(),
}).refine(d => d.role !== "pembeli" || d.buyerType, { message: "buyerType required for pembeli" })
  .refine(d => d.role !== "petani" || d.regionId, { message: "regionId required for petani" });

authRouter.post("/register", validate(registerSchema), wrap(async (req, res) => {
  const b = req.body;
  const hash = await bcrypt.hash(b.password, 12);
  const kyc = b.role === "pembeli" && b.buyerType === "b2c" ? "approved" : "unsubmitted";
  const [u] = await query(
    `INSERT INTO users(email,password_hash,full_name,phone,role,buyer_type,company_name,region_id,address,kyc_status)
     VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10) RETURNING id,email,role,kyc_status,token_version`,
    [b.email, hash, b.fullName, b.phone ?? null, b.role, b.buyerType ?? null, b.companyName ?? null,
     b.regionId ?? null, b.address ?? null, kyc]);
  res.status(201).json({ user: { id: u.id, email: u.email, role: u.role, kycStatus: u.kyc_status }, ...(await issueTokens(u)) });
}));

authRouter.post("/login", validate(z.object({ email: z.string().email(), password: z.string() })), wrap(async (req, res) => {
  const [u] = await query("SELECT * FROM users WHERE email=$1", [req.body.email]);
  // constant-ish time: always compare
  const ok = await bcrypt.compare(req.body.password, u?.password_hash ?? "$2a$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidi");
  if (!u || !ok || !u.is_active) throw new HttpError(401, "Invalid credentials");
  res.json({
    user: { id: u.id, email: u.email, fullName: u.full_name, role: u.role, kycStatus: u.kyc_status, buyerType: u.buyer_type, regionId: u.region_id },
    ...(await issueTokens(u)),
  });
}));

authRouter.post("/refresh", validate(z.object({ refreshToken: z.string() })), wrap(async (req, res) => {
  const hash = sha(req.body.refreshToken);
  const out = await tx(async c => {
    const r = await c.query(
      `UPDATE refresh_tokens SET revoked_at=now() WHERE token_hash=$1 AND revoked_at IS NULL AND expires_at>now() RETURNING user_id`, [hash]);
    if (!r.rowCount) throw new HttpError(401, "Invalid refresh token");   // rotation: one-time use
    const u = (await c.query("SELECT id, token_version, is_active FROM users WHERE id=$1", [r.rows[0].user_id])).rows[0];
    if (!u?.is_active) throw new HttpError(401, "Account disabled");
    return u;
  });
  res.json(await issueTokens(out));
}));

authRouter.post("/logout", wrap(async (req, res) => {
  if (req.body?.refreshToken) await query("UPDATE refresh_tokens SET revoked_at=now() WHERE token_hash=$1", [sha(req.body.refreshToken)]);
  res.json({ ok: true });
}));

authRouter.post("/forgot-password", validate(z.object({ email: z.string().email() })), wrap(async (req, res) => {
  const [u] = await query("SELECT id FROM users WHERE email=$1", [req.body.email]);
  if (u) {
    const token = crypto.randomBytes(32).toString("hex");
    await query("INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES ($1,$2, now() + interval '30 minutes')", [u.id, sha(token)]);
    // TODO: send via email provider. In dev we log it.
    console.log(`[password-reset] ${req.body.email}: ${token}`);
  }
  res.json({ ok: true, message: "If the email exists, a reset link was sent." }); // no user enumeration
}));

authRouter.post("/reset-password", validate(z.object({ token: z.string(), password: z.string().min(8) })), wrap(async (req, res) => {
  await tx(async c => {
    const r = await c.query(
      `UPDATE password_resets SET used_at=now() WHERE token_hash=$1 AND used_at IS NULL AND expires_at>now() RETURNING user_id`, [sha(req.body.token)]);
    if (!r.rowCount) throw new HttpError(400, "Invalid or expired token");
    // bump token_version => all existing sessions invalid
    await c.query("UPDATE users SET password_hash=$1, token_version=token_version+1 WHERE id=$2",
      [await bcrypt.hash(req.body.password, 12), r.rows[0].user_id]);
  });
  res.json({ ok: true });
}));

authRouter.get("/me", requireRole("super_admin", "admin_tengkulak", "petani", "pembeli"), wrap(async (req, res) => {
  const [u] = await query(
    "SELECT id,email,full_name,phone,role,buyer_type,company_name,region_id,address,kyc_status,kyc_note FROM users WHERE id=$1", [req.user!.id]);
  res.json(u);
}));

// ---------- KYC ----------
fs.mkdirSync(env.uploadDir, { recursive: true });
const upload = multer({
  storage: multer.diskStorage({
    destination: env.uploadDir,
    filename: (_r, f, cb) => cb(null, crypto.randomUUID() + path.extname(f.originalname).toLowerCase()),
  }),
  limits: { fileSize: 5 * 1024 * 1024 },
  fileFilter: (_r, f, cb) => cb(null, ["image/jpeg", "image/png", "application/pdf"].includes(f.mimetype)),
});

export const kycRouter = Router();

kycRouter.post("/documents", requireRole("petani", "admin_tengkulak", "pembeli"),
  upload.array("files", 4), wrap(async (req, res) => {
    const files = (req.files as Express.Multer.File[]) ?? [];
    if (!files.length) throw new HttpError(400, "No valid file (jpg/png/pdf ≤5MB)");
    const types: string[] = [].concat(req.body.docTypes ?? []);
    await tx(async c => {
      for (let i = 0; i < files.length; i++)
        await c.query("INSERT INTO kyc_documents(user_id,doc_type,file_path) VALUES ($1,$2,$3)",
          [req.user!.id, types[i] ?? "ktp", files[i].filename]);
      await c.query("UPDATE users SET kyc_status='pending', kyc_note=NULL WHERE id=$1 AND kyc_status<>'approved'", [req.user!.id]);
    });
    res.status(201).json({ ok: true, status: "pending" });
  }));

// Review queue: tengkulak sees petani in own region; super admin sees all.
kycRouter.get("/pending", requireRole("super_admin", "admin_tengkulak"), wrap(async (req, res) => {
  const u = req.user!;
  const rows = await query(
    `SELECT u.id,u.full_name,u.email,u.role,u.buyer_type,u.company_name,u.region_id,u.created_at,
        (SELECT json_agg(json_build_object('type',d.doc_type,'file',d.file_path)) FROM kyc_documents d WHERE d.user_id=u.id) docs
       FROM users u WHERE u.kyc_status='pending'
        AND ($1='super_admin' OR (u.role='petani' AND u.region_id=$2))
      ORDER BY u.created_at`, [u.role, u.regionId]);
  res.json(rows);
}));

kycRouter.post("/:userId/review", requireRole("super_admin", "admin_tengkulak"),
  validate(z.object({ approve: z.boolean(), note: z.string().optional() })), wrap(async (req, res) => {
    const u = req.user!;
    const [t] = await query("SELECT role, region_id FROM users WHERE id=$1", [req.params.userId]);
    if (!t) throw new HttpError(404, "User not found");
    if (u.role === "admin_tengkulak" && !(t.role === "petani" && t.region_id === u.regionId))
      throw new HttpError(403, "Outside your region/scope");
    await query("UPDATE users SET kyc_status=$1, kyc_note=$2 WHERE id=$3",
      [req.body.approve ? "approved" : "rejected", req.body.note ?? null, req.params.userId]);
    await query("INSERT INTO audit_logs(actor_id,action,entity,entity_id,meta) VALUES ($1,'kyc.review','user',$2,$3)",
      [u.id, req.params.userId, JSON.stringify(req.body)]);
    res.json({ ok: true });
  }));
