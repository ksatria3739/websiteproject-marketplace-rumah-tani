import fs from "fs";
import path from "path";
import { pool } from "../config/db";

(async () => {
  await pool.query("CREATE TABLE IF NOT EXISTS _migrations (name TEXT PRIMARY KEY, applied_at TIMESTAMPTZ DEFAULT now())");
  const dir = path.resolve(__dirname, "../../migrations");
  for (const f of fs.readdirSync(dir).filter(f => f.endsWith(".sql")).sort()) {
    if ((await pool.query("SELECT 1 FROM _migrations WHERE name=$1", [f])).rowCount) continue;
    const c = await pool.connect();
    try {
      await c.query("BEGIN"); await c.query(fs.readFileSync(path.join(dir, f), "utf8"));
      await c.query("INSERT INTO _migrations(name) VALUES ($1)", [f]); await c.query("COMMIT");
      console.log("applied", f);
    } catch (e) { await c.query("ROLLBACK"); throw e; } finally { c.release(); }
  }
  await pool.end();
})().catch(e => { console.error(e); process.exit(1); });
