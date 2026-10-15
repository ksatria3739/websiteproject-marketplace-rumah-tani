import { Pool, PoolClient, QueryResultRow } from "pg";
import { env } from "./env";

export const pool = new Pool({ connectionString: env.databaseUrl, max: 20 });

export async function query<T extends QueryResultRow = any>(text: string, params: any[] = []) {
  return (await pool.query<T>(text, params)).rows;
}

/**
 * Run fn inside a transaction. Retries on serialization/deadlock errors.
 * Callers lock rows with SELECT ... FOR UPDATE to prevent overselling.
 */
export async function tx<T>(fn: (c: PoolClient) => Promise<T>, retries = 3): Promise<T> {
  for (let attempt = 0; ; attempt++) {
    const c = await pool.connect();
    try {
      await c.query("BEGIN");
      const out = await fn(c);
      await c.query("COMMIT");
      return out;
    } catch (e: any) {
      await c.query("ROLLBACK").catch(() => {});
      if ((e.code === "40001" || e.code === "40P01") && attempt < retries) continue;
      throw e;
    } finally {
      c.release();
    }
  }
}

export async function getSettings(c?: PoolClient): Promise<Record<string, number>> {
  const rows = await (c ?? pool).query("SELECT key,value FROM system_settings");
  const out: Record<string, number> = {};
  for (const r of rows.rows) out[r.key] = Number(r.value);
  return out;
}
