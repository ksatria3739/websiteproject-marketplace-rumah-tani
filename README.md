# 🌾 Marketplace Rumah Tani — Platform Rantai Pasok Pertanian Terintegrasi (B2B/B2C)

Sistem e-commerce agrikultur modern yang mendigitalisasi rantai pasok dari hulu (petani) ke hilir (konsumen & industri). Menghubungkan **Petani Produsen**, **Tengkulak / Distributor Wilayah**, dan **Pembeli (B2B Korporasi & B2C Konsumen)** dalam satu ekosistem terpadu dengan transparansi harga, pemantauan stok real-time tanpa race condition, negosiasi harga B2B, dan pelacakan logistik armada wilayah.

---

## 🛠️ Tech Stack Utama

| Layer | Teknologi | Peran & Alasan Pemilihan |
|---|---|---|
| **Backend (BE)** | **Laravel 12 (PHP 8.2)** | RESTful API tangguh, transaksi database atomik, middleware 5-level RBAC, integritas finansial, token Sanctum. |
| **Database (DB)** | **MySQL 8.x (InnoDB)** | Relational database ACID-compliant dengan *pessimistic row locking* (`SELECT ... FOR UPDATE`) untuk mencegah *race condition* stok. |
| **Frontend (FE)** | **Vue.js 3 + Vite + Pinia** | Antarmuka reaktif berkecepatan tinggi, SEO-friendly, state management Pinia, dan route guards RBAC berlapis. |
| **Styling & UI/UX** | **CSS Modular Mobile-First** | Responsif untuk petani di kebun/lapangan maupun layar lebar korporasi & admin. |

---

## 👥 Arsitektur Multi-Peran (RBAC Berlapis & Delegasi)

| Peran (Role) | Kredensial Demo | Akses Halaman | Tanggung Jawab & Fitur Utama |
|---|---|---|---|
| **🔴 Super Admin** | `admin@rumahtani.id`<br>`Password123` | `/super` | Kontrol penuh ekosistem: manajemen role global (semua level), analitik GMV & profit operasional, ekspor CSV/Excel finansial, validasi KYC, dan pengaturan parameter global (PPN, fee platform). |
| **🟠 Admin Support L3** | `support@rumahtani.id`<br>`Password123` | `/super` | **Level di bawah Super Admin untuk supporting operasional:**<br>1. **Dukungan Stok L3 (Supporting Petani):** Dapat menginput & menyesuaikan stok komoditas semua petani secara langsung/darurat.<br>2. **Manajemen User Role Lapangan:** Dapat mengubah role user (`pembeli`, `petani`, `admin_tengkulak`) saat traffic user membludak.<br>3. *Strict Boundary:* Dibatasi tidak bisa mempromosikan user menjadi `admin` atau `super_admin`. |
| **🔵 Admin Tengkulak** | `tengkulak@rumahtani.id`<br>`Password123` | `/tengkulak` | Kurasi hasil panen petani regional (persetujuan mutu Grade A/B/C), alokasi armada truk/pickup, manajemen rute ongkir, dan dispatch status pengiriman. *Terisolasi ketat dari manajemen role pengguna.* |
| **🟢 Petani Produsen** | `petani@rumahtani.id`<br>`Password123` | `/petani` | Antarmuka *mobile-first*: input panen baru, penentuan grade & harga, **pembaruan stok atomik real-time (+/-)**, respon negosiasi harga masuk dari korporasi. |
| **🟡 Pembeli B2B (Hotel/Pabrik)** | `hotel@rumahtani.id`<br>`Password123` | `/buyer` | Keranjang belanja massal, **fitur negosiasi & penawaran harga khusus (B2B bidding)**, kalkulator ongkir multi-vendor, checkout atomik anti-race-condition, simulasi pembayaran sandbox. |
| **⚪ Pembeli B2C (Konsumen)** | `umum@rumahtani.id`<br>`Password123` | `/buyer` | Pembelian ritel langsung dari petani, integrasi estimasi pengiriman instan, pelacakan armada visual. |
| **🌐 Guest (Tamu Publik)** | *Tanpa Otentikasi* | `/`, `/catalog`, `/articles`, `/track` | Mode *read-only*: eksplorasi etalase komoditas dengan filter Grade A/B/C, membaca kabar & edukasi rantai pasok tani, pelacakan nomor resi publik. |

