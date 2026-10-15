-- Rumah Tani - PostgreSQL schema (001_init)
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE EXTENSION IF NOT EXISTS citext;

CREATE TYPE user_role AS ENUM ('super_admin','admin_tengkulak','petani','pembeli');
-- "guest" = unauthenticated visitor, intentionally not stored.
CREATE TYPE buyer_type AS ENUM ('b2b','b2c');
CREATE TYPE kyc_status AS ENUM ('unsubmitted','pending','approved','rejected');
CREATE TYPE curation_status AS ENUM ('draft','pending','approved','rejected');
CREATE TYPE grade_t AS ENUM ('A','B','C');
CREATE TYPE nego_status AS ENUM ('open','accepted','rejected','expired','cancelled','ordered');
CREATE TYPE order_status AS ENUM ('pending_payment','paid','processing','shipped','delivered','completed','cancelled','expired');
CREATE TYPE payment_status AS ENUM ('pending','paid','failed','expired','refunded');
CREATE TYPE shipment_status AS ENUM ('preparing','picked_up','in_transit','arrived_hub','out_for_delivery','delivered');

CREATE TABLE regions (
  id SERIAL PRIMARY KEY,
  name TEXT NOT NULL UNIQUE,
  province TEXT NOT NULL,
  lat NUMERIC(9,6) NOT NULL,
  lng NUMERIC(9,6) NOT NULL
);

CREATE TABLE users (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  email CITEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  full_name TEXT NOT NULL,
  phone TEXT,
  role user_role NOT NULL,
  buyer_type buyer_type,                         -- only for role=pembeli
  company_name TEXT,
  region_id INT REFERENCES regions(id),          -- petani/tengkulak home region
  address TEXT,
  kyc_status kyc_status NOT NULL DEFAULT 'unsubmitted',
  kyc_note TEXT,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  token_version INT NOT NULL DEFAULT 0,          -- bump to revoke all sessions
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CHECK (role <> 'pembeli' OR buyer_type IS NOT NULL)
);
CREATE INDEX idx_users_role_region ON users(role, region_id);

