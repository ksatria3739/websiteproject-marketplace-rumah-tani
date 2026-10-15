# Rumah Tani — API Reference

Base URL: `/api` · Auth: `Authorization: Bearer <accessToken>` (JWT 15 min; refresh token rotates, 14 days).
Roles: `G` Guest · `P` Pembeli · `F` Petani · `T` Admin/Tengkulak · `S` Super Admin. Errors: `{ "error": "msg", "details"?: {} }` (401 unauthenticated, 403 forbidden, 409 conflict/stock, 422 validation).

## Auth & KYC
| Method | URL | Body | Response | Roles |
|---|---|---|---|---|
| POST | /auth/register | `{email,password,fullName,role:"petani"\|"pembeli",buyerType?,companyName?,regionId?,phone?}` | `201 {user,accessToken,refreshToken}` | G |
| POST | /auth/login | `{email,password}` | `{user,accessToken,refreshToken}` | G |
| POST | /auth/refresh | `{refreshToken}` (one-time use, rotated) | `{accessToken,refreshToken}` | G |
| POST | /auth/logout | `{refreshToken}` | `{ok}` | any |
| POST | /auth/forgot-password | `{email}` | `{ok}` (no user enumeration) | G |
| POST | /auth/reset-password | `{token,password}` (revokes all sessions) | `{ok}` | G |
| GET | /auth/me | – | profile + `kyc_status` | P F T S |
| POST | /kyc/documents | multipart `files[]` (jpg/png/pdf ≤5MB), `docTypes[]` | `201 {status:"pending"}` | P F T |
| GET | /kyc/pending | – | pending users (T: farmers in own region; S: all) with docs | T S |
| POST | /kyc/:userId/review | `{approve:boolean,note?}` | `{ok}` | T (own-region farmers) S |
| GET | /kyc/file/:name | – | file (private) | T S |

## Public (Guest read-only)
| Method | URL | Notes | Roles |
|---|---|---|---|
| GET | /public/catalog | `?q&regionId&categoryId&grade&page&limit` → `{page,items:[{id,name,slug,region,category,price_from,available,grades}]}` (only curated + active) | G+ |
| GET | /public/catalog/:slug | commodity + variants `{id,variant_name,grade,price,available,min_order}` | G+ |
| GET | /public/meta | regions, categories | G+ |
| GET | /public/articles, /public/articles/:slug | news | G+ |
| GET | /logistics/estimate | `?weightKg&originRegionId&destRegionId` → `{distanceKm,cost}` | G+ |
| GET | /logistics/track/:code | shipment + events | G+ |

## Inventory & Curation
| Method | URL | Body | Response | Roles |
|---|---|---|---|---|
| POST | /inventory/commodities | `{name,categoryId,regionId?,description?,imageUrl?,variants:[{variantName,grade:"A"\|"B"\|"C",price,stockQty,minOrder,weightPerUnitKg}]}` | `201 {id,curation}` (petani → `pending`; tengkulak → `approved`). Requires KYC | F T |
| GET | /inventory/commodities | – | own (F) / region (T) / all (S) with variants & stock/reserved | F T S |
| PATCH | /inventory/commodities/:id | `{name?,description?,imageUrl?}` (petani edit → re-curation) | `{ok}` | owner, T(region), S |
| POST | /inventory/commodities/:id/variants | variant object | `201 {id}` | owner, T, S |
| PATCH | /inventory/variants/:id | `{price?,minOrder?}` | `{ok}` | owner, T, S |
| POST | /inventory/variants/:id/stock | `{delta,reason:"harvest"\|"adjust"}` | `{stock_qty,reserved_qty}`; 409 if < reserved/0 | owner, T, S |
| GET | /curation/queue | – | pending commodities | T(region) S |
| POST | /curation/:id/decision | `{approve,note?,gradeOverrides?:[{variantId,grade}]}` | `{ok}` | T(region) S |

## Cart, Checkout, Orders
| Method | URL | Body | Response | Roles |
|---|---|---|---|---|
| PUT | /cart/items | `{variantId,qty,negotiationId?}` | `{ok}` (min order, availability, deal validity checked) | P |
| DELETE | /cart/items/:variantId | – | `{ok}` | P |
| GET | /cart?destRegionId= | – | `{groups:[{sellerId,sellerName,items,totalWeightKg,distanceKm,subtotal,tax,adminFee,shipping,total}],grandTotal}` | P |
| POST | /checkout | `{destRegionId,shipAddress,method:"va_bca"\|"va_bni"\|"va_bri"\|"va_mandiri"\|"gopay"\|"ovo"\|"bank_transfer"}` | `201 {paymentId,gatewayRef,payCode,amount,expiresAt,orders:[…]}` – one order per vendor, stock reserved atomically | P (+KYC for B2B) |
| GET | /orders | – | role-scoped list | P F T S |
| GET | /orders/:id | – | order + items + payment | participants, S |
| POST | /orders/:id/accept | – | seller moves `paid → processing` | F T |
| POST | /orders/:id/confirm-received | – | `delivered → completed` | P |
| POST | /webhooks/payment | gateway payload + `x-signature` (mock) / `signature_key` (Midtrans) | `{ok}` – idempotent, amount-verified | gateway |
| POST | /webhooks/mock-settle | `{orderId}` (PAYMENT_PROVIDER=mock only) | `{ok}` | P |

## Negotiation (B2B)
| Method | URL | Body | Response | Roles |
|---|---|---|---|---|
| POST | /negotiations | `{variantId,qty,unitPrice,message?}` (≥50% of list) | `201 {id}` | P(B2B) |
| GET | /negotiations | – | role-scoped threads with offers | P F T |
| POST | /negotiations/:id/offers | `{unitPrice,message?}` counter (alternating turns) | `201` | participants |
| POST | /negotiations/:id/respond | `{accept:boolean}` — accept locks `agreed_price` for 2 days | `{ok}` | participants |

## Logistics
| Method | URL | Body | Roles |
|---|---|---|---|
| GET/POST | /logistics/vehicles | `{plate,type,capacityKg,driverName?}` | T S |
| GET/PUT | /logistics/routes | `{originRegionId,destRegionId,baseFee,feePerKgKm}` (overrides global tariff) | T S |
| GET | /logistics/shipments | list | T S |
| POST | /logistics/shipments | `{orderId,vehicleId,routeId?}` – enforces fleet capacity | T S |
| PATCH | /logistics/shipments/:id/status | `{status,note?,lat?,lng?}` – strictly sequential: preparing→picked_up→in_transit→arrived_hub→out_for_delivery→delivered | T S |

## Dashboard & Super Admin
| Method | URL | Notes | Roles |
|---|---|---|---|
| GET | /dashboard/summary | to-process, sales, low stock | F T |
| GET | /admin/analytics | `?from&to&groupBy=day\|week\|month` → `{trend,top,profit}` | S |
| GET | /admin/reports/export | `?format=pdf\|xlsx&from&to` | S |
| GET | /admin/users · PATCH /admin/users/:id `{isActive}` · POST /admin/users/tengkulak | user mgmt (deactivation revokes sessions) | S |
| GET/PUT | /admin/settings[/:key] | tax, fees, tariffs, TTL (audited) | S |
| POST | /admin/categories · /admin/regions · /admin/articles | master data / CMS | S |
| GET | /admin/transactions | monitoring | S |