---

## 🔒 Solusi Anti-Race Condition Stok

Untuk menangani transaksi checkout bersamaan pada stok terbatas:
1. **Pessimistic Row Locking (`SELECT ... FOR UPDATE`)**:
   Saat checkout berlangsung, Laravel mengunci baris varian komoditas di MySQL dalam urutan ID menaik (`ORDER BY id ASC`) untuk mencegah *deadlock*.
2. **Siklus Hidup Stok (Reserved vs Sold)**:
   - Saat checkout: `reserved_qty += qty` (stok dikunci selama masa pembayaran).
   - Jika pembayaran lunas (webhook/sandbox): `stock_qty -= qty`, `reserved_qty -= qty`, mutasi dicatat di `stock_movements` bertipe `sale`.
   - Jika pembayaran kedaluwarsa/batal: `reserved_qty -= qty`, stok kembali tersedia bagi pembeli lain.

---

## 🚀 Panduan Menjalankan Sistem Secara Live

### 1. Prasyarat Lingkungan
- PHP >= 8.2 dengan ekstensi `pdo_mysql`, `curl`, `mbstring`.
- MySQL Server 8.x aktif pada `127.0.0.1:3306` (database: `rumah_tani`).
- Node.js >= 18.x dan npm.

### 2. Menjalankan Backend Laravel
```bash
cd backend-laravel
composer install
php artisan migrate --seed
php artisan serve --port=8000
```
> Server API aktif di: **`http://127.0.0.1:8000/api`**

### 3. Menjalankan Frontend Vue 3
```bash
cd frontend-vue
npm install
npm run dev -- --port 3000 --host
```
> Aplikasi web aktif di: **`http://localhost:3000`**

---

## 🧪 Hasil Pengujian & Bug Check (Live Verification)

Sistem telah diuji secara menyeluruh melalui 2 test suite otomatis:

### Test Suite 1: Integritas Database & Business Logic (`tests/live_test.php`)
```
========================================================
🌾 RUMAH TANI — LIVE SYSTEM & BUG CHECK TEST SUITE
========================================================
  [PASS] Total registered demo users across 5 roles
  [PASS] Super Admin account verified
  [PASS] Tengkulak account verified
  [PASS] Petani account verified
  [PASS] B2B Buyer verified
  [PASS] B2C Buyer verified
  [PASS] Sanctum bearer token generated for Petani & Buyer
  [PASS] Commodity Catalog & Variant Grades (A, B, C)
  [PASS] Distance & Shipping Cost Estimator (Haversine Formula)
  [PASS] PPN 11% & Platform Admin Fee 2% calculated accurately
  [PASS] Real-Time Stock Adjustment (Locking & Audit Trail)
  [PASS] B2B Negotiation (Bidding, Counter-Offer, Acceptance)
  [PASS] Anti-Race Condition Atomic Checkout & Stock Reservation
  [PASS] Payment Webhook Settlement & Stock Deductions
  [PASS] Logistics Allocation, Fleet Capacity & Step-by-Step Tracking
  [PASS] Super Admin Analytics & Financial Metrics
========================================================
🎉 ALL 31 TESTS PASSED! ZERO BUGS DETECTED IN LOGIC & DB.
========================================================
```