CREATE TABLE refresh_tokens (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  token_hash TEXT NOT NULL UNIQUE,
  expires_at TIMESTAMPTZ NOT NULL,
  revoked_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE password_resets (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  token_hash TEXT NOT NULL UNIQUE,
  expires_at TIMESTAMPTZ NOT NULL,
  used_at TIMESTAMPTZ
);

CREATE TABLE kyc_documents (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  doc_type TEXT NOT NULL,                        -- ktp, npwp, nib, selfie
  file_path TEXT NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE categories (
  id SERIAL PRIMARY KEY,
  name TEXT NOT NULL UNIQUE,
  slug TEXT NOT NULL UNIQUE
);

CREATE TABLE commodities (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  farmer_id UUID NOT NULL REFERENCES users(id),
  region_id INT NOT NULL REFERENCES regions(id),
  category_id INT NOT NULL REFERENCES categories(id),
  name TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  description TEXT,
  image_url TEXT,
  curation curation_status NOT NULL DEFAULT 'pending',
  curated_by UUID REFERENCES users(id),
  curation_note TEXT,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_commodities_catalog ON commodities(curation, is_active, region_id, category_id);
CREATE INDEX idx_commodities_name_trgm ON commodities (lower(name));

-- Variant = sellable SKU (commodity + grade + variety). Holds stock.
CREATE TABLE variants (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  commodity_id UUID NOT NULL REFERENCES commodities(id) ON DELETE CASCADE,
  variant_name TEXT NOT NULL,                    -- e.g. "Cabai Rawit Merah"
  grade grade_t NOT NULL,
  unit TEXT NOT NULL DEFAULT 'kg',
  price NUMERIC(14,2) NOT NULL CHECK (price > 0),
  stock_qty NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (stock_qty >= 0),       -- DB-level oversell guard
  reserved_qty NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (reserved_qty >= 0),
  min_order NUMERIC(12,2) NOT NULL DEFAULT 1 CHECK (min_order > 0),
  weight_per_unit_kg NUMERIC(8,3) NOT NULL DEFAULT 1 CHECK (weight_per_unit_kg > 0),
  version INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (commodity_id, variant_name, grade),
  CHECK (reserved_qty <= stock_qty)
);

CREATE TABLE stock_movements (
  id BIGSERIAL PRIMARY KEY,
  variant_id UUID NOT NULL REFERENCES variants(id),
  delta NUMERIC(12,2) NOT NULL,
  reason TEXT NOT NULL,                          -- harvest, adjust, reserve, release, sale
  ref_id UUID,
  actor_id UUID REFERENCES users(id),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_stock_mv_variant ON stock_movements(variant_id, created_at DESC);

CREATE TABLE cart_items (
  user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  variant_id UUID NOT NULL REFERENCES variants(id),
  qty NUMERIC(12,2) NOT NULL CHECK (qty > 0),
  negotiation_id UUID,                           -- accepted deal price, if any
  PRIMARY KEY (user_id, variant_id)
);

CREATE TABLE negotiations (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  buyer_id UUID NOT NULL REFERENCES users(id),
  seller_id UUID NOT NULL REFERENCES users(id),  -- petani or tengkulak owning the listing
  variant_id UUID NOT NULL REFERENCES variants(id),
  qty NUMERIC(12,2) NOT NULL CHECK (qty > 0),
  status nego_status NOT NULL DEFAULT 'open',
  agreed_price NUMERIC(14,2),
  expires_at TIMESTAMPTZ NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE negotiation_offers (
  id BIGSERIAL PRIMARY KEY,
  negotiation_id UUID NOT NULL REFERENCES negotiations(id) ON DELETE CASCADE,
  from_user UUID NOT NULL REFERENCES users(id),
  unit_price NUMERIC(14,2) NOT NULL CHECK (unit_price > 0),
  message TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE cart_items ADD FOREIGN KEY (negotiation_id) REFERENCES negotiations(id);

-- One payment group per checkout; one order per vendor (multi-vendor split)
CREATE TABLE payments (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  buyer_id UUID NOT NULL REFERENCES users(id),
  gateway_ref TEXT UNIQUE,                       -- gateway order_id
  method TEXT NOT NULL,                          -- va_bca, va_bni, gopay, ovo, bank_transfer
  amount NUMERIC(14,2) NOT NULL,
  status payment_status NOT NULL DEFAULT 'pending',
  pay_code TEXT,                                 -- VA number / deeplink
  expires_at TIMESTAMPTZ NOT NULL,
  paid_at TIMESTAMPTZ,
  raw_webhook JSONB,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE orders (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  code TEXT NOT NULL UNIQUE,
  payment_id UUID NOT NULL REFERENCES payments(id),
  buyer_id UUID NOT NULL REFERENCES users(id),
  seller_id UUID NOT NULL REFERENCES users(id),
  region_id INT NOT NULL REFERENCES regions(id), -- origin region
  dest_region_id INT NOT NULL REFERENCES regions(id),
  ship_address TEXT NOT NULL,
  status order_status NOT NULL DEFAULT 'pending_payment',
  subtotal NUMERIC(14,2) NOT NULL,
  tax NUMERIC(14,2) NOT NULL,
  admin_fee NUMERIC(14,2) NOT NULL,
  shipping_cost NUMERIC(14,2) NOT NULL,
  total NUMERIC(14,2) NOT NULL,
  total_weight_kg NUMERIC(12,2) NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_orders_buyer ON orders(buyer_id, created_at DESC);
CREATE INDEX idx_orders_seller ON orders(seller_id, status);
CREATE INDEX idx_orders_created ON orders(created_at);

CREATE TABLE order_items (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  order_id UUID NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  variant_id UUID NOT NULL REFERENCES variants(id),
  name_snapshot TEXT NOT NULL,
  grade grade_t NOT NULL,
  qty NUMERIC(12,2) NOT NULL CHECK (qty > 0),
  unit_price NUMERIC(14,2) NOT NULL,
  line_total NUMERIC(14,2) NOT NULL
);
CREATE INDEX idx_order_items_variant ON order_items(variant_id);

CREATE TABLE vehicles (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  owner_id UUID NOT NULL REFERENCES users(id),   -- tengkulak
  plate TEXT NOT NULL UNIQUE,
  type TEXT NOT NULL,
  capacity_kg NUMERIC(10,2) NOT NULL CHECK (capacity_kg > 0),
  driver_name TEXT,
  is_available BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE routes (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  owner_id UUID NOT NULL REFERENCES users(id),
  origin_region_id INT NOT NULL REFERENCES regions(id),
  dest_region_id INT NOT NULL REFERENCES regions(id),
  base_fee NUMERIC(14,2) NOT NULL DEFAULT 0,
  fee_per_kg_km NUMERIC(10,4) NOT NULL DEFAULT 0,  -- overrides global setting if > 0
  UNIQUE (owner_id, origin_region_id, dest_region_id)
);

CREATE TABLE shipments (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  order_id UUID NOT NULL UNIQUE REFERENCES orders(id),
  vehicle_id UUID REFERENCES vehicles(id),
  route_id UUID REFERENCES routes(id),
  status shipment_status NOT NULL DEFAULT 'preparing',
  tracking_code TEXT NOT NULL UNIQUE,
  last_lat NUMERIC(9,6), last_lng NUMERIC(9,6),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE shipment_events (
  id BIGSERIAL PRIMARY KEY,
  shipment_id UUID NOT NULL REFERENCES shipments(id) ON DELETE CASCADE,
  status shipment_status NOT NULL,
  note TEXT, lat NUMERIC(9,6), lng NUMERIC(9,6),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE articles (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  slug TEXT NOT NULL UNIQUE,
  title TEXT NOT NULL,
  excerpt TEXT,
  body TEXT NOT NULL,
  cover_url TEXT,
  author_id UUID REFERENCES users(id),
  published BOOLEAN NOT NULL DEFAULT FALSE,
  published_at TIMESTAMPTZ
);

CREATE TABLE system_settings (
  key TEXT PRIMARY KEY,
  value TEXT NOT NULL,
  updated_by UUID REFERENCES users(id),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
INSERT INTO system_settings(key,value) VALUES
 ('tax_rate','0.11'),            -- PPN 11%
 ('admin_fee_rate','0.02'),      -- 2% platform fee
 ('admin_fee_min','2500'),
 ('ship_base_fee','15000'),
 ('ship_fee_per_kg_km','0.35'),
 ('payment_ttl_minutes','60');

CREATE TABLE audit_logs (
  id BIGSERIAL PRIMARY KEY,
  actor_id UUID REFERENCES users(id),
  action TEXT NOT NULL,
  entity TEXT, entity_id TEXT,
  meta JSONB,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

INSERT INTO categories(name,slug) VALUES
 ('Sayuran','sayuran'),('Buah','buah'),('Biji-bijian','biji-bijian'),('Rempah','rempah'),('Umbi','umbi');
INSERT INTO regions(name,province,lat,lng) VALUES
 ('Bandung','Jawa Barat',-6.9175,107.6191),
 ('Garut','Jawa Barat',-7.2279,107.9087),
 ('Malang','Jawa Timur',-7.9666,112.6326),
 ('Magelang','Jawa Tengah',-7.4797,110.2177),
 ('Jakarta','DKI Jakarta',-6.2088,106.8456),
 ('Surabaya','Jawa Timur',-7.2575,112.7521);
