import express from "express";
import helmet from "helmet";
import cors from "cors";
import rateLimit from "express-rate-limit";
import path from "path";
import { env } from "./config/env";
import { errorHandler, HttpError, optionalAuth } from "./middleware/security";
import { authRouter, kycRouter } from "./modules/auth";
import { curationRouter, inventoryRouter, publicRouter } from "./modules/catalog";
import { cartRouter, checkoutRouter, negoRouter, orderRouter, webhookRouter } from "./modules/commerce";
import { logisticsRouter } from "./modules/logistics";
import { adminRouter, dashRouter } from "./modules/admin";

export const app = express();
app.set("trust proxy", 1);
app.use(helmet({ crossOriginResourcePolicy: { policy: "cross-origin" } }));
app.use(cors({ origin: env.corsOrigin.split(","), credentials: true }));
app.use(express.json({ limit: "1mb" }));
app.use(optionalAuth);                       // populates req.user; absent => Guest

const authLimiter = rateLimit({ windowMs: 15 * 60_000, limit: 30, standardHeaders: true });
const apiLimiter = rateLimit({ windowMs: 60_000, limit: 300, standardHeaders: true });
app.use("/api", apiLimiter);

app.get("/health", (_q, r) => r.json({ ok: true }));
app.use("/api/auth", authLimiter, authRouter);
app.use("/api/kyc", kycRouter);
app.use("/api/public", publicRouter);                 // Guest + all
app.use("/api/inventory", inventoryRouter);           // petani, tengkulak, super admin
app.use("/api/curation", curationRouter);             // tengkulak, super admin
app.use("/api/cart", cartRouter);                     // pembeli
app.use("/api/checkout", checkoutRouter);             // pembeli (+KYC for B2B)
app.use("/api/orders", orderRouter);
app.use("/api/negotiations", negoRouter);
app.use("/api/logistics", logisticsRouter);
app.use("/api/dashboard", dashRouter);
app.use("/api/admin", adminRouter);                   // super admin
app.use("/api/webhooks", webhookRouter);              // signature verified
// KYC documents are private: only reviewers may fetch them
app.get("/api/kyc/file/:name", (req, res, next) => {
  if (!req.user || !["super_admin", "admin_tengkulak"].includes(req.user.role)) return next(new HttpError(403, "Forbidden"));
  res.sendFile(path.join(env.uploadDir, path.basename(req.params.name)));
});

app.use((_q, r) => r.status(404).json({ error: "Not found" }));
app.use(errorHandler);