### Test Suite 2: RESTful API Live Endpoints (`tests/e2e_http_test.php`)
```
========================================================
🌐 LIVE HTTP REST API TEST SUITE (5-LEVEL RBAC)
========================================================
▶ 1. Guest (Unauthenticated Public User):
  [PASS] GET /health responds 200 with MySQL
  [PASS] GET /public/catalog returns catalog items
  [PASS] GET /public/catalog/cabai-rawit-merah-garut returns product variants
  [PASS] GET /public/articles returns education news array
  [PASS] POST /checkout blocked for Guest (HTTP 401 Unauthorized)
▶ 2. Role: Petani (Farmer / Producer):
  [PASS] POST /auth/login successful for Petani
  [PASS] GET /inventory/commodities returns farmer inventory
  [PASS] POST /inventory/variants/1/stock atomic update works
▶ 3. Role: Admin Tengkulak (Regional Distributor):
  [PASS] POST /auth/login successful for Tengkulak
  [PASS] GET /curation/queue accessible by Tengkulak
  [PASS] GET /logistics/vehicles returns fleet armadas
  [PASS] GET /logistics/shipments returns regional shipments
▶ 4. Role: Pembeli B2B (Corporate Buyer):
  [PASS] POST /auth/login B2B Buyer verified
  [PASS] PUT /cart/items adds product to B2B cart
  [PASS] GET /cart returns multi-vendor grouped cart items
  [PASS] POST /negotiations creates B2B bidding offer
  [PASS] GET /negotiations returns user negotiations
  [PASS] POST /checkout creates atomic order & reserves stock
  [PASS] GET /orders returns buyer order history
  [PASS] POST /webhooks/mock-settle confirms settlement & executes stock deduction
▶ 5. Role: Super Admin (Executive Control):
  [PASS] POST /auth/login Super Admin verified
  [PASS] GET /admin/analytics returns live GMV & platform profit summary
  [PASS] GET /admin/users returns multi-role user accounts
  [PASS] GET /admin/settings returns system configs
  [PASS] GET /admin/reports/export generates financial and order CSV report
========================================================
SUMMARY: 25 Passed, 0 Failed
🎉 LIVE API TEST SUITE COMPLETED WITH 100% SUCCESS!
========================================================
```

### Test Suite 3: RBAC Delegasi & Dukungan Stok L3 (`tests/admin_support_test.php`)
```
========================================================
🛡️  ADMIN SUPPORT L3 & SUPER ADMIN ROLE MANAGEMENT TEST
========================================================
▶ 1. Authenticating test users:
  ✅ [PASS] Super Admin login succeeds
  ✅ [PASS] Admin Support L3 login succeeds
  ✅ [PASS] Admin Tengkulak login succeeds
  ✅ [PASS] Petani login succeeds
▶ 2. Admin Support L3 Stock Management (L3 Support):
  ✅ [PASS] Admin Support can list all farmer commodities
  ✅ [PASS] Found test commodity variant (ID: 1, Initial: 0 kg)
  ✅ [PASS] Admin Support successfully added +15 kg stock with reason 'l3_support'
  ✅ [PASS] Revert test adjustment succeeds
▶ 3. Admin Support Role Management Bounds Check:
  ✅ [PASS] Admin Support can list users for management
  ✅ [PASS] Admin Support can promote user to 'petani'
  ✅ [PASS] Admin Support can promote user to 'admin_tengkulak'
  ✅ [PASS] Admin Support CANNOT promote user to 'admin' (HTTP 403)
  ✅ [PASS] Admin Support CANNOT promote user to 'super_admin' (HTTP 403)
  ✅ [PASS] Admin Support restored user back to 'pembeli'
▶ 4. Admin Tengkulak Boundary Check (Strict Isolation):
  ✅ [PASS] Admin Tengkulak is strictly BLOCKED from PATCH /admin/users/{id}/role (HTTP 403)
  ✅ [PASS] Admin Tengkulak is BLOCKED from farmer stock adjustment (HTTP 403)
▶ 5. Super Admin Global Role Management Authority:
  ✅ [PASS] Super Admin CAN promote user to 'admin'
  ✅ [PASS] Super Admin restored user back to 'pembeli'
========================================================
📊 RESULTS: 18 Passed, 0 Failed
========================================================
```

