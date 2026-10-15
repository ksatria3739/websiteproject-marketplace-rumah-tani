# Rumah Tani — ERD & Design Notes

```mermaid
erDiagram
  regions ||--o{ users : "home region"
  users ||--o{ kyc_documents : uploads
  users ||--o{ refresh_tokens : has
  users ||--o{ commodities : "farmer/tengkulak owns"
  regions ||--o{ commodities : origin
  categories ||--o{ commodities : classifies
  commodities ||--o{ variants : "grade/variety SKUs"
  variants ||--o{ stock_movements : ledger
  users ||--o{ cart_items : holds
  variants ||--o{ cart_items : in
  users ||--o{ negotiations : "buyer / seller"
  variants ||--o{ negotiations : about
  negotiations ||--o{ negotiation_offers : thread
  users ||--o{ payments : pays
  payments ||--|{ orders : "1 payment : N vendor orders"
  orders ||--|{ order_items : contains
  variants ||--o{ order_items : sold
  orders ||--o| shipments : delivered_by
  vehicles ||--o{ shipments : carries
  routes ||--o{ shipments : follows
  shipments ||--o{ shipment_events : tracking
  users ||--o{ vehicles : owns
  users ||--o{ routes : defines
```

## Race-condition strategy (stock)
1. `variants` has `CHECK (stock_qty >= 0)`, `CHECK (reserved_qty >= 0)`, `CHECK (reserved_qty <= stock_qty)` — the database itself refuses oversell.
2. Checkout runs in one transaction: `SELECT … FROM variants WHERE id = ANY(cart) ORDER BY id FOR UPDATE` (consistent lock order ⇒ no deadlocks), re-validates availability *after* locking, then increments `reserved_qty`.
3. Payment `paid` webhook converts reserved → sold; `expired/failed` (or the 60 s expiry job) releases the reservation. Webhook is idempotent (`payments … FOR UPDATE` + status check) and verifies signature and amount.
4. Stock edits by farmers are **delta-based** under row lock, plus an append-only `stock_movements` ledger.
5. Transactions auto-retry on `40001/40P01`.

## RBAC
Guest = no token (read-only `/public/*`). Roles enforced by `requireRole()`; tengkulak additionally scoped by `region_id`; KYC gate via `requireKyc`. Frontend adds edge middleware + `<RoleGuard>` (UX only; API is the source of truth).
