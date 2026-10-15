import { NextFunction, Request, Response, RequestHandler } from "express";
import jwt from "jsonwebtoken";
import { ZodSchema } from "zod";
import { env } from "../config/env";
import { query } from "../config/db";

export type Role = "super_admin" | "admin_tengkulak" | "petani" | "pembeli";
export interface AuthUser { id: string; role: Role; regionId: number | null; kyc: string; buyerType: string | null }

declare global {
  namespace Express { interface Request { user?: AuthUser } }
}

export class HttpError extends Error {
  constructor(public status: number, message: string, public details?: unknown) { super(message); }
}

export const wrap = (fn: (req: Request, res: Response) => Promise<any>): RequestHandler =>
  (req, res, next) => { fn(req, res).catch(next); };

/** Parses Bearer access token. Missing token => request continues as Guest. */
export const optionalAuth: RequestHandler = async (req, _res, next) => {
  try {
    const h = req.headers.authorization;
    if (h?.startsWith("Bearer ")) {
      const p = jwt.verify(h.slice(7), env.accessSecret) as { sub: string; tv: number };
      const [u] = await query(
        "SELECT id, role, region_id, kyc_status, buyer_type, token_version, is_active FROM users WHERE id=$1", [p.sub]);
      if (!u || !u.is_active || u.token_version !== p.tv) throw new HttpError(401, "Session revoked");
      req.user = { id: u.id, role: u.role, regionId: u.region_id, kyc: u.kyc_status, buyerType: u.buyer_type };
    }
    next();
  } catch (e: any) {
    next(e instanceof HttpError ? e : new HttpError(401, "Invalid or expired token"));
  }
};

/** Require authentication AND one of the given roles. Guest => 401, wrong role => 403. */
export const requireRole = (...roles: Role[]): RequestHandler => (req, _res, next) => {
  if (!req.user) return next(new HttpError(401, "Authentication required"));
  if (!roles.includes(req.user.role)) return next(new HttpError(403, "Forbidden for role " + req.user.role));
  next();
};

/** Block actions until KYC approved (used for selling & B2B purchasing). */
export const requireKyc: RequestHandler = (req, _res, next) => {
  const u = req.user!;
  const needs = u.role === "petani" || u.role === "admin_tengkulak" || (u.role === "pembeli" && u.buyerType === "b2b");
  if (needs && u.kyc !== "approved") return next(new HttpError(403, "KYC verification required"));
  next();
};

export const validate = <T>(schema: ZodSchema<T>, src: "body" | "query" = "body"): RequestHandler =>
  (req, _res, next) => {
    const r = schema.safeParse(req[src]);
    if (!r.success) return next(new HttpError(422, "Validation failed", r.error.flatten()));
    (req as any)[src] = r.data;
    next();
  };

export const errorHandler = (err: any, _req: Request, res: Response, _next: NextFunction) => {
  if (err instanceof HttpError) return res.status(err.status).json({ error: err.message, details: err.details });
  if (err?.code === "23505") return res.status(409).json({ error: "Duplicate record" });
  if (err?.code === "23514") return res.status(409).json({ error: "Constraint violated (e.g. insufficient stock)" });
  console.error(err);
  res.status(500).json({ error: "Internal server error" });
};
