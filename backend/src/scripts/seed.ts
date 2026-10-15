import bcrypt from "bcryptjs";
import { pool } from "../config/db";

(async () => {
  const pw = await bcrypt.hash("Password123", 12);
  const ins = async (email: string, name: string, role: string, extra: any = {}) => (await pool.query(
    `INSERT INTO users(email,password_hash,full_name,role,region_id,buyer_type,company_name,kyc_status)
     VALUES ($1,$2,$3,$4,$5,$6,$7,'approved') ON CONFLICT (email) DO UPDATE SET full_name=EXCLUDED.full_name RETURNING id`,
    [email, pw, name, role, extra.region ?? null, extra.buyer ?? null, extra.company ?? null])).rows[0].id;
  await ins("admin@rumahtani.id", "Super Admin", "super_admin");
  const teng = await ins("tengkulak@rumahtani.id", "Tengkulak Bandung", "admin_tengkulak", { region: 1 });
  const tani = await ins("petani@rumahtani.id", "Pak Asep (Petani Garut)", "petani", { region: 2 });
  await ins("hotel@rumahtani.id", "Hotel Nusantara", "pembeli", { buyer: "b2b", company: "PT Hotel Nusantara" });
  await ins("umum@rumahtani.id", "Budi", "pembeli", { buyer: "b2c" });
  const c = (await pool.query(
    `INSERT INTO commodities(farmer_id,region_id,category_id,name,slug,description,curation,curated_by)
     VALUES ($1,2,1,'Cabai Rawit Merah','cabai-rawit-merah-seed','Cabai segar langsung dari Garut','approved',$2)
     ON CONFLICT (slug) DO UPDATE SET name=EXCLUDED.name RETURNING id`, [tani, teng])).rows[0].id;
  for (const [g, price] of [["A", 65000], ["B", 55000], ["C", 42000]])
    await pool.query(
      `INSERT INTO variants(commodity_id,variant_name,grade,price,stock_qty,min_order) VALUES ($1,'Cabai Rawit Merah',$2,$3,500,5)
       ON CONFLICT (commodity_id,variant_name,grade) DO NOTHING`, [c, g, price]);
  await pool.query(
    `INSERT INTO articles(slug,title,excerpt,body,published,published_at) VALUES
     ('selamat-datang','Selamat datang di Rumah Tani','Digitalisasi rantai pasok pertanian','Platform yang menghubungkan petani, tengkulak, dan pembeli.',true,now())
     ON CONFLICT (slug) DO NOTHING`);
  console.log("Seeded. All accounts use password: Password123");
  await pool.end();
})().catch(e => { console.error(e); process.exit(1); });