### Test Suite 4: Revisi Katalog Home, Upload Foto Real-Time & Tripay QRIS (`tests/revisions_test.php`)
```
========================================================
🌾 REVISI 3 FITUR: KATALOG HOME, UPLOAD FOTO & TRIPAY QRIS
========================================================
▶ 1. Pengujian Katalog di Halaman Home / Index:
  ✅ [PASS] GET /public/catalog responds 200 OK
  ✅ [PASS] Katalog menghasilkan array komoditas untuk halaman utama
  ✅ [PASS] Ada komoditas aktif yang siap tampil di halaman depan
▶ 2. Pengujian Upload Foto Panen Real-Time (Petani):
  ✅ [PASS] Login Petani berhasil
  ✅ [PASS] Upload foto panen real-time POST /inventory/upload-image berhasil
  ✅ [PASS] Path penyimpanan gambar (/storage/commodities/...) diterima
  ✅ [PASS] Petani berhasil menyimpan komoditas dengan foto panen real-time
▶ 3. Pengujian Payment Gateway Tripay & QRIS (Tidak Ribet):
  ✅ [PASS] GET /payment/channels mengembalikan channel pembayaran aktif
  ✅ [PASS] Tripay & Direct Gateway channels terkonfigurasi
  ✅ [PASS] Login Pembeli B2B berhasil
  ✅ [PASS] Produk berhasil ditambahkan ke keranjang belanja
  ✅ [PASS] Checkout sukses dengan metode QRIS Real-Time
  ✅ [PASS] QR Code URL dinamis berhasil dihasilkan untuk scan
  ✅ [PASS] Referensi gateway transaksi Tripay (gatewayRef) valid
▶ 4. Simulasi Webhook Tripay (Instant Settlement):
  ✅ [PASS] Webhook Tripay berhasil memproses status PAID secara otomatis
  ✅ [PASS] Status pesanan otomatis berubah menjadi 'paid' & mutasi stok sale tercatat
========================================================
📊 REVISI TEST SUMMARY: 16 Passed, 0 Failed
========================================================
```

---

## 📁 Struktur Direktori Proyek

```
rumah-tani/
├── backend-laravel/               # Backend Laravel 12 + MySQL
│   ├── app/
│   │   ├── Http/Controllers/Api/  # REST API Controllers (Auth, Catalog, Commerce, Inventory, Curation, Logistics, Admin, Kyc)
│   │   ├── Http/Middleware/       # EnsureRole (RBAC 5-level) & EnsureKyc
│   │   ├── Models/                # Eloquent Models (Commodity, Variant, Order, Shipment, Vehicle, etc.)
│   │   └── Services/              # PricingService (Tarif Haversine, Pajak, Fee)
│   ├── database/migrations/       # 19 Tabel Relasional MySQL
│   ├── database/seeders/          # DatabaseSeeder (Master data 6 kota, komoditas, grade, akun demo)
│   ├── routes/api.php             # 54 Endpoint RESTful
│   └── tests/                     # Suite Pengujian Live (live_test.php & e2e_http_test.php)
│
└── frontend-vue/                  # Frontend Vue 3 + Vite + Pinia
    ├── src/
    │   ├── router/index.js        # 5-Level RBAC Navigation Route Guard
    │   ├── stores/auth.js         # Pinia Auth State Management
    │   ├── services/api.js        # Fetch Client targeting http://127.0.0.1:8000/api
    │   ├── views/
    │   │   ├── HomeView.vue       # Landing Page & Rantai Pasok
    │   │   ├── CatalogView.vue    # Etalase Komoditas & Filter Grade A/B/C
    │   │   ├── ProductDetailView.vue # Detail Mutu & Form B2B Bidding
    │   │   ├── TrackingView.vue   # Visual Stepper Pelacakan Armada
    │   │   ├── ArticlesView.vue   # Kabar & Edukasi Petani
    │   │   ├── LoginView.vue      # Login dengan 1-Click Quick Demo RBAC
    │   │   ├── RegisterView.vue   # Registrasi Akun Multi-Peran
    │   │   ├── petani/            # PetaniDashboard.vue (Input Panen, +/- Stok Atomik)
    │   │   ├── tengkulak/         # TengkulakDashboard.vue (Kurasi Mutu, Alokasi Truk)
    │   │   ├── buyer/             # BuyerDashboard.vue (Keranjang Massal, Nego, Mock Bayar)
    │   │   └── super/             # SuperAdminDashboard.vue (Analitik GMV, KYC, Ekspor Laporan)
    │   └── App.vue & style.css    # Layout Utama & Desain Sistem Mobile-First
```
