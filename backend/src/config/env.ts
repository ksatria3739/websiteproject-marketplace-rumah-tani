import fs from "fs";
import path from "path";

// Minimal .env loader (no extra dependency)
const envFile = path.resolve(process.cwd(), ".env");
if (fs.existsSync(envFile)) {
  for (const line of fs.readFileSync(envFile, "utf8").split(/\r?\n/)) {
    const m = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/);
    if (m && process.env[m[1]] === undefined) process.env[m[1]] = m[2];
  }
}

const req = (k: string, d?: string) => {
  const v = process.env[k] ?? d;
  if (v === undefined) throw new Error(`Missing env ${k}`);
  return v;
};

export const env = {
  port: Number(req("PORT", "4000")),
  databaseUrl: req("DATABASE_URL", "postgres://postgres:postgres@localhost:5432/rumah_tani"),
  accessSecret: req("JWT_ACCESS_SECRET", "dev-access-secret-change-me-please-32"),
  refreshSecret: req("JWT_REFRESH_SECRET", "dev-refresh-secret-change-me-please-32"),
  corsOrigin: req("CORS_ORIGIN", "http://localhost:3000"),
  uploadDir: path.resolve(req("UPLOAD_DIR", "./uploads")),
  paymentProvider: req("PAYMENT_PROVIDER", "mock"),
  webhookSecret: req("PAYMENT_WEBHOOK_SECRET", "dev-webhook-secret"),
  midtransKey: process.env.MIDTRANS_SERVER_KEY ?? "",
};
